<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Mayaram\BrowserLocation\Commands\InstallBrowserLocationCommand;
use Mayaram\BrowserLocation\Contracts\Geocoder as GeocoderContract;
use Mayaram\BrowserLocation\Http\Middleware\ValidateBrowserLocation;
use Mayaram\BrowserLocation\View\Components\BrowserLocationTracker;

class BrowserLocationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/browser-location.php', 'browser-location');

        $this->app->singleton(BrowserLocation::class, fn (): BrowserLocation => new BrowserLocation);
        $this->app->singleton(LocationPersister::class, function ($app): LocationPersister {
            /** @var Request|null $request */
            $request = $app->bound('request') ? $app->make('request') : null;

            return new LocationPersister(
                $app->make(BrowserLocation::class),
                $app->make(GeocoderContract::class),
                $app->make(ConfigRepository::class),
                $request
            );
        });
        $this->app->singleton(GeocoderContract::class, function ($app): Geocoder {
            return new Geocoder(
                $app->make(HttpFactory::class),
                $app->make(CacheFactory::class),
                $app->make(ConfigRepository::class)
            );
        });
        $this->app->alias(GeocoderContract::class, Geocoder::class);
        $this->app->alias(GeocoderContract::class, 'browser-location.geocoder');
    }

    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'browser-location');

        Blade::component('browser-location-tracker', BrowserLocationTracker::class);

        $router->aliasMiddleware('browser-location.validate', ValidateBrowserLocation::class);

        $this->loadRoutesFrom(__DIR__.'/../routes/browser-location.php');

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
