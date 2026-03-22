<?php

namespace TwoWee\Laravel\Fields;

class Decimal extends Field
{
    protected int $decimals = 2;

    protected ?float $min = null;

    protected ?float $max = null;

    public function fieldType(): string
    {
        return 'Decimal';
    }

    public function decimals(int $decimals): static
    {
        $this->decimals = $decimals;

        return $this;
    }

    public function min(float $min): static
    {
        $this->min = $min;

        return $this;
    }

    public function max(float $max): static
    {
        $this->max = $max;

        return $this;
    }

    protected function typeSpecificValidation(): array
    {
        $result = ['decimals' => $this->decimals];

        if ($this->min !== null) {
            $result['min'] = $this->min;
        }

        if ($this->max !== null) {
            $result['max'] = $this->max;
        }

        return $result;
    }

    public function parseValue(string $value): mixed
    {
        if ($value === '') {
            return null;
        }

        return (float) $value;
    }

    public function toArray(mixed $value = null, mixed $model = null): array
    {
        if ($value !== null && is_numeric($value)) {
            $value = number_format((float) $value, $this->decimals, '.', '');
        }

        return parent::toArray($value, $model);
    }
}
