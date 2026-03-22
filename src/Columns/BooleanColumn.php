<?php

namespace TwoWee\Laravel\Columns;

class BooleanColumn extends Column
{
    protected function columnType(): string
    {
        return 'Boolean';
    }

    public function formatValue(mixed $value): string
    {
        return $value ? 'true' : 'false';
    }
}
