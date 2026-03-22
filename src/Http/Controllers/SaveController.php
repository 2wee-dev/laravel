<?php

namespace TwoWee\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use TwoWee\Laravel\TwoWee;

class SaveController extends Controller
{
    use \TwoWee\Laravel\Http\Concerns\AuthorizesResources;

    public function __construct(
        protected TwoWee $twoWee,
    ) {}

    public function store(Request $request, string $resource): JsonResponse
    {
        $resourceClass = $this->twoWee->getResource($resource);

        if ($resourceClass === null) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        if ($denied = $this->authorize($resourceClass, 'create')) {
            return $denied;
        }

        $changes = $this->normalizeChanges($resourceClass, $request->input('changes', []));
        $lines = $request->input('lines');

        // Validate header fields (all rules on store — new record)
        $rules = $resourceClass::collectRules(null);
        if (! empty($rules)) {
            $validator = Validator::make($changes, $rules);

            if ($validator->fails()) {
                return $this->validationErrorResponse($resourceClass, null, $validator);
            }
        }

        // Validate line rows
        if ($lines !== null && ! empty($lines)) {
            $lineError = $this->validateLines($resourceClass, $lines);
            if ($lineError !== null) {
                return $this->lineValidationErrorResponse($resourceClass, null, $lineError);
            }
        }

        $changes = $resourceClass::beforeSave($changes);

        $modelClass = $resourceClass::getModel();
        $model = new $modelClass();
        $model->fill($changes);
        $model->save();

        // Delegate to save action if declared, otherwise default sync
        $saveAction = $resourceClass::getSaveAction();
        if ($saveAction !== null) {
            resolve($saveAction)->handle($model, $changes, $lines);
        } else {
            if ($lines !== null && ! empty($lines)) {
                static::saveLinesData($resourceClass, $model, $lines);
            }
            $resourceClass::afterSave($model);
        }

        $resourceClass::afterCreate($model);

        $model->refresh();

        return response()->json(
            $resourceClass::toCardJson($model, 'Created.')
        );
    }

    public function update(Request $request, string $resource, string $id): JsonResponse
    {
        $resourceClass = $this->twoWee->getResource($resource);

        if ($resourceClass === null) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        $model = $resourceClass::findRecord($id);

        if ($model === null) {
            return response()->json([
                'status' => 'error',
                'errors' => [
                    ['field_id' => null, 'message' => 'Record not found.'],
                ],
            ], 404);
        }

        if ($denied = $this->authorize($resourceClass, 'update', $model)) {
            return $denied;
        }

        $changes = $this->normalizeChanges($resourceClass, $request->input('changes', []), $model);
        $lines = $request->input('lines');

        // Validate header fields — only rules for fields present in the changeset
        $allRules = $resourceClass::collectRules($model);
        $rules = array_intersect_key($allRules, $changes);
        if (! empty($rules)) {
            $validator = Validator::make($changes, $rules);

            if ($validator->fails()) {
                return $this->validationErrorResponse($resourceClass, $model, $validator);
            }
        }

        // Validate line rows
        if ($lines !== null) {
            $lineError = $this->validateLines($resourceClass, $lines);
            if ($lineError !== null) {
                return $this->lineValidationErrorResponse($resourceClass, $model, $lineError);
            }
        }

        $changes = $resourceClass::beforeSave($changes, $model);

        if (! empty($changes)) {
            $model->fill($changes);
            $model->save();
        }

        // Delegate to save action if declared, otherwise default sync
        $saveAction = $resourceClass::getSaveAction();
        if ($saveAction !== null) {
            resolve($saveAction)->handle($model, $changes, $lines);
        } else {
            if ($lines !== null) {
                static::saveLinesData($resourceClass, $model, $lines);
            }
            $resourceClass::afterSave($model);
        }

        $resourceClass::afterUpdate($model);

        $model->refresh();

        return response()->json(
            $resourceClass::toCardJson($model, 'Saved.')
        );
    }

    public function destroy(Request $request, string $resource, string $id): JsonResponse
    {
        $resourceClass = $this->twoWee->getResource($resource);

        if ($resourceClass === null) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        $model = $resourceClass::findRecord($id);

        if ($model === null) {
            return response()->json(['error' => 'Record not found'], 404);
        }

        if ($denied = $this->authorize($resourceClass, 'delete', $model)) {
            return $denied;
        }

        // Delegate to delete action if declared, otherwise default delete
        $deleteAction = $resourceClass::getDeleteAction();
        if ($deleteAction !== null) {
            resolve($deleteAction)->handle($model);
        } else {
            $model->delete();
        }

        $resourceClass::afterDelete($model);

        $modelClass = $resourceClass::getModel();
        $query = $modelClass::query();
        $maxRows = $resourceClass::maxRows();
        if ($maxRows !== null) {
            $query->limit($maxRows);
        }
        $rows = $query->get();

        return response()->json(
            $resourceClass::toListJson($rows, $rows->count(), 'Deleted.')
        );
    }

