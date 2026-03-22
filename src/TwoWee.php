<?php

namespace TwoWee\Laravel;

use Illuminate\Support\Str;
use TwoWee\Laravel\Columns\TextColumn;
use TwoWee\Laravel\Lookup\LookupDefinition;

class TwoWee
{
    /** @var array<string, class-string<Resource>> */
    protected array $resources = [];

    /** @var array<string, LookupDefinition> */
    protected array $lookups = [];

    /** @var array<string, class-string<Resource>> field_id → resource class for blur validation */
    protected array $blurFields = [];

    /** @var array<string, array{resource: string, relation: string, columns: array}> field_id → drilldown info */
    protected array $relationDrilldowns = [];

    public function register(string $resourceClass): void
    {
        $slug = $resourceClass::getSlug();
        $this->resources[$slug] = $resourceClass;

        // Register lookups from this resource (scoped by resource slug)
        foreach ($resourceClass::lookups() as $fieldId => $lookupDefinition) {
            $this->lookups[$slug . ':' . $fieldId] = $lookupDefinition;
            // Global fallback (first registration wins)
            if (! isset($this->lookups[$fieldId])) {
                $this->lookups[$fieldId] = $lookupDefinition;
            }
        }

        // Scan form fields for relationship() and blurValidate()
        foreach ($resourceClass::form() as $section) {
            foreach ($section->getFields() as $field) {
                $fieldName = $field->getName();
                $scopedKey = $slug . ':' . $fieldName;

                // Auto-generate LookupDefinition from inline lookup(ModelClass)
                if ($field->hasInlineLookup() && ! isset($this->lookups[$scopedKey])) {
                    $lookup = $this->buildInlineLookup($field);
                    if ($lookup !== null) {
                        $this->lookups[$scopedKey] = $lookup;
                        if (! isset($this->lookups[$fieldName])) {
                            $this->lookups[$fieldName] = $lookup;
                        }
                    }
                }

                // Auto-generate LookupDefinition from relationship()
                if ($field->hasRelationship() && ! isset($this->lookups[$scopedKey])) {
                    $lookup = $this->buildRelationshipLookup($resourceClass, $field);
                    if ($lookup !== null) {
                        $this->lookups[$scopedKey] = $lookup;
                        if (! isset($this->lookups[$fieldName])) {
                            $this->lookups[$fieldName] = $lookup;
                        }
                    }
                }

                // Register relationship drilldowns
                $drillTarget = $field->getDrillDownTarget();
                if ($drillTarget !== null) {
                    $this->relationDrilldowns[$scopedKey] = [
                        'resource' => $resourceClass,
                        'target' => $drillTarget,
                        'columns' => $field->getDrillDownColumns(),
                    ];
                    if (! isset($this->relationDrilldowns[$fieldName])) {
                        $this->relationDrilldowns[$fieldName] = $this->relationDrilldowns[$scopedKey];
                    }
                }

                // Register blur-validated fields (non-lookup fields)
                if ($field->hasBlurValidate() && ! isset($this->lookups[$scopedKey])) {
                    $this->blurFields[$scopedKey] = $resourceClass;
                    if (! isset($this->blurFields[$fieldName])) {
                        $this->blurFields[$fieldName] = $resourceClass;
                    }
                }
            }
        }

        // Scan lineColumns for inline lookups (HeaderLines/Grid)
        foreach ($resourceClass::lineColumns() as $column) {
            $columnName = $column->getName();
            $scopedKey = $slug . ':' . $columnName;

            if ($column->hasInlineLookup() && ! isset($this->lookups[$scopedKey])) {
                $lookup = $this->buildColumnLookup($column);
                if ($lookup !== null) {
                    $this->lookups[$scopedKey] = $lookup;
                    if (! isset($this->lookups[$columnName])) {
                        $this->lookups[$columnName] = $lookup;
                    }
                }
            }
        }
    }

    /**
     * Build a LookupDefinition from a column's inline lookup(ModelClass).
     */
    protected function buildColumnLookup($column): ?LookupDefinition
    {
        if ($column->hasLookupDefinitionClass()) {
            $lookup = $this->resolveLookupDefinitionClass($column);

            return $lookup;
        }

        return $this->buildLookupFromModel(
            $column->getLookupModelClass(),
            $column->getLookupValueColumn(),
            $column->getLookupAutofill(),
            $column->getLookupDisplay(),
            $column->getLookupTitle()
        );
    }

    protected function buildInlineLookup($field): ?LookupDefinition
    {
        if ($field->hasLookupDefinitionClass()) {
            return $this->resolveLookupDefinitionClass($field);
        }

        return $this->buildLookupFromModel(
            $field->getLookupModelClass(),
            $field->getLookupValueColumn(),
            $field->getLookupAutofill(),
            null,
            $field->getLookupTitle()
        );
    }

    /**
     * Resolve a lookup from a definition class, merging field/column overrides.
     */
    protected function resolveLookupDefinitionClass($fieldOrColumn): LookupDefinition
    {
        $definitionClass = $fieldOrColumn->getLookupDefinitionClass();
        $lookup = $definitionClass::definition();

        $autofill = $fieldOrColumn->getLookupAutofill();
        if (! empty($autofill)) {
            $lookup->autofill($autofill);
        }

        $title = $fieldOrColumn->getLookupTitle();
        if ($title !== null) {
            $lookup->title($title);
        }

        return $lookup;
    }

