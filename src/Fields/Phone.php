<?php

namespace TwoWee\Laravel\Fields;

class Phone extends Field
{
    public function fieldType(): string
    {
        return 'Phone';
    }
}
