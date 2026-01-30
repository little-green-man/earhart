<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Tests\TestCase;

uses(TestCase::class);

describe('CacheService', function () {
    test('can be instantiated with enabled flag', function () {
        $service = new CacheService(enabled: true, ttlMinutes: 60);

        expect($service->isEnabled())->toBeTrue();
    });

    test('can be instantiated with disabled flag', function () {
        $service = new CacheService(enabled: false);

        expect($service->isEnabled())->toBeFalse();
    });

    test('defaults to disabled', function () {
        $service = new CacheService;

        expect($service->isEnabled())->toBeFalse();
    });

    test('converts ttl minutes to seconds', function () {
        $service = new CacheService(enabled: true, ttlMinutes: 120);

        expect($service->getTtl())->toBe(7200);
    });

    test('get calls callback when cache is disabled', function () {
        $service = new CacheService(enabled: false);
        $called = false;

        $result = $service->get('test-key', function () use (&$called) {
            $called = true;

            return 'value';
        });

        expect($called)->toBeTrue();
        expect($result)->toBe('value');
    });

    test('get uses cache when enabled', function () {
        $service = new CacheService(enabled: true, ttlMinutes: 60);

        Cache::shouldReceive('remember')
            ->once()
            ->with('propelauth.test-key', 3600, \Mockery::any())
            ->andReturn('cached-value');

        $result = $service->get('test-key', fn () => 'value');

        expect($result)->toBe('cached-value');
    });

    test('forget returns true when cache is disabled', function () {
        $service = new CacheService(enabled: false);

        $result = $service->forget('test-key');

        expect($result)->toBeTrue();
    });

    test('forget deletes from cache when enabled', function () {
        $service = new CacheService(enabled: true);

        Cache::shouldReceive('forget')
            ->once()
            ->with('propelauth.test-key')
            ->andReturn(true);

        $result = $service->forget('test-key');

        expect($result)->toBeTrue();
    });

    test('flush does nothing when cache is disabled', function () {
        $service = new CacheService(enabled: false);

        // Should not call Cache methods
        $service->flush();

        expect(true)->toBeTrue();
    });

    test('flush clears all propelauth cache when enabled', function () {
        $service = new CacheService(enabled: true);

        Cache::shouldReceive('tags')
            ->once()
            ->with(['propelauth'])
            ->andReturnSelf();

        Cache::shouldReceive('flush')->once();

        $service->flush();

        expect(true)->toBeTrue();
    });

    test('invalidateUser forgets user cache key', function () {
        $service = new CacheService(enabled: true);

        Cache::shouldReceive('forget')
            ->once()
            ->with('propelauth.user.user123')
            ->andReturn(true);

        $service->invalidateUser('user123');

        expect(true)->toBeTrue();
    });

    test('invalidateOrganisation forgets organisation caches', function () {
        $service = new CacheService(enabled: true);

        Cache::shouldReceive('forget')->twice()->andReturn(true);

        $service->invalidateOrganisation('org456');

        expect(true)->toBeTrue();
    });

    test('builds cache key with namespace', function () {
        $service = new CacheService(enabled: true);

        Cache::shouldReceive('remember')
            ->once()
            ->with('propelauth.custom-key', \Mockery::any(), \Mockery::any())
            ->andReturn('value');

        $result = $service->get('custom-key', fn () => 'value');

        expect($result)->toBe('value');
    });

    test('default ttl is 60 minutes', function () {
        $service = new CacheService(enabled: true);

        expect($service->getTtl())->toBe(3600);
    });
});
