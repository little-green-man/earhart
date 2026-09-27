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
                enabled: $this->cacheEnabled(),
                ttlMinutes: $this->cacheTtlMinutes(),
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
                enableCache: $this->cacheEnabled(),
                cacheTtlMinutes: $this->cacheTtlMinutes(),
            );
        });

        $this->app->alias('earhart', Earhart::class);
    }

    /**
     * Cache settings default to config/earhart.php. A value set under
     * services.propelauth.cache (the pre-2.1 location) takes precedence.
     */
    private function cacheConfig(string $key, mixed $default): mixed
    {
        return config("services.propelauth.cache.{$key}") ?? config("earhart.cache.{$key}", $default);
    }

    private function cacheEnabled(): bool
    {
        return (bool) $this->cacheConfig('enabled', false);
    }

    private function cacheTtlMinutes(): int
    {
        return (int) $this->cacheConfig('ttl_minutes', 60);
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
        ];

        foreach ($requiredKeys as $key => $label) {
            $value = config("services.propelauth.{$key}");
            if (! $value) {
                $envKey = strtoupper($key);
                throw new \RuntimeException(
                    "{$label} is not configured. "
                    ."Please set the PROPELAUTH_{$envKey} environment variable and configure services.propelauth.{$key} in config/services.php",
                );
            }
        }
    }
}
