<?php

namespace TwoWee\Laravel\Contracts;

use Illuminate\Database\Eloquent\Model;

interface SaveAction
{
    /**
     * Handle the complete save operation for a record.
     *
     * Receives the model (existing or newly created), the header field changes,
     * and the grid line data. Owns everything: validation, saving, syncing
     * lines, cleanup, calculations.
     *
     * @param  Model  $model  The model instance (already filled and saved by the plugin)
     * @param  array  $changes  Header field changes from the client
     * @param  array|null  $lines  Grid line data (2D array, column order matches lineColumns())
     */
    public function handle(Model $model, array $changes, ?array $lines): void;
}
