<?php

namespace TwoWee\Laravel\Fields;

class Date extends Field
{
    protected string $format = 'DD-MM-YY';

    public function fieldType(): string
    {
        return 'Date';
    }

    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    protected function typeSpecificValidation(): array
    {
        return ['format' => $this->format];
    }
}
