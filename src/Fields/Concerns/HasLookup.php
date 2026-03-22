<?php

namespace TwoWee\Laravel\Fields\Concerns;

trait HasLookup
{
    protected ?string $lookupEndpoint = null;

    protected ?string $lookupDisplayField = null;

    protected bool $lookupValidate = false;

    protected ?string $lookupDisplay = null;

    protected ?string $lookupTitle = null;

    protected array $lookupContext = [];

    protected ?string $lookupModelClass = null;

    protected ?string $lookupDefinitionClass = null;

    protected ?string $lookupValueColumn = null;

    protected array $lookupAutofill = [];

    /** @var string|null Resource slug for scoped lookup URLs */
    protected static ?string $currentResourceSlug = null;

    public static function setResourceSlug(?string $slug): void
    {
        static::$currentResourceSlug = $slug;
    }

    /**
     * Configure a lookup for this field.
     *
     * Accepts three forms:
     *   ->lookup(CountryLookup::class)               // Lookup class with definition()
     *   ->lookup(PostCode::class, valueColumn: 'code') // Model class — auto-wired
     *   ->lookup('post_code', 'name', validate: true)  // Manual endpoint
     */
    public function lookup(string $endpoint, ?string $displayField = null, bool $validate = false, ?string $display = null, ?string $valueColumn = null): static
    {
        // Detect lookup definition class (has static definition() method)
        if (class_exists($endpoint) && method_exists($endpoint, 'definition')) {
            $this->lookupDefinitionClass = $endpoint;
            $this->lookupEndpoint = $this->name;
            $this->lookupValidate = true;
            $this->blurValidate = true;
            $this->lookupDisplay = $display;

            return $this;
        }

        // Detect model class: exists as a class and is an Eloquent model
        if (class_exists($endpoint) && is_subclass_of($endpoint, \Illuminate\Database\Eloquent\Model::class)) {
            $this->lookupModelClass = $endpoint;
            $this->lookupValueColumn = $valueColumn;
            $this->lookupEndpoint = $this->name;
            $this->lookupValidate = true;
            $this->blurValidate = true;
            $this->lookupDisplay = $display;

            return $this;
        }

        $this->lookupEndpoint = $endpoint;
        $this->lookupDisplayField = $displayField;
        $this->lookupValidate = $validate;
        $this->lookupDisplay = $display;

        return $this;
    }

    /**
     * Set a custom title for the lookup window (modal or full-screen).
     */
    public function lookupTitle(string $title): static
    {
        $this->lookupTitle = $title;

        return $this;
    }

    /**
     * Set the lookup to display as a modal overlay instead of full-screen.
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
     * Set autofill mapping for inline lookups.
     * Maps lookup column IDs to card field IDs.
     *
     * Accepts two forms:
     *   ->autofill(['description', 'unit_price'])                    // same name on both sides
     *   ->autofill(['name' => 'customer_name', 'city' => 'city'])    // different names
     *   ->autofill(['description', 'name' => 'customer_name'])       // mixed
     */
    public function autofill(array $map): static
    {
        $this->lookupAutofill = static::normalizeAutofill($map);

        return $this;
    }

    protected static function normalizeAutofill(array $map): array
    {
        return \TwoWee\Laravel\Support\FieldHelpers::normalizeAutofill($map);
    }

    /**
     * Filter the lookup by other field values on the card.
     * The client sends these as query parameters.
     *
     * Examples:
     *   ->filterFrom('country_code')                    // ?country_code=FO
     *   ->filterFrom('country_code', 'region_code')     // ?country_code=FO&region_code=ST
     *   ->filterFrom(['field' => 'account_type', 'param' => 'type'])  // ?type=Customer
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

    public function hasInlineLookup(): bool
    {
        return $this->lookupModelClass !== null || $this->lookupDefinitionClass !== null;
    }

    public function getLookupTitle(): ?string
    {
        return $this->lookupTitle;
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

    /**
     * Build the full validate endpoint URL for a field.
     */
    protected static function buildValidateUrl(string $fieldId): string
    {
        $slug = static::$currentResourceSlug;

        return $slug
            ? static::buildPrefixedUrl('/validate/' . $slug . '/' . $fieldId)
            : static::buildPrefixedUrl('/validate/' . $fieldId);
    }

    protected static function buildLookupUrl(string $fieldId): string
    {
        $slug = static::$currentResourceSlug;

        return $slug
            ? static::buildPrefixedUrl('/lookup/' . $slug . '/' . $fieldId)
            : static::buildPrefixedUrl('/lookup/' . $fieldId);
    }

    protected static function buildPrefixedUrl(string $path): string
    {
        if (function_exists('config') && app()->bound('config')) {
            return \TwoWee\Laravel\TwoWee::baseUrl() . $path;
        }

        return $path;
    }
}