    /**
     * Build a LookupDefinition from a model class, value column, and autofill map.
     */
    protected function buildLookupFromModel(string $modelClass, ?string $valueColumn, array $autofill = [], ?string $display = null, ?string $title = null): ?LookupDefinition
    {
        try {
            $model = new $modelClass();

            if ($valueColumn === null) {
                $valueColumn = $model->getKeyName();
            }

            $columns = $this->resolveColumnsForModel($modelClass);

            $lookup = LookupDefinition::make($modelClass)
                ->columns($columns)
                ->valueColumn($valueColumn);

            if (! empty($autofill)) {
                $lookup->autofill($autofill);
            }

            if ($display !== null) {
                $lookup->display($display);
            }

            if ($title !== null) {
                $lookup->title($title);
            }

            $drillUrl = $this->buildDrillUrl($modelClass);
            if ($drillUrl !== null) {
                $lookup->onDrill($drillUrl);
            }

            return $lookup;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolve display columns for a model: registered resource table() > fillable attributes.
     */
    protected function resolveColumnsForModel(string $modelClass): array
    {
        // Check if a resource is registered for this model
        foreach ($this->resources as $resourceClass) {
            if ($resourceClass::getModel() === $modelClass) {
                return $resourceClass::table();
            }
        }

        // Fallback: generate TextColumns from model's fillable
        try {
            $model = new $modelClass();
            $fillable = $model->getFillable();

            if (empty($fillable)) {
                return [TextColumn::make('id')->label('ID')->width(10)];
            }

            $columns = [];
            foreach ($fillable as $i => $attr) {
                $width = $i === 0 ? 15 : ($i === count($fillable) - 1 ? 'fill' : 20);
                $columns[] = TextColumn::make($attr)->label(\Illuminate\Support\Str::headline($attr))->width($width);
            }

            return $columns;
        } catch (\Throwable) {
            return [];
        }
    }

    protected function buildRelationshipLookup(string $resourceClass, $field): ?LookupDefinition
    {
        $relationName = $field->getRelationshipName();
        $titleAttribute = $field->getRelationshipTitleAttribute();
        $modelClass = $resourceClass::getModel();

        // Resolve the related model from the Eloquent relationship
        try {
            $parentModel = new $modelClass();
            if (! method_exists($parentModel, $relationName)) {
                return null;
            }

            $relation = $parentModel->{$relationName}();
            $relatedModel = get_class($relation->getRelated());

            // Determine the foreign key column name (what the field stores)
            $foreignKeyName = $relation->getForeignKeyName();

            // Build a simple lookup: key column + title column
            $keyColumn = Str::afterLast($foreignKeyName, '.');
            $relatedKeyName = $relation->getOwnerKeyName();

            $lookup = LookupDefinition::make($relatedModel)
                ->columns([
                    TextColumn::make($relatedKeyName)->label(Str::headline($relatedKeyName))->width(15),
                    TextColumn::make($titleAttribute)->label(Str::headline($titleAttribute))->width('fill'),
                ])
                ->valueColumn($relatedKeyName);

            // Explicit autofill takes precedence
            $fieldAutofill = $field->getLookupAutofill();
            if (! empty($fieldAutofill)) {
                $lookup->autofill($fieldAutofill);
            } else {
                // Auto-generate namespaced autofill: {relationship}_{column} → {relationship}_{column}
                // e.g. relationship('customer', 'name') → autofill 'name' to 'customer_name'
                $autofillTarget = $relationName . '_' . $titleAttribute;
                $lookup->autofill([$titleAttribute => $autofillTarget]);
            }

            // Set title from field if provided
            $title = $field->getLookupTitle();
            if ($title !== null) {
                $lookup->title($title);
            }

            // Auto-set drill URL if the related model has a registered resource
            $drillUrl = $this->buildDrillUrl($relatedModel);
            if ($drillUrl !== null) {
                $lookup->onDrill($drillUrl);
            }

            return $lookup;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Build a drill URL for a model if it has a registered resource.
     */
    protected function buildDrillUrl(string $modelClass): ?string
    {
        foreach ($this->resources as $slug => $resourceClass) {
            if ($resourceClass::getModel() === $modelClass) {
                return static::baseUrl() . '/screen/' . $slug . '/card/{0}';
            }
        }

        return null;
    }

    public function registerMany(array $resourceClasses): void
    {
        foreach ($resourceClasses as $resourceClass) {
            $this->register($resourceClass);
        }
    }

    /**
     * Get the URL base path with prefix. Single source of truth.
     */
    public static function baseUrl(): string
    {
        $prefix = trim(config('twowee.prefix', 'terminal'), '/');

        return $prefix !== '' ? '/' . $prefix : '';
    }

    public function getResource(string $slug): ?string
    {
        return $this->resources[$slug] ?? null;
    }

    public function getResources(): array
    {
        return $this->resources;
    }

    public function getLookup(string $fieldId): ?LookupDefinition
    {
        return $this->lookups[$fieldId] ?? null;
    }

    public function getLookups(): array
    {
        return $this->lookups;
    }

    /**
     * Get relationship drilldown info for a field.
     */
    public function getRelationDrilldown(string $fieldId): ?array
    {
        return $this->relationDrilldowns[$fieldId] ?? null;
    }

    /**
     * Get the resource class that owns a blur-validated field.
     */
    public function getBlurFieldResource(string $fieldId): ?string
    {
        return $this->blurFields[$fieldId] ?? null;
    }

    public function discoverResources(string $directory, string $namespace): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory . '/*.php') as $file) {
            $className = $namespace . '\\' . pathinfo($file, PATHINFO_FILENAME);

            if (class_exists($className) && is_subclass_of($className, Resource::class)) {
                $this->register($className);
            }
        }
    }
}
