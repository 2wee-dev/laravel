<?php

namespace TwoWee\Laravel\Support;

class FieldHelpers
{
    /**
     * Normalize autofill array: simple strings become key => key.
     *
     *   ['description']                → ['description' => 'description']
     *   ['name' => 'customer_name']    → ['name' => 'customer_name']
     */
    public static function normalizeAutofill(array $map): array
    {
        $normalized = [];

        foreach ($map as $key => $value) {
            if (is_int($key)) {
                $normalized[$value] = $value;
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Convert an options array to OptionPair[] format.
     *
     *   ['Item', 'Resource']           → [['value' => 'Item', 'label' => 'Item'], ...]
     *   ['item' => 'Item']             → [['value' => 'item', 'label' => 'Item']]
     */
    public static function buildOptionPairs(array $options): array
    {
        $pairs = [];

        foreach ($options as $key => $value) {
            if (is_int($key)) {
                $pairs[] = ['value' => $value, 'label' => $value];
            } else {
                $pairs[] = ['value' => $key, 'label' => $value];
            }
        }

        return $pairs;
    }

    /**
     * Format a collection of records into row data using column definitions.
     *
     * @param  iterable  $records  Eloquent models or objects
     * @param  array  $columns  Column instances with getName() and formatValue()
     * @return array  Array of ['index' => int, 'values' => string[]]
     */
    public static function formatRows(iterable $records, array $columns): array
    {
        $rows = [];
        $index = 0;

        foreach ($records as $record) {
            $values = [];
            foreach ($columns as $column) {
                $values[] = $column->formatValue($record->{$column->getName()} ?? null);
            }
            $rows[] = [
                'index' => $index++,
                'values' => $values,
            ];
        }

        return $rows;
    }
}
