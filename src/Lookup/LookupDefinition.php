<?php

namespace TwoWee\Laravel\Lookup;

use TwoWee\Laravel\Columns\Column;

class LookupDefinition
{
    protected string $model;

    /** @var Column[] */
    protected array $columns = [];

    protected string $valueColumn;

    protected array $autofill = [];

    protected ?\Closure $queryModifier = null;

    protected ?string $display = null;

    protected ?\Closure $drilldownModifier = null;

    protected ?string $drilldownKeyColumn = null;

    protected ?string $onDrill = null;

    protected ?string $title = null;

    protected ?int $pageSize = null;

    public function __construct(string $model)
    {
        $this->model = $model;
    }

    public static function make(string $model): static
    {
        return new static($model);
    }

    /**
     * @param Column[] $columns
     */
    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function valueColumn(string $column): static
    {
        $this->valueColumn = $column;

        return $this;
    }

    /**
     * Map lookup column IDs to card field IDs for auto-population.
     * e.g. ['name' => 'customer_name', 'city' => 'city']
     */
    public function autofill(array $map): static
    {
        $this->autofill = $map;

        return $this;
    }

    /**
     * Custom query modifier for filtering.
     */
    public function query(\Closure $modifier): static
    {
        $this->queryModifier = $modifier;

        return $this;
    }

    /**
     * Display mode: 'modal' or null (full-screen).
     */
    public function display(?string $display): static
    {
        $this->display = $display;

        return $this;
    }

    /**
     * Set the drilldown query closure for exact key filtering.
     * Receives ($query, $key). Used by DrilldownController.
     */
    public function drilldownQuery(\Closure $modifier): static
    {
        $this->drilldownModifier = $modifier;

        return $this;
    }

