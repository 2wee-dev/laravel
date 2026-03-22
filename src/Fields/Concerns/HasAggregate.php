<?php

namespace TwoWee\Laravel\Fields\Concerns;

trait HasAggregate
{
    protected string|null $aggregateTarget = null;

    protected ?string $aggregateRelation = null;

    protected ?string $aggregateFunction = null;

    protected ?string $aggregateColumn = null;

    protected string|\Closure|null $drillDownEndpoint = null;

    protected string|null $drillDownTarget = null;

    protected ?string $drillDownResolvedRelation = null;

    protected array $drillDownColumns = [];

    /**
     * Set a drill-down endpoint for read-only navigation.
     * Ctrl+Enter opens the related data. Maps to lookup.endpoint in JSON.
     *
     * Accepts:
     * - String with {id} placeholder: '/drilldown/balance/{id}'
     * - Closure receiving the model: fn($model) => '/drilldown/balance/' . $model->getKey()
     * - Simple string (static endpoint): 'ledger_entries'
     *
     * When combined with aggregate(), pass just the relationship name and the
     * plugin auto-generates the drilldown URL and queries the relationship.
     */
    public function drillDown(string|\Closure $endpoint, array $columns = []): static
    {
        // Check if this is a relationship target (class name or plain string matching aggregate)
        if (is_string($endpoint) && ! str_contains($endpoint, '/') && ! str_contains($endpoint, '{')) {
            $this->drillDownTarget = $endpoint;
        }

        $this->drillDownEndpoint = $endpoint;
        $this->drillDownColumns = $columns;

        return $this;
    }

    /**
     * Compute the field value from a HasMany relationship aggregate.
     * Auto-disables the field. Supported functions: sum, count, avg, min, max.
     *
     * Accepts either a model class name or a relationship string:
     *   ->aggregate(LedgerEntry::class, 'sum', 'remaining_amount')
     *   ->aggregate('ledgerEntries', 'sum', 'remaining_amount')
     */
    public function aggregate(string $target, string $function, ?string $column = null): static
    {
        $this->aggregateTarget = $target;
        $this->aggregateFunction = $function;
        $this->aggregateColumn = $column;
        $this->isDisabled = true;

        return $this;
    }

    public function getAggregateTarget(): ?string
    {
        return $this->aggregateTarget;
    }

    public function getAggregateFunction(): ?string
    {
        return $this->aggregateFunction;
    }

    public function getAggregateColumn(): ?string
    {
        return $this->aggregateColumn;
    }

    public function hasAggregate(): bool
    {
        return $this->aggregateTarget !== null;
    }

    /**
     * Resolve the aggregate target to a relationship name on the given model.
     */
    public function resolveAggregateRelation(mixed $model): ?string
    {
        if ($this->aggregateRelation !== null) {
            return $this->aggregateRelation;
        }

        if ($this->aggregateTarget === null || $model === null) {
            return null;
        }

        $this->aggregateRelation = static::resolveRelationNameStatic($model, $this->aggregateTarget);

        return $this->aggregateRelation;
    }

    public function getDrillDownRelation(): ?string
    {
        return $this->drillDownResolvedRelation;
    }

    /**
     * Resolve the drilldown target to a relationship name on the given model.
     */
    public function resolveDrillDownRelation(mixed $model): ?string
    {
        if ($this->drillDownResolvedRelation !== null) {
            return $this->drillDownResolvedRelation;
        }

        if ($this->drillDownTarget === null || $model === null) {
            return null;
        }

        $this->drillDownResolvedRelation = static::resolveRelationNameStatic($model, $this->drillDownTarget);

        return $this->drillDownResolvedRelation;
    }

    public function getDrillDownColumns(): array
    {
        return $this->drillDownColumns;
    }

    public function getDrillDownTarget(): ?string
    {
        return $this->drillDownTarget;
    }

    /**
     * Resolve the drillDown endpoint, substituting {id} or calling closure.
     */
    protected function resolveDrillDown(mixed $model): string
    {
        $endpoint = $this->drillDownEndpoint;

        if ($endpoint instanceof \Closure) {
            return (string) ($endpoint)($model);
        }

        // Relationship drilldown: auto-build URL with the field name and record ID
        if ($this->drillDownTarget !== null && $model !== null) {
            // Resolve the relationship name (caches on first call)
            $this->resolveDrillDownRelation($model);

            $base = '';
            if (function_exists('config') && app()->bound('config')) {
                $base = \TwoWee\Laravel\TwoWee::baseUrl();
            }

            return $base . '/drilldown/' . $this->name . '/' . $model->getKey();
        }

        if ($model !== null && is_string($endpoint) && str_contains($endpoint, '{id}')) {
            return str_replace('{id}', (string) $model->getKey(), $endpoint);
        }

        return $endpoint;
    }
}
