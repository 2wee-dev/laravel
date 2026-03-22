<?php

namespace TwoWee\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use TwoWee\Laravel\TwoWee;

class ValidateController extends Controller
{
    public function __construct(
        protected TwoWee $twoWee,
    ) {}

    public function validate(Request $request, string $resource, string $fieldId, string $value): JsonResponse
    {
        return $this->resolve($request, $resource . ':' . $fieldId, $fieldId, $value);
    }

    public function validateGlobal(Request $request, string $fieldId, string $value): JsonResponse
    {
        return $this->resolve($request, $fieldId, $fieldId, $value);
    }

    protected function resolve(Request $request, string $lookupKey, string $fieldId, string $value): JsonResponse
    {
        // Normalize value by input mask before any validation
        $value = $this->normalizeValue($fieldId, $value);

        // 1. Check for a lookup definition first (validates + returns autofill)
        $definition = $this->twoWee->getLookup($lookupKey);

        if ($definition !== null) {
            $context = $request->query();
            $result = $definition->validateValue($value, $context);

            return response()->json($result);
        }

        // 2. Fall back to field-level or resource-level rules
        $resourceClass = $this->twoWee->getBlurFieldResource($lookupKey)
            ?? $this->twoWee->getBlurFieldResource($fieldId);

        if ($resourceClass !== null) {
            $fieldRules = $resourceClass::getFieldRules($fieldId);

            if (! empty($fieldRules)) {
                $validator = Validator::make(
                    [$fieldId => $value],
                    [$fieldId => $fieldRules]
                );

                if ($validator->fails()) {
                    return response()->json([
                        'valid' => false,
                        'autofill' => (object) [],
                        'error' => $validator->errors()->first($fieldId),
                    ]);
                }

                return response()->json([
                    'valid' => true,
                    'autofill' => (object) [],
                    'error' => null,
                ]);
            }
        }

        return response()->json(['error' => 'No validation defined for field'], 404);
    }

    protected function normalizeValue(string $fieldId, string $value): string
    {
        foreach ($this->twoWee->getResources() as $resourceClass) {
            foreach ($resourceClass::form() as $section) {
                foreach ($section->getFields() as $field) {
                    if ($field->getName() === $fieldId) {
                        return match ($field->getInputMask()) {
                            'uppercase' => strtoupper($value),
                            'lowercase' => strtolower($value),
                            default => $value,
                        };
                    }
                }
            }
        }

        return $value;
    }
}
