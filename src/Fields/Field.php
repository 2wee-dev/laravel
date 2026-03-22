<?php

namespace TwoWee\Laravel\Fields;

abstract class Field
{
    use Concerns\HasLookup, Concerns\HasValidation, Concerns\HasAggregate, Concerns\HasRelationship;

    protected string $name;

    protected ?string $label = null;

    protected ?int $width = null;

    protected mixed $default = null;

    protected ?string $placeholder = null;

    protected bool $isRequired = false;

    protected ?int $maxLength = null;

    protected ?int $minLength = null;

    protected bool $isDisabled = false;

    protected bool $disableOnCreate = false;

    protected bool $disableOnUpdate = false;

    protected bool $isHidden = false;

    protected bool $quickEntry = true;

    protected bool $focus = false;

    protected bool $showZero = false;

    protected ?string $inputMask = null;

    protected ?string $patternRule = null;

    protected ?string $fieldColor = null;

    protected bool $isBold = false;

    protected ?\Closure $resolveCallback = null;

    protected ?\Closure $fillCallback = null;

    abstract public function fieldType(): string;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function width(int $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function required(): static
    {
        $this->isRequired = true;

        return $this;
    }

    public function maxLength(int $maxLength): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    public function minLength(int $minLength): static
    {
        $this->minLength = $minLength;

        return $this;
    }

    public function disabled(): static
    {
        $this->isDisabled = true;

        return $this;
    }

    /**
     * Disable this field when editing an existing record.
     * The field still renders but becomes read-only after first save.
     */
    public function disableOnUpdate(): static
    {
        $this->disableOnUpdate = true;

        return $this;
    }

    /**
     * Disable this field when creating a new record.
     * The field still renders but becomes editable only after first save.
     */
    public function disableOnCreate(): static
    {
        $this->disableOnCreate = true;

        return $this;
    }

    public function hidden(): static
    {
        $this->isHidden = true;

        return $this;
    }

    /**
     * Exclude this field from the Enter quick-entry path.
     * Tab still visits all fields. Enter skips fields with quickEntry(false).
     */
    public function quickEntry(bool $enabled = true): static
    {
        $this->quickEntry = $enabled;

        return $this;
    }

    /**
     * Set initial cursor focus to this field when the card opens.
     * Only one field per form should have focus.
     */
    public function focus(): static
    {
        $this->focus = true;

        return $this;
    }

    /**
     * Always display zero values instead of blank.
     * By default, numeric fields show blank when zero (Navision convention).
     * Use on fields where zero is meaningful (prices, balances).
     */
    public function showZero(): static
    {
        $this->showZero = true;

        return $this;
    }

    public function inputMask(string $mask): static
    {
        $this->inputMask = $mask;

        return $this;
    }

    public function uppercase(): static
    {
        return $this->inputMask('uppercase');
    }

    public function lowercase(): static
    {
        return $this->inputMask('lowercase');
    }

    public function digitsOnly(): static
    {
        return $this->inputMask('digits_only');
    }

    /**
     * Add a regex pattern for both client-side and server-side validation.
     */
    public function pattern(string $pattern): static
    {
        $this->patternRule = $pattern;

        return $this;
    }

    /**
     * Set the field text color.
     */
    public function color(\TwoWee\Laravel\Enums\Color $color): static
    {
        $this->fieldColor = $color->value;

        return $this;
    }

    /**
     * Make the field text bold.
     */
    public function bold(): static
    {
        $this->isBold = true;

        return $this;
    }

    /**
     * Transform the database value before sending it to the client.
     * The closure receives ($value, $model) and should return the display value.
     *
     *   ->resolveUsing(fn ($value, $model) => $value / 100)
     */
    public function resolveUsing(\Closure $callback): static
    {
        $this->resolveCallback = $callback;

        return $this;
    }

    /**
     * Transform the client value before writing it to the database.
     * The closure receives ($value, $model) and should return the storage value.
     *
     *   ->fillUsing(fn ($value, $model) => $value * 100)
     */
    public function fillUsing(\Closure $callback): static
    {
        $this->fillCallback = $callback;

        return $this;
    }

    public function hasFillCallback(): bool
    {
        return $this->fillCallback !== null;
    }

    public function applyFill(mixed $value, mixed $model = null): mixed
    {
        return ($this->fillCallback)($value, $model);
    }

    /**
     * Parse a raw string value from the client into a database-ready value.
     * Override in subclasses for type-specific parsing.
     */
    public function parseValue(string $value): mixed
    {
        return $value;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isHidden(): bool
    {
        return $this->isHidden;
    }

    /**
     * Whether this field is effectively disabled for the given context.
     * Pass null for create, a model for update.
     */
    public function isDisabledFor(mixed $model = null): bool
    {
        if ($this->isDisabled) {
            return true;
        }

        if ($model !== null && $this->disableOnUpdate) {
            return true;
        }

        if ($model === null && $this->disableOnCreate) {
            return true;
        }

        return false;
    }

    public function getInputMask(): ?string
    {
        return $this->inputMask;
    }

    protected function buildValidation(): array
    {
        $validation = [];

        if ($this->isRequired) {
            $validation['required'] = true;
        }

        if ($this->maxLength !== null) {
            $validation['max_length'] = $this->maxLength;
        }

        if ($this->minLength !== null) {
            $validation['min_length'] = $this->minLength;
        }

        if ($this->inputMask !== null) {
            $validation['input_mask'] = $this->inputMask;
        }

        if ($this->patternRule !== null) {
            $validation['pattern'] = $this->patternRule;
        }

        return $validation;
    }

    protected function typeSpecificValidation(): array
    {
        return [];
    }

    public function toArray(mixed $value = null, mixed $model = null): array
    {
        // Compute aggregate value from relationship
        if ($this->aggregateTarget !== null && $model !== null) {
            $relationName = $this->resolveAggregateRelation($model);
            if ($relationName !== null && method_exists($model, $relationName)) {
                $relation = $model->{$relationName}();
                $value = match ($this->aggregateFunction) {
                    'count' => $relation->count(),
                    'sum' => $relation->sum($this->aggregateColumn),
                    'avg' => $relation->avg($this->aggregateColumn),
                    'min' => $relation->min($this->aggregateColumn),
                    'max' => $relation->max($this->aggregateColumn),
                    default => $value,
                };
            }
        }

        // Apply resolveUsing callback to transform DB value → display value
        if ($this->resolveCallback !== null) {
            $value = ($this->resolveCallback)($value, $model);
        }

        $stringValue = $value !== null ? static::castToString($value) : static::castToString($this->default ?? '');

        if (! $this->showZero && $stringValue !== '' && is_numeric($stringValue) && (float) $stringValue === 0.0) {
            $stringValue = '';
        }

        // Resolve effective disabled state based on context
        $disabled = $this->isDisabled;
        if (! $disabled && $model !== null && $this->disableOnUpdate) {
            $disabled = true;
        }
        if (! $disabled && $model === null && $this->disableOnCreate) {
            $disabled = true;
        }

        $result = [
            'id' => $this->name,
            'label' => $this->label ?? $this->name,
            'type' => $this->fieldType(),
            'value' => $stringValue,
            'editable' => ! $disabled,
        ];

        if ($this->width !== null) {
            $result['width'] = $this->width;
        }

        if ($this->placeholder !== null) {
            $result['placeholder'] = $this->placeholder;
        }

        if (! $this->quickEntry) {
            $result['quick_entry'] = false;
        }

        if ($this->focus) {
            $result['focus'] = true;
        }

        $validation = array_merge($this->buildValidation(), $this->typeSpecificValidation());
        if ($validation !== []) {
            $result['validation'] = $validation;
        }

        if ($this->lookupEndpoint !== null) {
            // Prefix endpoint with URL base if it's not already an absolute path
            $endpoint = $this->lookupEndpoint;
            if (! str_starts_with($endpoint, '/')) {
                $endpoint = static::buildLookupUrl($endpoint);
            }

            $lookup = [
                'endpoint' => $endpoint,
            ];

            if ($this->lookupDisplayField !== null) {
                $lookup['display_field'] = $this->lookupDisplayField;
            }

            if ($this->lookupValidate || $this->blurValidate) {
                $lookup['validate'] = static::buildValidateUrl($this->lookupEndpoint);
            }

            if ($this->lookupDisplay !== null) {
                $lookup['display'] = $this->lookupDisplay;
            }

            if (! empty($this->lookupContext)) {
                $lookup['context'] = $this->lookupContext;
            }

            $result['lookup'] = $lookup;
        } elseif ($this->drillDownEndpoint !== null) {
            // Drill-down: read-only navigation via lookup.endpoint
            $result['lookup'] = [
                'endpoint' => $this->resolveDrillDown($model),
            ];
        } elseif ($this->blurValidate) {
            // No lookup, but blur validation enabled — emit validate-only lookup
            $result['lookup'] = [
                'endpoint' => $this->name,
                'validate' => static::buildValidateUrl($this->name),
            ];
        }

        if ($this->fieldColor !== null) {
            $result['color'] = $this->fieldColor;
        }

        if ($this->isBold) {
            $result['bold'] = true;
        }

        return $result;
    }

    protected static function castToString(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }
}
