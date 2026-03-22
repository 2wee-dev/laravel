<?php

namespace TwoWee\Laravel\Columns;

class TextColumn extends Column
{
    protected function columnType(): string
    {
        return 'Text';
    }
}
