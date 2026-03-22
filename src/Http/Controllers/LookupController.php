<?php

namespace TwoWee\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use TwoWee\Laravel\TwoWee;

class LookupController extends Controller
{
    public function __construct(
        protected TwoWee $twoWee,
    ) {}

    public function search(Request $request, string $resource, string $fieldId): JsonResponse
    {
        return $this->resolve($request, $resource . ':' . $fieldId, $fieldId);
    }

    public function searchGlobal(Request $request, string $fieldId): JsonResponse
    {
        return $this->resolve($request, $fieldId, $fieldId);
    }

    protected function resolve(Request $request, string $lookupKey, string $fieldId): JsonResponse
    {
        $definition = $this->twoWee->getLookup($lookupKey);

        if ($definition === null) {
            return response()->json(['error' => 'Lookup not found'], 404);
        }

        $searchTerm = $request->query('query');
        $selected = $request->query('selected');
        $context = collect($request->query())->except(['query', 'selected'])->all();

        $records = $definition->resolve($searchTerm, $context, $selected);

        $columns = $definition->getColumns();
        $columnDefs = array_map(fn ($col) => $col->toArray(), $columns);

        $rowData = \TwoWee\Laravel\Support\FieldHelpers::formatRows($records, $columns);

        $user = Auth::guard('twowee')->user();

        $lines = [
            'columns' => $columnDefs,
            'rows' => $rowData,
            'row_count' => count($rowData),
            'selectable' => true,
            'value_column' => $definition->getValueColumn(),
            'autofill' => $definition->getAutofill() ?: (object) [],
        ];

        $onDrill = $definition->getOnDrill();
        if ($onDrill !== null) {
            $lines['on_drill'] = $onDrill;
        }

        $status = null;
        if (count($rowData) === 0) {
            $status = ($searchTerm !== null && $searchTerm !== '')
                ? 'No records match "' . $searchTerm . '".'
                : 'No records found.';
        }

        return response()->json([
            'layout' => 'List',
            'title' => $definition->getTitle() ?? $fieldId,
            'user_display_name' => $user?->name ?? null,
            'sections' => [],
            'lines' => $lines,
            'status' => $status,
        ]);
    }
}
