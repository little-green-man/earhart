<?php

namespace LittleGreenMan\Earhart;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Services\OrganisationService;
use LittleGreenMan\Earhart\Services\UserService;

class ServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/earhart.php' => config_path('earhart.php'),
        ], 'config');

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Validate required configuration after everything is set up
        $this->validateConfiguration();
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/earhart.php', 'earhart');

        // Register CacheService
        $this->app->singleton(CacheService::class, function ($app) {
            return new CacheService(
                enabled: (bool) config('services.propelauth.cache.enabled', false),
                ttlMinutes: (int) config('services.propelauth.cache.ttl_minutes', 60),
            );
        });

        // Register UserService
        $this->app->singleton(UserService::class, function ($app) {
            return new UserService(
                apiKey: (string) config('services.propelauth.api_key'),
                authUrl: (string) config('services.propelauth.auth_url'),
                cache: $app->make(CacheService::class),
            );
        });

        // Register OrganisationService
        $this->app->singleton(OrganisationService::class, function ($app) {
            return new OrganisationService(
                apiKey: (string) config('services.propelauth.api_key'),
                authUrl: (string) config('services.propelauth.auth_url'),
                cache: $app->make(CacheService::class),
            );
        });

        // Register main Earhart facade/service
        $this->app->singleton('earhart', function ($app) {
            return new Earhart(
                clientId: (string) config('services.propelauth.client_id'),
                clientSecret: (string) config('services.propelauth.client_secret'),
                callbackUrl: (string) config('services.propelauth.redirect'),
                authUrl: (string) config('services.propelauth.auth_url'),
                svixSecret: (string) config('services.propelauth.svix_secret'),
                apiKey: (string) config('services.propelauth.api_key'),
                enableCache: (bool) config('services.propelauth.cache.enabled', false),
                cacheTtlMinutes: (int) config('services.propelauth.cache.ttl_minutes', 60),
            );
        });

        $this->app->alias('earhart', Earhart::class);
    }

    /**
     * Validate required configuration values are present.
     *
     * @throws \RuntimeException If required configuration is missing
     */
    private function validateConfiguration(): void
    {
        // Skip validation when running in console (tests, artisan commands)
        if ($this->app->runningInConsole()) {
            return;
        }

        $requiredKeys = [
            'api_key' => 'PropelAuth API key',
            'auth_url' => 'PropelAuth Auth URL',
            'client_id' => 'PropelAuth Client ID',
            'client_secret' => 'PropelAuth Client Secret',
            'svix_secret' => 'PropelAuth Webhook Secret',
        ];

        foreach ($requiredKeys as $key => $label) {
            $value = config("earhart.{$key}");
            if (! $value) {
                $envKey = strtoupper($key);
                throw new \RuntimeException(
                    "{$label} is not configured. "
                    ."Please set PROPELAUTH_{$envKey} environment variable or configure earhart.{$key} in config/earhart.php",
                );
            }
        }
    }
}
