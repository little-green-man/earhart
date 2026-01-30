<?php

namespace LittleGreenMan\Earhart\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
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
     */
    public function flush(): void
    {
        if ($this->enabled) {
            Cache::tags(['propelauth'])->flush();
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
        return "propelauth.{$key}";
    }
}
