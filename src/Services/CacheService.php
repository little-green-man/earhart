<?php

namespace LittleGreenMan\Earhart\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    /**
     * Prefix for every entry. Bumped when cached data classes change shape,
     * so entries written by an older version are never read back.
     */
    protected const PREFIX = 'propelauth.v3';

    protected const GENERATION_KEY = self::PREFIX.'.generation';

    protected int $ttlSeconds;

    protected bool $enabled;

    public function __construct(bool $enabled = false, int $ttlMinutes = 60)
    {
        $this->enabled = $enabled;
        $this->ttlSeconds = $ttlMinutes * 60;
    }

    /**
     * Get or fetch a value from cache.
     */
    public function get(string $key, \Closure $callback): mixed
    {
        if (! $this->enabled) {
            return $callback();
        }

        $cacheKey = $this->buildKey($key);

        return Cache::remember($cacheKey, $this->ttlSeconds, $callback);
    }

    /**
     * Forget a cached value.
     */
    public function forget(string $key): bool
    {
        if (! $this->enabled) {
            return true;
        }

        return Cache::forget($this->buildKey($key));
    }

    /**
     * Flush all PropelAuth cache.
     *
     * Bumps a generation counter that is part of every key, so existing
     * entries are no longer read and expire via their TTL. Unlike cache tags,
     * this works on every cache store.
     */
    public function flush(): void
    {
        if ($this->enabled) {
            Cache::forever(self::GENERATION_KEY, $this->generation() + 1);
        }
    }

    /**
     * Invalidate user cache.
     */
    public function invalidateUser(string $userId): void
    {
        $this->forget("user.{$userId}");
    }

    /**
     * Invalidate organisation cache.
     */
    public function invalidateOrganisation(string $orgId): void
    {
        $this->forget("org.{$orgId}");
        $this->forget("org.{$orgId}.users");
    }

    /**
     * Check if caching is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get cache TTL in seconds.
     */
    public function getTtl(): int
    {
        return $this->ttlSeconds;
    }

    /**
     * Build a cache key with namespace.
     */
    protected function buildKey(string $key): string
    {
        $generation = $this->generation();

        return $generation === 0 ? self::PREFIX.".{$key}" : self::PREFIX.".{$generation}.{$key}";
    }

    protected function generation(): int
    {
        return (int) Cache::get(self::GENERATION_KEY, 0);
    }
}
