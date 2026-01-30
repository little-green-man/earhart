<?php

namespace LittleGreenMan\Earhart\Webhooks;

/**
 * Configuration class for webhook handling behavior.
 *
 * This class allows customization of which events trigger cache invalidation,
 * custom cache key naming, and other webhook processing options.
 *
 * Usage:
 *   $config = WebhookConfig::default()
 *       ->withCustomCacheKeyFormat('app:propelauth:{type}:{id}')
 *       ->disableCacheInvalidationFor(['user.updated']);
 *
 *   $handler = new WebhookEventHandler(
 *       cacheInvalidationConfig: $config
 *   );
 */
class WebhookConfig
{
    /**
     * Events that should trigger cache invalidation for users.
     *
     * @var array<string>
     */
    private array $userCacheInvalidationEvents = [
        'user.created',
        'user.updated',
        'user.deleted',
        'user.disabled',
        'user.enabled',
        'user.locked',
        'user.added_to_org',
        'user.removed_from_org',
        'user.role_changed_within_org',
    ];

    /**
     * Events that should trigger cache invalidation for organisations.
     *
     * @var array<string>
     */
    private array $orgCacheInvalidationEvents = [
        'org.created',
        'org.updated',
        'org.deleted',
        'user.added_to_org',
        'user.removed_from_org',
    ];

    /**
     * Whether to invalidate user list caches when user-related events occur.
     */
    private bool $invalidateUserListCache = true;

    /**
     * Whether to invalidate organisation list caches when org-related events occur.
     */
    private bool $invalidateOrgListCache = true;

    /**
     * Custom cache key format for user caches.
     * Placeholders: {user_id}, {id}
     */
    private ?string $userCacheKeyFormat = null;

    /**
     * Custom cache key format for organisation caches.
     * Placeholders: {org_id}, {id}
     */
    private ?string $orgCacheKeyFormat = null;

    /**
     * Whether to verify webhook signatures before processing.
     */
    private bool $verifySignatures = true;

    /**
     * The Svix webhook signing secret for verification.
     */
    private ?string $signingSecret = null;

    /**
     * Timestamp tolerance in seconds for webhook verification (prevents replay attacks).
     */
    private int $timestampToleranceSeconds = 300; // 5 minutes

    /**
     * Create a new webhook configuration with default settings.
     */
    public static function default(): self
    {
        return new self;
    }

    /**
     * Create a webhook configuration from an array (useful for config files).
     *
     * @param  array  $config  Configuration array
     */
    public static function fromArray(array $config): self
    {
        $instance = new self;

        if (isset($config['user_cache_invalidation_events'])) {
            $instance->userCacheInvalidationEvents = $config['user_cache_invalidation_events'];
        }

        if (isset($config['org_cache_invalidation_events'])) {
            $instance->orgCacheInvalidationEvents = $config['org_cache_invalidation_events'];
        }

        if (isset($config['invalidate_user_list_cache'])) {
            $instance->invalidateUserListCache = (bool) $config['invalidate_user_list_cache'];
        }

        if (isset($config['invalidate_org_list_cache'])) {
            $instance->invalidateOrgListCache = (bool) $config['invalidate_org_list_cache'];
        }

        if (isset($config['user_cache_key_format'])) {
            $instance->userCacheKeyFormat = $config['user_cache_key_format'];
        }

        if (isset($config['org_cache_key_format'])) {
            $instance->orgCacheKeyFormat = $config['org_cache_key_format'];
        }

        if (isset($config['verify_signatures'])) {
            $instance->verifySignatures = (bool) $config['verify_signatures'];
        }

        if (isset($config['signing_secret'])) {
            $instance->signingSecret = $config['signing_secret'];
        }

        if (isset($config['timestamp_tolerance_seconds'])) {
            $instance->timestampToleranceSeconds = (int) $config['timestamp_tolerance_seconds'];
        }

        return $instance;
    }

    /**
     * Set events that trigger user cache invalidation.
     *
     * @param  array<string>  $events
     */
    public function withUserCacheInvalidationEvents(array $events): self
    {
        $this->userCacheInvalidationEvents = $events;

        return $this;
    }

    /**
     * Add an event to user cache invalidation triggers.
     */
    public function addUserCacheInvalidationEvent(string $event): self
    {
        if (! in_array($event, $this->userCacheInvalidationEvents, true)) {
            $this->userCacheInvalidationEvents[] = $event;
        }

        return $this;
    }

    /**
     * Remove an event from user cache invalidation triggers.
     */
    public function removeUserCacheInvalidationEvent(string $event): self
    {
        $this->userCacheInvalidationEvents = array_filter($this->userCacheInvalidationEvents, fn ($e) => $e !== $event);

        return $this;
    }

    /**
     * Set events that trigger organisation cache invalidation.
     *
     * @param  array<string>  $events
     */
    public function withOrgCacheInvalidationEvents(array $events): self
    {
        $this->orgCacheInvalidationEvents = $events;

        return $this;
    }

