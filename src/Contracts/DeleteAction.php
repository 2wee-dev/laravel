<?php

namespace TwoWee\Laravel\Contracts;

use Illuminate\Database\Eloquent\Model;

interface DeleteAction
{
    /**
     * Handle the complete delete operation for a record.
     *
     * Owns everything: checking if delete is allowed, archiving,
     * cleaning up related data, the actual delete.
     *
     * @param  Model  $model  The model instance to delete
     */
    public function handle(Model $model): void;
}
