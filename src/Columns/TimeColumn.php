<?php

namespace TwoWee\Laravel\Columns;

class TimeColumn extends Column
{
    protected function columnType(): string
    {
        return 'Time';
    }
}
