<?php

namespace LittleGreenMan\Earhart\Tests;

use LittleGreenMan\Earhart\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Set up test configuration (both earhart.* and services.propelauth.* for compatibility)
        $app['config']->set('earhart.api_key', 'test-api-key');
        $app['config']->set('earhart.auth_url', 'https://auth.example.com');
        $app['config']->set('earhart.client_id', 'test-client-id');
        $app['config']->set('earhart.client_secret', 'test-client-secret');
        $app['config']->set('earhart.redirect_url', 'https://app.example.com/callback');
        $app['config']->set('earhart.svix_secret', 'test-svix-secret');
        $app['config']->set('earhart.cache.enabled', false);
        $app['config']->set('earhart.cache.ttl_minutes', 60);

        // Also set services.propelauth.* as ServiceProvider uses these for service registration
        $app['config']->set('services.propelauth.api_key', 'test-api-key');
        $app['config']->set('services.propelauth.auth_url', 'https://auth.example.com');
        $app['config']->set('services.propelauth.client_id', 'test-client-id');
        $app['config']->set('services.propelauth.client_secret', 'test-client-secret');
        $app['config']->set('services.propelauth.redirect_url', 'https://app.example.com/callback');
        $app['config']->set('services.propelauth.svix_secret', 'test-svix-secret');
        $app['config']->set('services.propelauth.cache.enabled', false);
        $app['config']->set('services.propelauth.cache.ttl_minutes', 60);

        // Set up cache configuration for testing
        $app['config']->set('cache.default', 'array');

        // Set up spatie/laravel-data configuration
        $app['config']->set('data.validation_strategy', 'disabled');

        // PERFORMANCE OPTIMIZATION: Disable database (no DB dependencies in this package)
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        // PERFORMANCE OPTIMIZATION: Disable unnecessary Laravel services
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('logging.default', 'null');
        $app['config']->set('logging.channels.null', [
            'driver' => 'monolog',
            'handler' => \Monolog\Handler\NullHandler::class,
        ]);
        $app['config']->set('broadcasting.default', 'null');
        $app['config']->set('app.debug', false);
    }

    /**
     * Prevent database migrations from running (no DB dependencies).
     */
    protected function defineDatabaseMigrations()
    {
        // Intentionally empty - this package has no database dependencies
    }
}
