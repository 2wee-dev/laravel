<?php

namespace TwoWee\Laravel\Fields;

use TwoWee\Laravel\Enums\Color;

class Boolean extends Field
{
    protected ?string $trueLabel = null;

    protected ?string $falseLabel = null;

    protected ?string $trueColor = null;

    protected ?string $falseColor = null;

    public function fieldType(): string
    {
        return 'Boolean';
    }

    public function trueLabel(string $label): static
    {
        $this->trueLabel = $label;

        return $this;
    }

    public function falseLabel(string $label): static
    {
        $this->falseLabel = $label;

        return $this;
    }

    public function trueColor(Color $color): static
    {
        $this->trueColor = $color->value;

        return $this;
    }

    public function falseColor(Color $color): static
    {
        $this->falseColor = $color->value;

        return $this;
    }

    public function parseValue(string $value): mixed
    {
        return $value === 'true' || $value === '1';
    }

    public function toArray(mixed $value = null, mixed $model = null): array
    {
        if ($value !== null) {
            $value = $value ? 'true' : 'false';
        }

        $result = parent::toArray($value, $model);

        if ($this->trueLabel !== null) {
            $result['true_label'] = $this->trueLabel;
        }

        if ($this->falseLabel !== null) {
            $result['false_label'] = $this->falseLabel;
        }

        if ($this->trueColor !== null) {
            $result['true_color'] = $this->trueColor;
        }

        if ($this->falseColor !== null) {
            $result['false_color'] = $this->falseColor;
        }

        return $result;
    }
}