    /**
     * Add an event to organisation cache invalidation triggers.
     */
    public function addOrgCacheInvalidationEvent(string $event): self
    {
        if (! in_array($event, $this->orgCacheInvalidationEvents, true)) {
            $this->orgCacheInvalidationEvents[] = $event;
        }

        return $this;
    }

    /**
     * Remove an event from organisation cache invalidation triggers.
     */
    public function removeOrgCacheInvalidationEvent(string $event): self
    {
        $this->orgCacheInvalidationEvents = array_filter($this->orgCacheInvalidationEvents, fn ($e) => $e !== $event);

        return $this;
    }

    /**
     * Set whether to invalidate user list caches.
     */
    public function setInvalidateUserListCache(bool $invalidate): self
    {
        $this->invalidateUserListCache = $invalidate;

        return $this;
    }

    /**
     * Set whether to invalidate organisation list caches.
     */
    public function setInvalidateOrgListCache(bool $invalidate): self
    {
        $this->invalidateOrgListCache = $invalidate;

        return $this;
    }

    /**
     * Set a custom cache key format for users.
     *
     * @param  string  $format  Cache key format with {user_id} or {id} placeholders
     */
    public function withUserCacheKeyFormat(string $format): self
    {
        $this->userCacheKeyFormat = $format;

        return $this;
    }

    /**
     * Set a custom cache key format for organisations.
     *
     * @param  string  $format  Cache key format with {org_id} or {id} placeholders
     */
    public function withOrgCacheKeyFormat(string $format): self
    {
        $this->orgCacheKeyFormat = $format;

        return $this;
    }

    /**
     * Enable or disable webhook signature verification.
     */
    public function setVerifySignatures(bool $verify): self
    {
        $this->verifySignatures = $verify;

        return $this;
    }

    /**
     * Set the Svix webhook signing secret.
     */
    public function withSigningSecret(string $secret): self
    {
        $this->signingSecret = $secret;

        return $this;
    }

    /**
     * Set the timestamp tolerance for webhook verification.
     *
     * @param  int  $seconds  Tolerance in seconds
     */
    public function setTimestampTolerance(int $seconds): self
    {
        $this->timestampToleranceSeconds = $seconds;

        return $this;
    }

    /**
     * Get events that trigger user cache invalidation.
     *
     * @return array<string>
     */
    public function getUserCacheInvalidationEvents(): array
    {
        return $this->userCacheInvalidationEvents;
    }

    /**
     * Get events that trigger organisation cache invalidation.
     *
     * @return array<string>
     */
    public function getOrgCacheInvalidationEvents(): array
    {
        return $this->orgCacheInvalidationEvents;
    }

    /**
     * Check if user list cache should be invalidated.
     */
    public function shouldInvalidateUserListCache(): bool
    {
        return $this->invalidateUserListCache;
    }

    /**
     * Check if organisation list cache should be invalidated.
     */
    public function shouldInvalidateOrgListCache(): bool
    {
        return $this->invalidateOrgListCache;
    }

    /**
     * Get custom user cache key format.
     */
    public function getUserCacheKeyFormat(): ?string
    {
        return $this->userCacheKeyFormat;
    }

    /**
     * Get custom organisation cache key format.
     */
    public function getOrgCacheKeyFormat(): ?string
    {
        return $this->orgCacheKeyFormat;
    }

    /**
     * Check if signature verification is enabled.
     */
    public function shouldVerifySignatures(): bool
    {
        return $this->verifySignatures;
    }

    /**
     * Get the webhook signing secret.
     */
    public function getSigningSecret(): ?string
    {
        return $this->signingSecret;
    }

    /**
     * Get the timestamp tolerance in seconds.
     */
    public function getTimestampTolerance(): int
    {
        return $this->timestampToleranceSeconds;
    }

    /**
     * Check if an event should trigger user cache invalidation.
     */
    public function shouldInvalidateUserCache(string $eventType): bool
    {
        return in_array($eventType, $this->userCacheInvalidationEvents, true);
    }

    /**
     * Check if an event should trigger organisation cache invalidation.
     */
    public function shouldInvalidateOrgCache(string $eventType): bool
    {
        return in_array($eventType, $this->orgCacheInvalidationEvents, true);
    }

    /**
     * Convert configuration to array for storage or transmission.
     */
    public function toArray(): array
    {
        return [
            'user_cache_invalidation_events' => $this->userCacheInvalidationEvents,
            'org_cache_invalidation_events' => $this->orgCacheInvalidationEvents,
            'invalidate_user_list_cache' => $this->invalidateUserListCache,
            'invalidate_org_list_cache' => $this->invalidateOrgListCache,
            'user_cache_key_format' => $this->userCacheKeyFormat,
            'org_cache_key_format' => $this->orgCacheKeyFormat,
            'verify_signatures' => $this->verifySignatures,
            'signing_secret' => $this->signingSecret ? '***' : null,
            'timestamp_tolerance_seconds' => $this->timestampToleranceSeconds,
        ];
    }
}
