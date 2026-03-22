<?php

namespace TwoWee\Laravel\Columns;

abstract class Column
{
    protected string $name;

    protected ?string $label = null;

    protected int|string|null $width = null;

    protected string $align = 'left';

    protected bool $isEditable = false;

    protected bool $quickEntry = true;

    protected bool $showZero = false;

    protected array $options = [];

    protected ?string $lookupEndpoint = null;

    protected ?string $lookupValueColumn = null;

    protected array $lookupAutofill = [];

    protected ?string $lookupModelClass = null;

    protected ?string $lookupDefinitionClass = null;

    protected ?string $lookupDisplay = null;

    protected ?string $lookupTitle = null;

    protected array $lookupContext = [];

    protected ?string $formula = null;

    protected array $validation = [];

    protected ?\Closure $formatCallback = null;

    protected static ?string $currentResourceSlug = null;

    public static function setResourceSlug(?string $slug): void
    {
        static::$currentResourceSlug = $slug;
    }

    abstract protected function columnType(): string;

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

    public function width(int|string $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function align(string $align): static
    {
        $this->align = $align;

        return $this;
    }

    public function editable(): static
    {
        $this->isEditable = true;

        return $this;
    }

    /**
     * Exclude this column from the Enter quick-entry path.
     * Tab still visits all columns. Enter skips columns with quickEntry(false).
     */
    public function quickEntry(bool $enabled = true): static
    {
        $this->quickEntry = $enabled;

        return $this;
    }

    /**
     * Always display zero values instead of blank.
     * By default, numeric columns show blank when zero.
     */
    public function showZero(): static
    {
        $this->showZero = true;

        return $this;
    }

    /**
     * Set a client-side formula for live calculation.
     * References other column IDs by name. Supports +, -, *, /, parentheses.
     *
     *   ->formula('quantity * unit_price * (1 - line_discount_pct / 100)')
     */
    public function formula(string $expression): static
    {
        $this->formula = $expression;

        return $this;
    }

    /**
     * Set options for editable grid columns (like Option type).
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Configure lookup for editable grid columns.
     *
     * Accepts either a model class or a string endpoint:
     *   ->lookup(Item::class, valueColumn: 'no')
     *   ->lookup(CountryLookup::class)               // Lookup class with definition()
     *   ->lookup(Item::class, valueColumn: 'no')       // Model class — auto-wired
     *   ->lookup('items', valueColumn: 'no')            // Manual endpoint
     */
    public function lookup(string $endpoint, ?string $valueColumn = null): static
    {
        // Detect lookup definition class
        if (class_exists($endpoint) && method_exists($endpoint, 'definition')) {
            $this->lookupDefinitionClass = $endpoint;
            $this->lookupEndpoint = $this->name;

            return $this;
        }

        // Detect model class
        if (class_exists($endpoint) && is_subclass_of($endpoint, \Illuminate\Database\Eloquent\Model::class)) {
            $this->lookupModelClass = $endpoint;
            $this->lookupEndpoint = $this->name;
            $this->lookupValueColumn = $valueColumn;

            return $this;
        }

        $this->lookupEndpoint = $endpoint;
        $this->lookupValueColumn = $valueColumn;

        return $this;
    }

    /**
     * Set autofill mapping for this column's lookup.
     * When the user selects a row, these columns auto-fill in the same grid row.
     *
     *   ->autofill(['description', 'unit_price'])              // same name
     *   ->autofill(['name' => 'customer_name'])                // different name
     *   ->autofill(['description', 'name' => 'item_name'])     // mixed
     */
    public function autofill(array $map): static
    {
        $this->lookupAutofill = static::normalizeAutofill($map);

        return $this;
    }

    /**
     * Set the lookup to display as a modal overlay.
     * Optionally set a title for the modal window.
     */
    public function lookupTitle(string $title): static
    {
        $this->lookupTitle = $title;

        return $this;
    }

    /**
     * Set the lookup to display as a modal overlay.
     * Optionally set a title for the modal window.
     */
    public function modal(?string $title = null): static
    {
        $this->lookupDisplay = 'modal';

        if ($title !== null) {
            $this->lookupTitle = $title;
        }

        return $this;
    }

    /**
     * Filter the lookup by other column values in the same row.
     *
     *   ->context('line_type')
     *   ->context(['field' => 'line_type', 'param' => 'type'])
     */
    public function filterFrom(string|array ...$fields): static
    {
        foreach ($fields as $field) {
            if (is_string($field)) {
                $this->lookupContext[] = ['field' => $field];
            } else {
                $this->lookupContext[] = $field;
            }
        }

        return $this;
    }

    /**
     * Set validation rules for editable grid columns.
     */
    public function validation(array $rules): static
    {
        $this->validation = $rules;

        return $this;
    }

    /**
     * Set a custom formatting closure for display values.
     */
    public function formatUsing(\Closure $callback): static
    {
        $this->formatCallback = $callback;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    protected static function normalizeAutofill(array $map): array
    {
        return \TwoWee\Laravel\Support\FieldHelpers::normalizeAutofill($map);
    }

    public function getLookupTitle(): ?string
    {
        return $this->lookupTitle;
    }

    public function hasInlineLookup(): bool
    {
        return $this->lookupModelClass !== null || $this->lookupDefinitionClass !== null;
    }

    public function hasLookupDefinitionClass(): bool
    {
        return $this->lookupDefinitionClass !== null;
    }

    public function getLookupDefinitionClass(): ?string
    {
        return $this->lookupDefinitionClass;
    }

    public function getLookupModelClass(): ?string
    {
        return $this->lookupModelClass;
    }

    public function getLookupValueColumn(): ?string
    {
        return $this->lookupValueColumn;
    }

    public function getLookupAutofill(): array
    {
        return $this->lookupAutofill;
    }

    public function getLookupDisplay(): ?string
    {
        return $this->lookupDisplay;
    }

    public function toArray(): array
    {
        $result = [
            'id' => $this->name,
            'label' => $this->label ?? $this->name,
            'type' => $this->columnType(),
        ];

        if ($this->width !== null) {
            $result['width'] = $this->width;
        }

        $result['align'] = $this->align;

        if ($this->isEditable) {
            $result['editable'] = true;
        }

        if (! $this->quickEntry) {
            $result['quick_entry'] = false;
        }

        if ($this->formula !== null) {
            $result['formula'] = $this->formula;
        }

        if (! empty($this->options)) {
            $result['options'] = \TwoWee\Laravel\Support\FieldHelpers::buildOptionPairs($this->options);
        }

        if ($this->lookupEndpoint !== null) {
            $endpoint = $this->lookupEndpoint;

            // Prefix with URL base if not already absolute
            if (! str_starts_with($endpoint, '/')) {
                $base = function_exists('config') && app()->bound('config') ? \TwoWee\Laravel\TwoWee::baseUrl() : '';
                $slugSegment = static::$currentResourceSlug ? static::$currentResourceSlug . '/' : '';
                $endpoint = $base . '/lookup/' . $slugSegment . $endpoint;
            }

            $lookup = ['endpoint' => $endpoint];

            // Add validate URL
            $base = function_exists('config') && app()->bound('config') ? \TwoWee\Laravel\TwoWee::baseUrl() : '';
            $slugSegment = static::$currentResourceSlug ? static::$currentResourceSlug . '/' : '';
            $lookup['validate'] = $base . '/validate/' . $slugSegment . $this->lookupEndpoint;

            if ($this->lookupDisplay !== null) {
                $lookup['display'] = $this->lookupDisplay;
            }

            if (! empty($this->lookupContext)) {
                $lookup['context'] = $this->lookupContext;
            }

            $result['lookup'] = $lookup;
        }

        if (! empty($this->validation)) {
            $result['validation'] = $this->validation;
        }

        return $result;
    }

    /**
     * Parse a raw string value from the client into a database-ready value.
     * Override in subclasses for type-specific parsing (e.g. decimal locale).
     */
    public function parseValue(string $value): mixed
    {
        return $value;
    }

    public function formatValue(mixed $value): string
    {
        if ($this->formatCallback !== null) {
            $formatted = (string) ($this->formatCallback)($value);

            return $this->applyZeroDisplay($formatted);
        }

        if ($value === null) {
            return '';
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return $this->applyZeroDisplay((string) $value);
    }

    protected function applyZeroDisplay(string $value): string
    {
        if (! $this->showZero && $value !== '' && is_numeric($value) && (float) $value === 0.0) {
            return '';
        }

        return $value;
    }
}
