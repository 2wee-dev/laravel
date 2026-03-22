<?php

namespace TwoWee\Laravel\Fields;

class Email extends Field
{
    public function fieldType(): string
    {
        return 'Email';
    }
}