    protected function normalizeChanges(string $resourceClass, array $changes, mixed $model = null): array
    {
        foreach ($resourceClass::form() as $section) {
            foreach ($section->getFields() as $field) {
                $name = $field->getName();

                // Strip disabled fields — they should never be saved
                if ($field->isDisabledFor($model) && isset($changes[$name])) {
                    unset($changes[$name]);

                    continue;
                }

                if (! isset($changes[$name]) || ! is_string($changes[$name])) {
                    // Apply fillUsing even for non-string values
                    if (isset($changes[$name]) && $field->hasFillCallback()) {
                        $changes[$name] = $field->applyFill($changes[$name], $model);
                    }

                    continue;
                }

                // Input mask normalization (uppercase/lowercase)
                $changes[$name] = match ($field->getInputMask()) {
                    'uppercase' => strtoupper($changes[$name]),
                    'lowercase' => strtolower($changes[$name]),
                    default => $changes[$name],
                };

                // Type-specific parsing (decimal locale, integer)
                $parsed = $field->parseValue($changes[$name]);
                if ($parsed !== $changes[$name]) {
                    $changes[$name] = $parsed;
                }

                // Apply fillUsing callback
                if ($field->hasFillCallback()) {
                    $changes[$name] = $field->applyFill($changes[$name], $model);
                }
            }
        }

        return $changes;
    }

    protected function validationErrorResponse(string $resourceClass, $model, $validator): JsonResponse
    {
        $firstError = $validator->errors()->first();
        $status = 'Error: ' . $firstError;

        if ($model !== null) {
            return response()->json(
                $resourceClass::toCardJson($model, $status)
            );
        }

        return response()->json(
            $resourceClass::toCreateJson($status)
        );
    }

    protected function validateLines(string $resourceClass, array $lines): ?string
    {
        $lineRules = $resourceClass::lineRules();
        if (empty($lineRules)) {
            return null;
        }

        $columns = $resourceClass::lineColumns();
        $columnNames = array_map(fn ($col) => $col->getName(), $columns);

        foreach ($lines as $rowIndex => $lineValues) {
            // Skip empty rows
            $hasData = false;
            foreach ($lineValues as $v) {
                if ($v !== '' && $v !== null) {
                    $hasData = true;
                    break;
                }
            }
            if (! $hasData) {
                continue;
            }

            // Map positional values to column names
            $rowData = [];
            foreach ($columnNames as $i => $name) {
                $rowData[$name] = $lineValues[$i] ?? '';
            }

            $validator = Validator::make($rowData, $lineRules);
            if ($validator->fails()) {
                $lineNum = $rowIndex + 1;

                return 'Line ' . $lineNum . ': ' . $validator->errors()->first();
            }
        }

        return null;
    }

    protected function lineValidationErrorResponse(string $resourceClass, $model, string $error): JsonResponse
    {
        $status = 'Error: ' . $error;

        if ($model !== null) {
            return response()->json(
                $resourceClass::toCardJson($model, $status)
            );
        }

        return response()->json(
            $resourceClass::toCreateJson($status)
        );
    }

    protected static function saveLinesData(string $resourceClass, $model, array $lines): void
    {
        $relation = $resourceClass::linesRelation();
        if ($relation === null || ! method_exists($model, $relation)) {
            return;
        }

        $columns = $resourceClass::lineColumns();

        // Delete existing lines and recreate
        $model->{$relation}()->delete();

        foreach ($lines as $lineValues) {
            // Skip empty rows (all values empty)
            $hasData = false;
            foreach ($lineValues as $v) {
                if ($v !== '' && $v !== null) {
                    $hasData = true;
                    break;
                }
            }

            if (! $hasData) {
                continue;
            }

            $lineData = [];
            foreach ($columns as $i => $column) {
                if (isset($lineValues[$i])) {
                    // Parse value using column type (e.g. decimal locale → float)
                    $lineData[$column->getName()] = $column->parseValue($lineValues[$i]);
                }
            }

            $model->{$relation}()->create($lineData);
        }
    }
}
