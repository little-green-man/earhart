<?php

namespace LittleGreenMan\Earhart;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
//        $this->publishes([
//            __DIR__ . '/../config/propelauth.php' => config_path('propelauth.php'),
//        ], 'config');
//        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

    public function register()
    {
//        $this->mergeConfigFrom(
//            __DIR__ . '/../config/propelauth.php', 'propelauth'
//        );

//        $this->app->singleton('earhart', function ($app) {
//            return new PropelAuth(config('earhart'));
//        });
    }
}
