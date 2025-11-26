<?php

namespace LittleGreenMan\Earhart;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        //        $this->publishes([
        //            __DIR__ . '/../config/earhart.php' => config_path('earhart.php'),
        //        ], 'config');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }

    public function register()
    {
        //        $this->mergeConfigFrom(
        //            __DIR__ . '/../config/earhart.php', 'earhart'
        //        );

        $this->app->singleton('earhart', function ($app) {
            return new Earhart(
                clientId: config('services.propelauth.client_id'),
                clientSecret: config('services.propelauth.client_secret'),
                callbackUrl: config('services.propelauth.redirect'),
                authUrl: config('services.propelauth.auth_url'),
                svixSecret: config('services.propelauth.svix_secret'),
                apiKey: config('services.propelauth.api_key'),
            );
        });
    }
}
