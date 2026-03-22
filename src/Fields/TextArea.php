<?php

namespace TwoWee\Laravel\Fields;

class TextArea extends Field
{
    protected int $rows = 4;

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->quickEntry = false;
    }

    public function fieldType(): string
    {
        return 'TextArea';
    }

    public function rows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    public function toArray(mixed $value = null, mixed $model = null): array
    {
        $result = parent::toArray($value, $model);

        $result['rows'] = $this->rows;

        return $result;
    }
}
