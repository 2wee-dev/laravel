<?php

namespace TwoWee\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \TwoWee\Laravel\TwoWee
 */
class TwoWee extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \TwoWee\Laravel\TwoWee::class;
    }
}
