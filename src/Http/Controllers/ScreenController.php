<?php

namespace TwoWee\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use TwoWee\Laravel\TwoWee;

class ScreenController extends Controller
{
    use \TwoWee\Laravel\Http\Concerns\AuthorizesResources;

    public function __construct(
        protected TwoWee $twoWee,
    ) {}

    public function list(Request $request, string $resource): JsonResponse
    {
        $resourceClass = $this->twoWee->getResource($resource);

        if ($resourceClass === null) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        if ($denied = $this->authorize($resourceClass, 'viewAny')) {
            return $denied;
        }

        $modelClass = $resourceClass::getModel();
        $searchTerm = $request->query('query');
        $usesScout = in_array('Laravel\Scout\Searchable', class_uses_recursive($modelClass));

        if ($searchTerm !== null && $searchTerm !== '' && $usesScout) {
            // Scout search — uses the model's toSearchableArray() and configured driver
            $scoutQuery = $modelClass::search($searchTerm);
            $maxRows = $resourceClass::maxRows();
            if ($maxRows !== null) {
                $scoutQuery->take($maxRows);
            }
            $rows = $scoutQuery->get();
        } else {
            $query = $modelClass::query();

            // Apply ?query= search filter (LIKE fallback)
            if ($searchTerm !== null && $searchTerm !== '') {
                $searchColumns = $resourceClass::resolveSearchColumns();
                if (! empty($searchColumns)) {
                    $query->where(function ($q) use ($searchColumns, $searchTerm) {
                        foreach ($searchColumns as $column) {
                            $q->orWhere($column, 'like', '%' . $searchTerm . '%');
                        }
                    });
                }
            }

            // Apply maxRows safety valve
            $maxRows = $resourceClass::maxRows();
            if ($maxRows !== null) {
                $query->limit($maxRows);
            }

            $rows = $query->get();
        }
        $rowCount = $rows->count();

        $status = null;
        if ($rowCount === 0) {
            $label = strtolower(Str::plural($resourceClass::getLabel()));
            $status = ($searchTerm !== null && $searchTerm !== '')
                ? "No {$label} matching '{$searchTerm}'"
                : "No {$label} found.";
        }

        return response()->json(
            $resourceClass::toListJson($rows, $rowCount, $status)
        );
    }

    public function show(Request $request, string $resource, string $id): JsonResponse
    {
        $resourceClass = $this->twoWee->getResource($resource);

        if ($resourceClass === null) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        $model = $resourceClass::findRecord($id);

        if ($model === null) {
            return response()->json(['error' => 'Record not found'], 404);
        }

        if ($denied = $this->authorize($resourceClass, 'view', $model)) {
            return $denied;
        }

        return response()->json($resourceClass::toCardJson($model));
    }

    public function create(Request $request, string $resource): JsonResponse
    {
        $resourceClass = $this->twoWee->getResource($resource);

        if ($resourceClass === null) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        if ($denied = $this->authorize($resourceClass, 'create')) {
            return $denied;
        }

        return response()->json($resourceClass::toCreateJson());
    }
}
