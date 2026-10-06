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
    })->group('unit', 'fast');

    test('can be instantiated with disabled flag', function () {
        $service = new CacheService(enabled: false);

        expect($service->isEnabled())->toBeFalse();
    })->group('unit', 'fast');

    test('defaults to disabled', function () {
        $service = new CacheService;

        expect($service->isEnabled())->toBeFalse();
    })->group('unit', 'fast');

    test('converts ttl minutes to seconds', function () {
        $service = new CacheService(enabled: true, ttlMinutes: 120);

        expect($service->getTtl())->toBe(7200);
    })->group('unit', 'fast');

    test('get calls callback when cache is disabled', function () {
        $service = new CacheService(enabled: false);
        $called = false;

        $result = $service->get('test-key', function () use (&$called) {
            $called = true;

            return 'value';
        });

        expect($called)->toBeTrue();
        expect($result)->toBe('value');
    })->group('unit', 'fast');

    test('get caches the value under a namespaced key when enabled', function () {
        $service = new CacheService(enabled: true, ttlMinutes: 60);

        expect($service->get('test-key', fn () => 'value'))->toBe('value')
            ->and($service->get('test-key', fn () => 'other'))->toBe('value')
            ->and(Cache::get('propelauth.v3.test-key'))->toBe('value');
    })->group('unit', 'fast');

    test('forget returns true when cache is disabled', function () {
        $service = new CacheService(enabled: false);

        $result = $service->forget('test-key');

        expect($result)->toBeTrue();
    })->group('unit', 'fast');

    test('forget deletes from cache when enabled', function () {
        $service = new CacheService(enabled: true);
        $service->get('test-key', fn () => 'value');

        expect($service->forget('test-key'))->toBeTrue()
            ->and(Cache::has('propelauth.v3.test-key'))->toBeFalse();
    })->group('unit', 'fast');

    test('flush does nothing when cache is disabled', function () {
        $service = new CacheService(enabled: false);

        $service->flush();

        expect(Cache::has('propelauth.v3.generation'))->toBeFalse();
    })->group('unit', 'fast');

    test('flush invalidates all propelauth cache when enabled', function () {
        $service = new CacheService(enabled: true);
        $service->get('user.user123', fn () => 'old');
        $service->get('org.org456', fn () => 'old');

        $service->flush();

        expect($service->get('user.user123', fn () => 'new'))->toBe('new')
            ->and($service->get('org.org456', fn () => 'new'))->toBe('new');
    })->group('unit', 'fast');

    test('flush works on stores without tag support', function () {
        config()->set('cache.default', 'file');
        $service = new CacheService(enabled: true);
        $service->get('user.user123', fn () => 'old');

        $service->flush();

        expect($service->get('user.user123', fn () => 'new'))->toBe('new');

        Cache::flush();
    })->group('unit', 'fast');

    test('keys written after a flush can still be forgotten', function () {
        $service = new CacheService(enabled: true);
        $service->flush();
        $service->get('user.user123', fn () => 'value');

        $service->invalidateUser('user123');

        expect($service->get('user.user123', fn () => 'fresh'))->toBe('fresh');
    })->group('unit', 'fast');

    test('invalidateUser forgets user cache key', function () {
        $service = new CacheService(enabled: true);
        $service->get('user.user123', fn () => 'value');

        $service->invalidateUser('user123');

        expect(Cache::has('propelauth.v3.user.user123'))->toBeFalse();
    })->group('unit', 'fast');

    test('invalidateOrganisation forgets organisation caches', function () {
        $service = new CacheService(enabled: true);
        $service->get('org.org456', fn () => 'value');
        $service->get('org.org456.users', fn () => 'value');

        $service->invalidateOrganisation('org456');

        expect(Cache::has('propelauth.v3.org.org456'))->toBeFalse()
            ->and(Cache::has('propelauth.v3.org.org456.users'))->toBeFalse();
    })->group('unit', 'fast');

    test('default ttl is 60 minutes', function () {
        $service = new CacheService(enabled: true);

        expect($service->getTtl())->toBe(3600);
    })->group('unit', 'fast');
});
