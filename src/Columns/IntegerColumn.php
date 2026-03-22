<?php

namespace TwoWee\Laravel\Columns;

class IntegerColumn extends Column
{
    protected function columnType(): string
    {
        return 'Integer';
    }

    public function parseValue(string $value): mixed
    {
        if ($value === '') {
            return null;
        }

        return (int) $value;
    }

    public function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return $this->applyZeroDisplay((string) (int) $value);
    }
}
