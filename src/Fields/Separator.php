<?php

namespace TwoWee\Laravel\Fields;

class Separator extends Field
{
    public function __construct()
    {
        parent::__construct('separator');

        $this->isDisabled = true;
    }

    public static function make(string $name = 'separator'): static
    {
        return new static();
    }

    public function fieldType(): string
    {
        return 'Separator';
    }

    public function toArray(mixed $value = null, mixed $model = null): array
    {
        return [
            'id' => $this->name,
            'label' => '',
            'type' => 'Separator',
            'value' => '',
        ];
    }
}
