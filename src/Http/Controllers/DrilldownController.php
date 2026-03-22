<?php

namespace TwoWee\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use TwoWee\Laravel\Columns\TextColumn;
use TwoWee\Laravel\TwoWee;

class DrilldownController extends Controller
{
    public function __construct(
        protected TwoWee $twoWee,
    ) {}

    public function drilldown(Request $request, string $resource, string $fieldId, string $key): JsonResponse
    {
        return $this->resolve($request, $resource . ':' . $fieldId, $fieldId, $key);
    }

    public function drilldownGlobal(Request $request, string $fieldId, string $key): JsonResponse
    {
        return $this->resolve($request, $fieldId, $fieldId, $key);
    }

    protected function resolve(Request $request, string $lookupKey, string $fieldId, string $key): JsonResponse
    {
        // 1. Check for relationship drilldown (from aggregate + drillDown)
        $relationInfo = $this->twoWee->getRelationDrilldown($lookupKey)
            ?? $this->twoWee->getRelationDrilldown($fieldId);

        if ($relationInfo !== null) {
            return $this->relationshipDrilldown($relationInfo, $key);
        }

        // 2. Fall back to LookupDefinition drilldown
        $definition = $this->twoWee->getLookup($lookupKey);

        if ($definition === null) {
            return response()->json(['error' => 'Drilldown not found'], 404);
        }

        $context = $request->query();
        $records = $definition->resolveDrilldown($key, $context);

        $columns = $definition->getColumns();
        $columnDefs = array_map(fn ($col) => $col->toArray(), $columns);

        $rowData = \TwoWee\Laravel\Support\FieldHelpers::formatRows($records, $columns);

        return response()->json([
            'layout' => 'List',
            'title' => $fieldId . ' - ' . $key,
            'sections' => [],
            'lines' => [
                'columns' => $columnDefs,
                'rows' => $rowData,
                'row_count' => count($rowData),
                'selectable' => false,
            ],
        ]);
    }

    protected function relationshipDrilldown(array $info, string $key): JsonResponse
    {
        $resourceClass = $info['resource'];
        $target = $info['target'];
        $explicitColumns = $info['columns'];

        $modelClass = $resourceClass::getModel();
        $parentModel = $modelClass::find($key);

        if ($parentModel === null) {
            return response()->json(['error' => 'Record not found'], 404);
        }

        // Resolve target (model class or string) to relationship name
        $relationName = \TwoWee\Laravel\Fields\Field::resolveRelationNameStatic($parentModel, $target);

        if (! method_exists($parentModel, $relationName)) {
            return response()->json(['error' => 'Relationship not found'], 404);
        }

        $records = $parentModel->{$relationName}()->get();

        // Resolve columns: explicit > related resource's table() > auto-detect
        $columns = $this->resolveColumns($explicitColumns, $relationName, $parentModel);

        $columnDefs = array_map(fn ($col) => $col->toArray(), $columns);

        $rowData = \TwoWee\Laravel\Support\FieldHelpers::formatRows($records, $columns);

        $user = Auth::guard('twowee')->user();

        return response()->json([
            'layout' => 'List',
            'title' => $relationName . ' - ' . $key,
            'user_display_name' => $user?->name ?? null,
            'sections' => [],
            'lines' => [
                'columns' => $columnDefs,
                'rows' => $rowData,
                'row_count' => count($rowData),
                'selectable' => false,
            ],
        ]);
    }

    protected function resolveColumns(array $explicitColumns, string $relationName, $parentModel): array
    {
        // 1. Explicit columns from ->drillDown('relation', columns: [...])
        if (! empty($explicitColumns)) {
            return $explicitColumns;
        }

        // 2. Try to find a registered resource for the related model
        $relation = $parentModel->{$relationName}();
        $relatedModelClass = get_class($relation->getRelated());

        foreach ($this->twoWee->getResources() as $resourceClass) {
            if ($resourceClass::getModel() === $relatedModelClass) {
                return $resourceClass::table();
            }
        }

        // 3. Auto-detect columns from first record's attributes
        $firstRecord = $relation->first();
        if ($firstRecord !== null) {
            $columns = [];
            foreach (array_keys($firstRecord->getAttributes()) as $attr) {
                $columns[] = TextColumn::make($attr)->label($attr)->width(15);
            }

            return $columns;
        }

        return [];
    }
}
