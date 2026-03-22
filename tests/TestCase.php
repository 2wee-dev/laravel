<?php

namespace TwoWee\Laravel\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use TwoWee\Laravel\TwoWeeServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [TwoWeeServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'TwoWee' => \TwoWee\Laravel\Facades\TwoWee::class,
        ];
    }
}
