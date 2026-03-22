<?php

namespace TwoWee\Laravel\Fields;

class Integer extends Field
{
    protected ?int $min = null;

    protected ?int $max = null;

    public function fieldType(): string
    {
        return 'Integer';
    }

    public function min(int $min): static
    {
        $this->min = $min;

        return $this;
    }

    public function max(int $max): static
    {
        $this->max = $max;

        return $this;
    }

    public function parseValue(string $value): mixed
    {
        if ($value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function typeSpecificValidation(): array
    {
        $result = [];

        if ($this->min !== null) {
            $result['min'] = $this->min;
        }

        if ($this->max !== null) {
            $result['max'] = $this->max;
        }

        return $result;
    }
}