    /**
     * Set the column to filter by when drilldown is called without a custom query.
     * Defaults to valueColumn if not set.
     */
    public function drilldownKeyColumn(string $column): static
    {
        $this->drilldownKeyColumn = $column;

        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getValueColumn(): string
    {
        return $this->valueColumn;
    }

    public function getAutofill(): array
    {
        return $this->autofill;
    }

    public function getQueryModifier(): ?\Closure
    {
        return $this->queryModifier;
    }

    public function getDisplay(): ?string
    {
        return $this->display;
    }

    public function onDrill(string $url): static
    {
        $this->onDrill = $url;

        return $this;
    }

    public function getOnDrill(): ?string
    {
        return $this->onDrill;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function pageSize(int $size): static
    {
        $this->pageSize = $size;

        return $this;
    }

    protected function getPageSize(): int
    {
        if ($this->pageSize !== null) {
            return $this->pageSize;
        }

        if (function_exists('config') && app()->bound('config')) {
            return config('twowee.lookup.page_size', 50);
        }

        return 50;
    }

    /**
     * Execute the lookup query and return results.
     * $context contains all query parameters from the request (context fields + query).
     */
    public function resolve(?string $searchTerm = null, array $context = [], ?string $selected = null): array
    {
        $modelClass = $this->model;
        $query = $modelClass::query();
        $limit = $this->getPageSize();

        if ($this->queryModifier !== null) {
            ($this->queryModifier)($query, $context);
        }

        $this->applyContextFilters($query, $context);

        if ($searchTerm !== null && $searchTerm !== '') {
            $usesScout = in_array('Laravel\Scout\Searchable', class_uses_recursive($modelClass));

            if ($usesScout) {
                // Scout search with context filters applied via query callback
                $contextFilters = $context;
                $queryMod = $this->queryModifier;

                return $modelClass::search($searchTerm)
                    ->query(function ($q) use ($queryMod, $contextFilters) {
                        if ($queryMod !== null) {
                            ($queryMod)($q, $contextFilters);
                        }
                        $this->applyContextFilters($q, $contextFilters);
                    })
                    ->take($limit)
                    ->get()
                    ->all();
            }

            // LIKE fallback
            $searchColumns = array_map(fn ($col) => $col->getName(), $this->columns);
            $query->where(function ($q) use ($searchColumns, $searchTerm) {
                foreach ($searchColumns as $column) {
                    $q->orWhere($column, 'like', '%' . $searchTerm . '%');
                }
            });

            return $query->orderBy($this->valueColumn)->limit($limit)->get()->all();
        }

        if ($selected !== null && $selected !== '') {
            // Position mode: show rows around the selected value
            // Fetch 10 rows before + (limit - 10) rows from selected onward
            $beforeCount = min(10, $limit - 1);
            $afterCount = $limit - $beforeCount;

            $beforeRows = (clone $query)
                ->where($this->valueColumn, '<', $selected)
                ->orderByDesc($this->valueColumn)
                ->limit($beforeCount)
                ->get()
                ->reverse()
                ->values();

            $fromRows = (clone $query)
                ->where($this->valueColumn, '>=', $selected)
                ->orderBy($this->valueColumn)
                ->limit($afterCount)
                ->get();

            $result = $beforeRows->merge($fromRows);

            // If still under the limit (near start or end), fill the gap
            if ($result->count() < $limit) {
                $have = $result->count();
                $need = $limit - $have;

                if ($beforeRows->count() < $beforeCount) {
                    // Near the start — get more rows after
                    $extraAfter = (clone $query)
                        ->where($this->valueColumn, '>=', $selected)
                        ->orderBy($this->valueColumn)
                        ->limit($afterCount + $need)
                        ->get();
                    $result = $beforeRows->merge($extraAfter);
                } else {
                    // Near the end — get more rows before
                    $extraBefore = (clone $query)
                        ->where($this->valueColumn, '<', $selected)
                        ->orderByDesc($this->valueColumn)
                        ->limit($beforeCount + $need)
                        ->get()
                        ->reverse()
                        ->values();
                    $result = $extraBefore->merge($fromRows);
                }
            }

            return $result->take($limit)->all();
        }

        // Default: first page sorted by value column
        return $query->orderBy($this->valueColumn)->limit($limit)->get()->all();
    }

    /**
     * Execute the drilldown query with exact key filtering.
     */
    public function resolveDrilldown(string $key, array $context = []): array
    {
        $modelClass = $this->model;
        $query = $modelClass::query();

        if ($this->drilldownModifier !== null) {
            ($this->drilldownModifier)($query, $key);
        } else {
            $column = $this->drilldownKeyColumn ?? $this->valueColumn;
            $query->where($column, $key);
        }

        if ($this->queryModifier !== null) {
            ($this->queryModifier)($query, $context);
        }

        return $query->get()->all();
    }

    /**
     * Validate a single value and return autofill data.
     */
    public function validateValue(string $value, array $context = []): array
    {
        $modelClass = $this->model;
        $query = $modelClass::query();

        if ($this->queryModifier !== null) {
            ($this->queryModifier)($query, $context);
        }

        $this->applyContextFilters($query, $context);

        // Case-insensitive lookup
        $record = $query->whereRaw(
            'UPPER(' . $this->valueColumn . ') = ?',
            [strtoupper($value)]
        )->first();

        if ($record === null) {
            return [
                'valid' => false,
                'autofill' => (object) [],
                'error' => 'Value not found.',
            ];
        }

        // Return the canonical value so the client can normalize (e.g. "dk" → "DK")
        $canonicalValue = (string) ($record->{$this->valueColumn} ?? '');

        $autofillData = [];
        foreach ($this->autofill as $lookupColumn => $fieldId) {
            $autofillData[$fieldId] = (string) ($record->{$lookupColumn} ?? '');
        }

        return [
            'valid' => true,
            'autofill' => ! empty($autofillData) ? $autofillData : (object) [],
            'error' => null,
            'value' => $canonicalValue,
        ];
    }

    /**
     * Auto-apply context parameters as WHERE clauses if they match
     * column names on the model's table.
     */
    protected function applyContextFilters($query, array $context): void
    {
        if (empty($context)) {
            return;
        }

        // Skip 'query' — that's the search term, not a context filter
        $filters = collect($context)->except('query')->filter(fn ($v) => $v !== null && $v !== '');

        if ($filters->isEmpty()) {
            return;
        }

        try {
            $model = new $this->model();
            $table = $model->getTable();
            $connection = $model->getConnection();
            $tableColumns = $connection->getSchemaBuilder()->getColumnListing($table);

            foreach ($filters as $key => $value) {
                if (in_array($key, $tableColumns)) {
                    $query->whereRaw('UPPER(' . $key . ') = ?', [strtoupper($value)]);
                }
            }
        } catch (\Throwable) {
            // Schema introspection not available — skip auto-filtering
        }
    }
}
