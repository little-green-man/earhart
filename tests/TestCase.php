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
        // Set up test configuration
        $app['config']->set('earhart.api_key', 'test-api-key');
        $app['config']->set('earhart.auth_url', 'https://auth.example.com');
        $app['config']->set('earhart.client_id', 'test-client-id');
        $app['config']->set('earhart.client_secret', 'test-client-secret');
        $app['config']->set('earhart.redirect_url', 'https://app.example.com/callback');
        $app['config']->set('earhart.svix_secret', 'test-svix-secret');
        $app['config']->set('earhart.cache.enabled', false);
        $app['config']->set('earhart.cache.ttl_minutes', 60);

        // Set up cache configuration for testing
        $app['config']->set('cache.default', 'array');

        // Set up spatie/laravel-data configuration
        $app['config']->set('data.validation_strategy', 'disabled');
    }
}
