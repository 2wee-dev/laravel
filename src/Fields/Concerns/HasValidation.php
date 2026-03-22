<?php

namespace TwoWee\Laravel\Fields\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

trait HasValidation
{
    /** @var array Laravel server-side validation rules */
    protected array $serverRules = [];

    /** @var array Rules applied only when creating */
    protected array $creationServerRules = [];

    /** @var array Rules applied only when updating */
    protected array $updateServerRules = [];

    protected bool $isNullable = false;

    protected ?string $uniqueTable = null;

    protected ?string $uniqueColumn = null;

    protected ?string $existsTable = null;

    protected ?string $existsColumn = null;

    protected ?string $enumClass = null;

    protected bool $blurValidate = false;

    /**
     * Add raw Laravel validation rules.
     * Accepts strings ('min:3|max:100'), arrays (['min:3', 'max:100']),
     * or Rule objects.
     */
    public function rules(array $rules): static
    {
        $this->serverRules = array_merge($this->serverRules, $rules);

        return $this;
    }

    /**
     * Add validation rules that only apply when creating a new record.
     */
    public function creationRules(array $rules): static
    {
        $this->creationServerRules = array_merge($this->creationServerRules, $rules);

        return $this;
    }

    /**
     * Add validation rules that only apply when updating an existing record.
     */
    public function updateRules(array $rules): static
    {
        $this->updateServerRules = array_merge($this->updateServerRules, $rules);

        return $this;
    }

    public function nullable(): static
    {
        $this->isNullable = true;

        return $this;
    }

    public function email(): static
    {
        $this->serverRules[] = 'email';

        return $this;
    }

    public function unique(string $table, ?string $column = null): static
    {
        $this->uniqueTable = $table;
        $this->uniqueColumn = $column ?? $this->name;

        return $this;
    }

    public function exists(string $table, ?string $column = null): static
    {
        $this->existsTable = $table;
        $this->existsColumn = $column ?? $this->name;

        return $this;
    }

    public function enum(string $enumClass): static
    {
        $this->enumClass = $enumClass;

        return $this;
    }

    public function in(array $values): static
    {
        $this->serverRules[] = Rule::in($values);

        return $this;
    }

    public function regex(string $pattern): static
    {
        $this->serverRules[] = 'regex:' . $pattern;

        return $this;
    }

    public function numeric(): static
    {
        $this->serverRules[] = 'numeric';

        return $this;
    }

    public function integer(): static
    {
        $this->serverRules[] = 'integer';

        return $this;
    }

    public function string(): static
    {
        $this->serverRules[] = 'string';

        return $this;
    }

    public function confirmed(): static
    {
        $this->serverRules[] = 'confirmed';

        return $this;
    }

    public function after(string $dateOrField): static
    {
        $this->serverRules[] = 'after:' . $dateOrField;

        return $this;
    }

    public function before(string $dateOrField): static
    {
        $this->serverRules[] = 'before:' . $dateOrField;

        return $this;
    }

    public function same(string $field): static
    {
        $this->serverRules[] = 'same:' . $field;

        return $this;
    }

    public function different(string $field): static
    {
        $this->serverRules[] = 'different:' . $field;

        return $this;
    }

    /**
     * Enable blur validation for this field. The client will call
     * GET /validate/{field_id}/{value} when the user tabs away.
     */
    public function blurValidate(): static
    {
        $this->blurValidate = true;

        return $this;
    }

    public function hasBlurValidate(): bool
    {
        return $this->blurValidate || $this->lookupValidate;
    }

    /**
     * Build the complete Laravel validation rules array for this field.
     * The model is passed for context-aware rules (e.g. unique ignoring current record).
     */
    public function getServerRules(?Model $model = null): array
    {
        $rules = [];

        if ($this->isNullable) {
            $rules[] = 'nullable';
        }

        if ($this->isRequired) {
            $rules[] = 'required';
        }

        if ($this->maxLength !== null) {
            $rules[] = 'max:' . $this->maxLength;
        }

        if ($this->minLength !== null) {
            $rules[] = 'min:' . $this->minLength;
        }

        if ($this->patternRule !== null) {
            $rules[] = 'regex:' . $this->patternRule;
        }

        // Merge explicit rules
        $rules = array_merge($rules, $this->serverRules);

        // Merge context-specific rules
        if ($model === null) {
            $rules = array_merge($rules, $this->creationServerRules);
        } else {
            $rules = array_merge($rules, $this->updateServerRules);
        }

        // Unique rule (with ignore for updates)
        if ($this->uniqueTable !== null) {
            $unique = Rule::unique($this->uniqueTable, $this->uniqueColumn);
            if ($model !== null) {
                $unique->ignore($model->getKey());
            }
            $rules[] = $unique;
        }

        // Exists rule
        if ($this->existsTable !== null) {
            $rules[] = Rule::exists($this->existsTable, $this->existsColumn);
        }

        // Enum rule
        if ($this->enumClass !== null) {
            $rules[] = Rule::enum($this->enumClass);
        }

        return $rules;
    }

    /**
     * Whether this field has any server-side validation rules.
     */
    public function hasServerRules(): bool
    {
        return $this->isRequired
            || $this->isNullable
            || $this->maxLength !== null
            || $this->minLength !== null
            || $this->patternRule !== null
            || ! empty($this->serverRules)
            || ! empty($this->creationServerRules)
            || ! empty($this->updateServerRules)
            || $this->uniqueTable !== null
            || $this->existsTable !== null
            || $this->enumClass !== null;
    }
}
