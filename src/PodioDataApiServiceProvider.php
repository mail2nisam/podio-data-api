<?php

namespace Phases\PodioDataApi;

use Illuminate\Support\ServiceProvider;
use Phases\PodioDataApi\Commands\Sync;

class PodioDataApiServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        include __DIR__.'/routes.php';
        $this->app->make('Phases\PodioDataApi\PodioController');
        $this->commands([
            Sync::class
        ]);
    }


}
