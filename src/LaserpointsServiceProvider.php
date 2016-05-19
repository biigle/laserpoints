<?php

namespace Dias\Modules\Laserpoints;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Dias\Modules\Laserpoints\Console\Commands\Install as InstallCommand;

class LaserpointsServiceProvider extends ServiceProvider {

    /**
     * Bootstrap the application events.
     *
     * @param  \Dias\Services\Modules  $modules
     * @param  \Illuminate\Routing\Router  $router
     *
     * @return void
     */
    public function boot(Router $router)
    {
        $router->group([
            'namespace' => 'Dias\Modules\Laserpoints\Http\Controllers',
            'middleware' => 'web',
        ], function ($router) {
            require __DIR__.'/Http/routes.php';
        });
        $this->publishes([
            __DIR__.'/config/laserpoints.php' => config_path('laserpoints.php'),
        ], 'config');
        $this->publishes([
            __DIR__.'/database/migrations/' => database_path('migrations')
        ], 'migrations');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/config/laserpoints.php', 'laserpoints');
        // set up the install console command
        $this->app->singleton('command.laserpoints.install', function ($app) {
            return new InstallCommand();
        });

        $this->commands('command.laserpoints.install');
    }
     /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'command.laserpoints.install',
        ];
    }
}
