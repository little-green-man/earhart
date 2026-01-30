<?php

namespace LittleGreenMan\Earhart\Webhooks;

use LittleGreenMan\Earhart\Services\CacheService;

/**
 * Automatically invalidates cache entries based on webhook events.
 *
 * When webhook events are received, this invalidator clears relevant cache
 * entries to ensure that subsequent API calls return fresh data. This prevents
 * stale data issues when user or organisation information changes.
 *
 * Usage:
 *   $invalidator = new WebhookCacheInvalidator($cacheService);
 *   $invalidator->handleEvent($event);
 */
class WebhookCacheInvalidator
{
    public function __construct(
        private CacheService $cacheService,
    ) {}

    /**
     * Handle cache invalidation for a webhook event.
     *
     * Determines the event type and invalidates relevant cache entries.
     * This method dispatches to specific handlers based on event class.
     *
     * @param  object  $event  The webhook event object
     */
    public function handleEvent(object $event): void
    {
        $eventClass = $event::class;
        $shortClassName = class_basename($eventClass);

        match ($shortClassName) {
            'UserCreated' => $this->handleUserCreated($event),
            'UserUpdated' => $this->handleUserUpdated($event),
            'UserDeleted' => $this->handleUserDeleted($event),
            'UserEnabled' => $this->handleUserEnabled($event),
            'UserDisabled' => $this->handleUserDisabled($event),
            'UserLocked' => $this->handleUserLocked($event),
            'UserAddedToOrg' => $this->handleUserAddedToOrg($event),
            'UserRemovedFromOrg' => $this->handleUserRemovedFromOrg($event),
            'UserRoleChangedWithinOrg' => $this->handleUserRoleChangedWithinOrg($event),
            'OrgCreated' => $this->handleOrgCreated($event),
            'OrgUpdated' => $this->handleOrgUpdated($event),
            'OrgDeleted' => $this->handleOrgDeleted($event),
            default => null,
        };
    }

    /**
     * Handle cache invalidation for UserCreated event.
     *
     * Invalidates:
     * - User query cache (since new user exists)
     */
    private function handleUserCreated(object $event): void
    {
        // Invalidate user list cache as a new user was created
        $this->cacheService->forget('users.list');
    }

    /**
     * Handle cache invalidation for UserUpdated event.
     *
     * Invalidates:
     * - Specific user data (profile updated)
     * - User email/username lookups (might have changed)
     */
    private function handleUserUpdated(object $event): void
    {
        if (isset($event->user_id)) {
            $this->cacheService->invalidateUser($event->user_id);
            // Also invalidate email/username lookups as they might have changed
            $this->cacheService->forget('users.list');
        }
    }

    /**
     * Handle cache invalidation for UserDeleted event.
     *
     * Invalidates:
     * - Specific user data
     * - User query cache
     * - All organisation memberships for this user
     */
    private function handleUserDeleted(object $event): void
    {
        if (isset($event->user_id)) {
            $this->cacheService->invalidateUser($event->user_id);
            $this->cacheService->forget('users.list');

            // Invalidate all org caches since this user is removed from them
            if (isset($event->org_ids) && is_array($event->org_ids)) {
                foreach ($event->org_ids as $orgId) {
                    $this->cacheService->invalidateOrganisation($orgId);
                }
            }
        }
    }

    /**
     * Handle cache invalidation for UserEnabled event.
     *
     * Invalidates:
     * - Specific user data (enabled status changed)
     */
    private function handleUserEnabled(object $event): void
    {
        if (isset($event->user_id)) {
            $this->cacheService->invalidateUser($event->user_id);
        }
    }

    /**
     * Handle cache invalidation for UserDisabled event.
     *
     * Invalidates:
     * - Specific user data (disabled status changed)
     */
    private function handleUserDisabled(object $event): void
    {
        if (isset($event->user_id)) {
            $this->cacheService->invalidateUser($event->user_id);
        }
    }

    /**
     * Handle cache invalidation for UserLocked event.
     *
     * Invalidates:
     * - Specific user data (locked status changed)
     */
    private function handleUserLocked(object $event): void
    {
        if (isset($event->user_id)) {
            $this->cacheService->invalidateUser($event->user_id);
        }
    }

    /**
     * Handle cache invalidation for UserAddedToOrg event.
     *
     * Invalidates:
     * - Specific user data (org membership changed)
     * - Organisation data (members list changed)
     * - Organisation user list cache
     */
    private function handleUserAddedToOrg(object $event): void
    {
        if (isset($event->user_id)) {
            $this->cacheService->invalidateUser($event->user_id);
        }

        if (isset($event->org_id)) {
            $this->cacheService->invalidateOrganisation($event->org_id);
            $this->cacheService->forget("org.{$event->org_id}.members");
        }
    }

    /**
     * Handle cache invalidation for UserRemovedFromOrg event.
     *
     * Invalidates:
     * - Specific user data (org membership changed)
     * - Organisation data (members list changed)
     * - Organisation user list cache
     */
    private function handleUserRemovedFromOrg(object $event): void
    {
        if (isset($event->removed_user_id)) {
            $this->cacheService->invalidateUser($event->removed_user_id);
        }

        if (isset($event->org_id)) {
            $this->cacheService->invalidateOrganisation($event->org_id);
            $this->cacheService->forget("org.{$event->org_id}.members");
        }
    }

    /**
     * Handle cache invalidation for UserRoleChangedWithinOrg event.
     *
     * Invalidates:
     * - Specific user data (role changed)
     * - Organisation data (role information changed)
     */
    private function handleUserRoleChangedWithinOrg(object $event): void
    {
        if (isset($event->user_id)) {
            $this->cacheService->invalidateUser($event->user_id);
        }

        if (isset($event->org_id)) {
            $this->cacheService->invalidateOrganisation($event->org_id);
        }
    }

    /**
     * Handle cache invalidation for OrgCreated event.
     *
     * Invalidates:
     * - Organisation list cache (new org exists)
     */
    private function handleOrgCreated(object $event): void
    {
        $this->cacheService->forget('orgs.list');
    }

    /**
     * Handle cache invalidation for OrgUpdated event.
     *
     * Invalidates:
     * - Specific organisation data
     * - Organisation list cache
     */
    private function handleOrgUpdated(object $event): void
    {
        if (isset($event->org_id)) {
            $this->cacheService->invalidateOrganisation($event->org_id);
            $this->cacheService->forget('orgs.list');
        }
    }

    /**
     * Handle cache invalidation for OrgDeleted event.
     *
     * Invalidates:
     * - Specific organisation data
     * - Organisation list cache
     * - All user caches (they're no longer in this org)
     */
    private function handleOrgDeleted(object $event): void
    {
        if (isset($event->org_id)) {
            $this->cacheService->invalidateOrganisation($event->org_id);
            $this->cacheService->forget('orgs.list');
        }
    }
}
