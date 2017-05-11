<?php

namespace Phases\PodioDataApi;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Facades\Image;
use Intervention\Image\ImageServiceProvider;
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

        $this->publishes([
            __DIR__.'/migrations' => database_path(). '/migrations'
        ], 'migrations');

        $this->publishes([
            __DIR__.'/config'=> config_path(),
        ],'config');
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
        $this->app->register(ImageServiceProvider::class);
        $loader = AliasLoader::getInstance();
        $loader->alias('Image', Image::class);
        $this->commands([
            Sync::class
        ]);
    }


}
