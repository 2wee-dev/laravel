<?php

namespace TwoWee\Laravel\Actions;

class ActionField
{
    protected string $id;

    protected string $label;

    protected string $type = 'Text';

    protected string $value = '';

    protected bool $isRequired = false;

    protected array $options = [];

    protected ?string $placeholder = null;

    protected array $validation = [];

    protected ?int $rows = null;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public static function make(string $id): static
    {
        return new static($id);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function text(): static
    {
        $this->type = 'Text';

        return $this;
    }

    public function email(): static
    {
        $this->type = 'Email';

        return $this;
    }

    public function phone(): static
    {
        $this->type = 'Phone';

        return $this;
    }

    public function url(): static
    {
        $this->type = 'URL';

        return $this;
    }

    public function decimal(): static
    {
        $this->type = 'Decimal';

        return $this;
    }

    public function integer(): static
    {
        $this->type = 'Integer';

        return $this;
    }

    public function date(): static
    {
        $this->type = 'Date';

        return $this;
    }

    public function time(): static
    {
        $this->type = 'Time';

        return $this;
    }

    public function boolean(): static
    {
        $this->type = 'Boolean';

        return $this;
    }

    public function password(): static
    {
        $this->type = 'Password';

        return $this;
    }

    public function option(array $options): static
    {
        $this->type = 'Option';
        $this->options = $options;

        return $this;
    }

    public function value(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function required(): static
    {
        $this->isRequired = true;

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * Set the field as a TextArea with optional row count.
     */
    public function textarea(int $rows = 4): static
    {
        $this->type = 'TextArea';
        $this->rows = $rows;

        return $this;
    }

    public function rows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    public function validation(array $validation): static
    {
        $this->validation = $validation;

        return $this;
    }

    public function toArray(): array
    {
        $result = [
            'id' => $this->id,
            'label' => $this->label,
            'type' => $this->type,
            'value' => $this->value,
            'required' => $this->isRequired,
        ];

        if (! empty($this->options)) {
            $result['options'] = $this->options;
        }

        if ($this->placeholder !== null) {
            $result['placeholder'] = $this->placeholder;
        }

        if ($this->rows !== null) {
            $result['rows'] = $this->rows;
        }

        if (! empty($this->validation)) {
            $result['validation'] = $this->validation;
        }

        return $result;
    }
}
