<?php

namespace Biigle\Modules\Laserpoints;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Biigle\Services\Modules;

class LaserpointsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @param  \Biigle\Services\Modules  $modules
     * @param  \Illuminate\Routing\Router  $router
     *
     * @return void
     */
    public function boot(Modules $modules, Router $router)
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'laserpoints');

        $router->group([
            'namespace' => 'Biigle\Modules\Laserpoints\Http\Controllers',
            'middleware' => 'web',
        ], function ($router) {
            require __DIR__.'/Http/routes.php';
        });

        $this->publishes([
            __DIR__.'/public/assets' => public_path('vendor/laserpoints'),
        ], 'public');

        $this->publishes([
            __DIR__.'/config/laserpoints.php' => config_path('laserpoints.php'),
        ], 'config');

        $modules->register('laserpoints', [
            'viewMixins' => [
                'imagesIndex',
                'volumesScripts',
                'volumesSidebar',
                'annotationsSettingsTab',
                'annotationsScripts',
                'manualTutorial',
                'annotationsManualSidebarSettings',
                'manualReferences',
            ],
            'apidoc' => [__DIR__.'/Http/Controllers/Api/'],
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/config/laserpoints.php', 'laserpoints');

        $this->app->singleton('command.laserpoints.publish', function ($app) {
            return new \Biigle\Modules\Laserpoints\Console\Commands\Publish();
        });
        $this->app->singleton('command.laserpoints.config', function ($app) {
            return new \Biigle\Modules\Laserpoints\Console\Commands\Config();
        });

        $this->commands([
            'command.laserpoints.publish',
            'command.laserpoints.config',
        ]);
    }
    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'command.laserpoints.publish',
            'command.laserpoints.config',
        ];
    }
}
