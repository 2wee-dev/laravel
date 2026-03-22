<?php

namespace TwoWee\Laravel;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use TwoWee\Laravel\Auth\TokenGuard;
use Illuminate\Support\Facades\Blade;
use TwoWee\Laravel\Commands\CheckTerminalCommand;
use TwoWee\Laravel\Commands\InstallCommand;
use TwoWee\Laravel\Commands\InstallTerminalCommand;
use TwoWee\Laravel\Commands\MakeActionCommand;
use TwoWee\Laravel\Commands\MakeLookupCommand;
use TwoWee\Laravel\Commands\MakeResourceCommand;
use TwoWee\Laravel\Commands\StartTerminalCommand;
use TwoWee\Laravel\Commands\StopTerminalCommand;
use TwoWee\Laravel\Terminal\BinManager;
use TwoWee\Laravel\View\Components\Terminal;

class TwoWeeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/twowee.php', 'twowee');

        $this->app->singleton(TwoWee::class);
        $this->app->singleton(BinManager::class);

        $this->app->alias(TwoWee::class, 'twowee');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/twowee.php' => config_path('twowee.php'),
        ], 'twowee-config');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->loadRoutesFrom(__DIR__ . '/../routes/twowee.php');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', '2wee');

        Blade::component(Terminal::class, '2wee-terminal');
        Blade::componentNamespace('TwoWee\\Laravel\\View\\Components', '2wee');

        $this->registerGuard();

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                MakeResourceCommand::class,
                MakeLookupCommand::class,
                MakeActionCommand::class,
                InstallTerminalCommand::class,
                StartTerminalCommand::class,
                StopTerminalCommand::class,
                CheckTerminalCommand::class,
            ]);
        }

        $this->bootResources();
    }

    protected function registerGuard(): void
    {
        Auth::extend('twowee', function ($app, $name, array $config) {
            return new TokenGuard($app['request']);
        });

        $this->app['config']->set('auth.guards.twowee', [
            'driver' => 'twowee',
            'provider' => 'users',
        ]);
    }

    protected function bootResources(): void
    {
        /** @var TwoWee $twoWee */
        $twoWee = $this->app->make(TwoWee::class);

        // Register resources from config
        foreach (config('twowee.resources', []) as $resourceClass) {
            $twoWee->register($resourceClass);
        }

        // Auto-discover from app/TwoWee/Resources/
        $twoWee->discoverResources(
            app_path('TwoWee/Resources'),
            'App\\TwoWee\\Resources'
        );
    }
}
