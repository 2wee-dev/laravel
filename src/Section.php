<?php

namespace TwoWee\Laravel;

use Illuminate\Support\Str;

class Section
{
    protected string $label;

    protected int $column = 0;

    protected int $rowGroup = 0;

    /** @var \TwoWee\Laravel\Fields\Field[] */
    protected array $fields = [];

    public function __construct(string $label)
    {
        $this->label = $label;
    }

    public static function make(string $label): static
    {
        return new static($label);
    }

    public function column(int $column): static
    {
        $this->column = $column;

        return $this;
    }

    public function rowGroup(int $rowGroup): static
    {
        $this->rowGroup = $rowGroup;

        return $this;
    }

    public function left(): static
    {
        $this->column = 0;

        return $this;
    }

    public function right(): static
    {
        $this->column = 1;

        return $this;
    }

    public function fullWidth(): static
    {
        return $this->left();
    }

    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getId(): string
    {
        return Str::snake(Str::ascii($this->label));
    }

    public function toArray(mixed $model = null): array
    {
        $fields = [];

        foreach ($this->fields as $field) {
            if ($field->isHidden()) {
                continue;
            }

            $value = null;
            if ($model !== null) {
                $value = $model->{$field->getName()} ?? null;
            }

            $fields[] = $field->toArray($value, $model);
        }

        return [
            'id' => $this->getId(),
            'label' => $this->label,
            'column' => $this->column,
            'row_group' => $this->rowGroup,
            'fields' => $fields,
        ];
    }
}
