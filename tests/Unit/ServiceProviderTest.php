<?php

use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Tests\TestCase;

uses(TestCase::class);

describe('ServiceProvider cache configuration', function () {
    test('reads cache settings from earhart config', function () {
        config()->set('earhart.cache.enabled', true);
        config()->set('earhart.cache.ttl_minutes', 15);
        app()->forgetInstance(CacheService::class);

        $cache = app(CacheService::class);

        expect($cache->isEnabled())->toBeTrue()
            ->and($cache->getTtl())->toBe(15 * 60);
    });

    test('legacy services.propelauth cache settings take precedence', function () {
        config()->set('earhart.cache.enabled', false);
        config()->set('services.propelauth.cache.enabled', true);
        config()->set('services.propelauth.cache.ttl_minutes', 5);
        app()->forgetInstance(CacheService::class);

        $cache = app(CacheService::class);

        expect($cache->isEnabled())->toBeTrue()
            ->and($cache->getTtl())->toBe(5 * 60);
    });
});
