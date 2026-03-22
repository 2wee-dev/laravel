<?php

namespace TwoWee\Laravel\Columns;

class OptionColumn extends Column
{
    protected function columnType(): string
    {
        return 'Option';
    }
}
