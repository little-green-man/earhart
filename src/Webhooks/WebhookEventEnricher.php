<?php

namespace LittleGreenMan\Earhart\Webhooks;

use LittleGreenMan\Earhart\Exceptions\InvalidOrgException;
use LittleGreenMan\Earhart\Exceptions\InvalidUserException;
use LittleGreenMan\Earhart\PropelAuth\OrganisationData;
use LittleGreenMan\Earhart\PropelAuth\UserData;
use LittleGreenMan\Earhart\Services\OrganisationService;
use LittleGreenMan\Earhart\Services\UserService;

/**
 * Enriches webhook events with full user and organisation data.
 *
 * This enricher provides lazy-loaded access to full UserData and OrganisationData
 * objects from webhook events that only contain IDs or partial information.
 *
 * Usage:
 *   $enricher = new WebhookEventEnricher($userService, $orgService);
 *   $event = new UserCreated(['user_id' => '123', ...]);
 *   $userData = $enricher->enrichUserData($event, 'user_id');
 */
class WebhookEventEnricher
{
    /**
     * Cache for lazy-loaded user data.
     *
     * @var array<string, UserData>
     */
    private array $userDataCache = [];

    /**
     * Cache for lazy-loaded organisation data.
     *
     * @var array<string, OrganisationData>
     */
    private array $orgDataCache = [];

    public function __construct(
        private UserService $userService,
        private OrganisationService $orgService,
    ) {}

    /**
     * Enrich an event with full UserData by fetching from the user_id property.
     *
     * This method lazy-loads the full user data from PropelAuth on first access.
     * Subsequent accesses use the cached result.
     *
     * @param  object  $event  The webhook event object
     * @param  string  $userIdProperty  The property name that contains the user ID (default: 'user_id')
     * @return UserData|null The full user data, or null if not found
     *
     * @throws InvalidUserException If user cannot be found
     */
    public function enrichUserData(object $event, string $userIdProperty = 'user_id'): ?UserData
    {
        if (! property_exists($event, $userIdProperty)) {
            return null;
        }

        $userId = $event->$userIdProperty;

        if (! $userId) {
            return null;
        }

        return $this->getUserData($userId);
    }

    /**
     * Enrich an event with full OrganisationData by fetching from the org_id property.
     *
     * This method lazy-loads the full organisation data from PropelAuth on first access.
     * Subsequent accesses use the cached result.
     *
     * @param  object  $event  The webhook event object
     * @param  string  $orgIdProperty  The property name that contains the org ID (default: 'org_id')
     * @return OrganisationData|null The full organisation data, or null if not found
     *
     * @throws InvalidOrgException If organisation cannot be found
     */
    public function enrichOrgData(object $event, string $orgIdProperty = 'org_id'): ?OrganisationData
    {
        if (! property_exists($event, $orgIdProperty)) {
            return null;
        }

        $orgId = $event->$orgIdProperty;

        if (! $orgId) {
            return null;
        }

        return $this->getOrgData($orgId);
    }

    /**
     * Get full UserData by ID, with lazy-loading and caching.
     *
     * @param  string  $userId  The user ID
     * @return UserData The full user data
     *
     * @throws InvalidUserException If user cannot be found
     */
    public function getUserData(string $userId): UserData
    {
        if (! isset($this->userDataCache[$userId])) {
            $this->userDataCache[$userId] = $this->userService->getUser($userId, fresh: true);
        }

        return $this->userDataCache[$userId];
    }

    /**
     * Get full OrganisationData by ID, with lazy-loading and caching.
     *
     * @param  string  $orgId  The organisation ID
     * @return OrganisationData The full organisation data
     *
     * @throws InvalidOrgException If organisation cannot be found
     */
    public function getOrgData(string $orgId): OrganisationData
    {
        if (! isset($this->orgDataCache[$orgId])) {
            $this->orgDataCache[$orgId] = $this->orgService->getOrganisation($orgId, fresh: true);
        }

        return $this->orgDataCache[$orgId];
    }

    /**
     * Clear all cached data.
     *
     * Useful if you need to refresh enriched data during event processing.
     */
    public function clearCache(): void
    {
        $this->userDataCache = [];
        $this->orgDataCache = [];
    }

    /**
     * Clear cached user data for a specific user.
     *
     * @param  string  $userId  The user ID to clear from cache
     */
    public function clearUserCache(string $userId): void
    {
        unset($this->userDataCache[$userId]);
    }

    /**
     * Clear cached organisation data for a specific organisation.
     *
     * @param  string  $orgId  The organisation ID to clear from cache
     */
    public function clearOrgCache(string $orgId): void
    {
        unset($this->orgDataCache[$orgId]);
    }

    /**
     * Get the number of cached user data entries.
     */
    public function getCachedUserCount(): int
    {
        return count($this->userDataCache);
    }

    /**
     * Get the number of cached organisation data entries.
     */
    public function getCachedOrgCount(): int
    {
        return count($this->orgDataCache);
    }

    /**
     * Enrich an event with all available data based on its properties.
     *
     * This method prepares enriched data for an event by pre-loading user and organisation data
     * if the corresponding properties exist on the event object. The enriched data is cached
     * for efficient access via enrichUserData() and enrichOrgData() methods.
     *
     * @param  object  $event  The webhook event object
     * @return object The event object (unchanged)
     *
     * @throws InvalidUserException If a user cannot be found
     * @throws InvalidOrgException If an organisation cannot be found
     */
    public function enrich(object $event): object
    {
        // Pre-load user data if the event has a user_id property
        if (property_exists($event, 'user_id')) {
            $this->enrichUserData($event);
        }

        // Pre-load organisation data if the event has an org_id property
        if (property_exists($event, 'org_id')) {
            $this->enrichOrgData($event);
        }

        return $event;
    }
}
