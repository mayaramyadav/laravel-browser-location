<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Mayaram\BrowserLocation\Commands\InstallBrowserLocationCommand;
use Mayaram\BrowserLocation\Http\Middleware\ValidateBrowserLocation;
use Mayaram\BrowserLocation\View\Components\BrowserLocationTracker;

class BrowserLocationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/browser-location.php', 'browser-location');

        $this->app->singleton(BrowserLocation::class, fn (): BrowserLocation => new BrowserLocation());
    }

    public function boot(Router $router): void
    {
        if ((bool) config('browser-location.api.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'browser-location');

        Blade::component('browser-location-tracker', BrowserLocationTracker::class);

        $router->aliasMiddleware('browser-location.validate', ValidateBrowserLocation::class);

        $this->publishes([
            __DIR__.'/../config/browser-location.php' => config_path('browser-location.php'),
        ], 'browser-location-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/browser-location'),
        ], 'browser-location-views');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'browser-location-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallBrowserLocationCommand::class,
            ]);
        }
    }
}
