<?php

namespace TwoWee\Laravel\Columns;

class DateColumn extends Column
{
    protected function columnType(): string
    {
        return 'Date';
    }
}
