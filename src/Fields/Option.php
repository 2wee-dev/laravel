<?php

namespace TwoWee\Laravel\Fields;

class Option extends Field
{
    protected array $options = [];

    public function fieldType(): string
    {
        return 'Option';
    }

    /**
     * Set options. Accepts either:
     * - Simple array: ['Item', 'G/L Account', 'Resource']
     * - Key-value pairs: ['item' => 'Item', 'gl' => 'G/L Account']
     *
     * Both are converted to OptionPair[] format: [{"value": "...", "label": "..."}]
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    protected function buildOptionPairs(): array
    {
        return \TwoWee\Laravel\Support\FieldHelpers::buildOptionPairs($this->options);
    }

    public function toArray(mixed $value = null, mixed $model = null): array
    {
        $result = parent::toArray($value, $model);

        if ($this->options !== []) {
            $result['options'] = $this->buildOptionPairs();
        }

        return $result;
    }
}
