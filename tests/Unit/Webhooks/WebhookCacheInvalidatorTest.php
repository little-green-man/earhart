<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Webhooks;

use LittleGreenMan\Earhart\Events\PropelAuth\OrgCreated;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgDeleted;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgUpdated;
use LittleGreenMan\Earhart\Events\PropelAuth\UserAddedToOrg;
use LittleGreenMan\Earhart\Events\PropelAuth\UserCreated;
use LittleGreenMan\Earhart\Events\PropelAuth\UserDeleted;
use LittleGreenMan\Earhart\Events\PropelAuth\UserDisabled;
use LittleGreenMan\Earhart\Events\PropelAuth\UserEnabled;
use LittleGreenMan\Earhart\Events\PropelAuth\UserLocked;
use LittleGreenMan\Earhart\Events\PropelAuth\UserRemovedFromOrg;
use LittleGreenMan\Earhart\Events\PropelAuth\UserRoleChangedWithinOrg;
use LittleGreenMan\Earhart\Events\PropelAuth\UserUpdated;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Tests\TestCase;
use LittleGreenMan\Earhart\Webhooks\WebhookCacheInvalidator;
use Mockery;

class WebhookCacheInvalidatorTest extends TestCase
{
    private WebhookCacheInvalidator $invalidator;

    private CacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheService = Mockery::mock(CacheService::class);
        $this->invalidator = new WebhookCacheInvalidator($this->cacheService);
    }

    /**
     * Test handling user.created event
     */
    public function test_handle_user_created_event()
    {
        $event = new UserCreated([
            'event_type' => 'user.created',
            'user_id' => 'user_123',
            'email' => 'john@example.com',
            'email_confirmed' => true,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'picture_url' => null,
        ]);

        // UserCreated invalidates user list cache
        $this->cacheService
            ->shouldReceive('forget')
            ->with('users.list')
            ->once();

        $this->invalidator->handleEvent($event);
    }

    /**
     * Test handling user.updated event
     */
    public function test_handle_user_updated_event()
    {
        $event = new UserUpdated([
            'event_type' => 'user.updated',
            'user_id' => 'user_123',
        ]);

        $this->cacheService
            ->shouldReceive('invalidateUser')
            ->with('user_123')
            ->once();
        $this->cacheService
            ->shouldReceive('forget')
            ->with('users.list')
            ->once();

        $this->invalidator->handleEvent($event);
    }

    /**
     * Test handling user.deleted event
     */
    public function test_handle_user_deleted_event()
    {
        $event = new UserDeleted([
            'event_type' => 'user.deleted',
            'user_id' => 'user_123',
            'email' => 'john@example.com',
        ]);

        $this->cacheService
            ->shouldReceive('invalidateUser')
            ->with('user_123')
            ->once();
        $this->cacheService
            ->shouldReceive('forget')
            ->with('users.list')
            ->once();

        $this->invalidator->handleEvent($event);
    }

    /**
     * Test handling user.enabled event
     */
    public function test_handle_user_enabled_event()
    {
        $event = new UserEnabled([
            'event_type' => 'user.enabled',
            'user_id' => 'user_123',
        ]);

        $this->cacheService
            ->shouldReceive('invalidateUser')
            ->with('user_123')
            ->once();

        $this->invalidator->handleEvent($event);
    }

    /**
     * Test handling user.disabled event
     */
    public function test_handle_user_disabled_event()
    {
        $event = new UserDisabled([
            'event_type' => 'user.disabled',
            'user_id' => 'user_123',
        ]);

        $this->cacheService
            ->shouldReceive('invalidateUser')
            ->with('user_123')
            ->once();

        $this->invalidator->handleEvent($event);
    }

    /**
     * Test handling user.locked event
     */
    public function test_handle_user_locked_event()
    {
        $event = new UserLocked([
            'event_type' => 'user.locked',
            'user_id' => 'user_123',
        ]);

        $this->cacheService
            ->shouldReceive('invalidateUser')
            ->with('user_123')
            ->once();

        $this->invalidator->handleEvent($event);
    }

    /**
     * Test handling user.added_to_org event
     */
    public function test_handle_user_added_to_org_event()
    {
        $event = new UserAddedToOrg([
            'event_type' => 'user.added_to_org',
            'user_id' => 'user_123',
            'org_id' => 'org_456',
            'role' => 'member',
        ]);

        $this->cacheService
            ->shouldReceive('invalidateUser')
            ->with('user_123')
            ->once();
        $this->cacheService
            ->shouldReceive('invalidateOrganisation')
            ->with('org_456')
            ->once();
        $this->cacheService
            ->shouldReceive('forget')
            ->with('org.org_456.members')
            ->once();

        $this->invalidator->handleEvent($event);
    }

    /**
     * Test handling user.removed_from_org event
     */
    public function test_handle_user_removed_from_org_event()
    {
        $event = new UserRemovedFromOrg([
            'event_type' => 'user.removed_from_org',
            'removed_user_id' => 'user_123',
            'org_id' => 'org_456',
        ]);

        $this->cacheService
            ->shouldReceive('invalidateUser')
            ->with('user_123')
            ->once();
        $this->cacheService
            ->shouldReceive('invalidateOrganisation')
            ->with('org_456')
            ->once();
        $this->cacheService
            ->shouldReceive('forget')
            ->with('org.org_456.members')
            ->once();

        $this->invalidator->handleEvent($event);
    }

    /**
     * Test handling user.role_changed_within_org event
     */
    public function test_handle_user_role_changed_within_org_event()
    {
        $event = new UserRoleChangedWithinOrg([
            'event_type' => 'user.role_changed_within_org',
            'user_id' => 'user_123',
            'org_id' => 'org_456',
            'new_role' => 'admin',
        ]);

        $this->cacheService
            ->shouldReceive('invalidateUser')
            ->with('user_123')
            ->once();
        $this->cacheService
            ->shouldReceive('invalidateOrganisation')
            ->with('org_456')
            ->once();

        $this->invalidator->handleEvent($event);
    }

    /**
     * Test handling org.created event
     */
    public function test_handle_org_created_event()
    {
        $event = new OrgCreated([
            'event_type' => 'org.created',
            'org_id' => 'org_456',
            'name' => 'Acme Corp',
        ]);

        $this->cacheService
            ->shouldReceive('forget')
            ->with('orgs.list')
            ->once();

        $this->invalidator->handleEvent($event);
    }

    /**
     * Test handling org.updated event
     */
    public function test_handle_org_updated_event()
    {
        $event = new OrgUpdated([
            'event_type' => 'org.updated',
            'org_id' => 'org_456',
        ]);

        $this->cacheService
            ->shouldReceive('invalidateOrganisation')
            ->with('org_456')
            ->once();
        $this->cacheService
            ->shouldReceive('forget')
            ->with('orgs.list')
            ->once();

        $this->invalidator->handleEvent($event);
    }

    /**
     * Test handling org.deleted event
     */
    public function test_handle_org_deleted_event()
    {
        $event = new OrgDeleted([
            'event_type' => 'org.deleted',
            'org_id' => 'org_456',
        ]);

        $this->cacheService
            ->shouldReceive('invalidateOrganisation')
            ->with('org_456')
            ->once();
        $this->cacheService
            ->shouldReceive('forget')
            ->with('orgs.list')
            ->once();

        $this->invalidator->handleEvent($event);
    }

    /**
     * Test handling unknown event type
     */
    public function test_handle_unknown_event_gracefully()
    {
        $event = new \stdClass;
        $event->event_type = 'unknown.event';

        // Should not throw exception, just do nothing
        $this->invalidator->handleEvent($event);

        // No cache methods should be called
        $this->cacheService->shouldNotHaveReceived('invalidateUser');
        $this->cacheService->shouldNotHaveReceived('invalidateOrganisation');
    }

    /**
     * Test multiple cache invalidations for single event
     */
    public function test_multiple_cache_invalidations_for_org_membership_change()
    {
        $event = new UserAddedToOrg([
            'event_type' => 'user.added_to_org',
            'user_id' => 'user_123',
            'org_id' => 'org_456',
            'role' => 'member',
        ]);

        // Should invalidate both user and org caches
        $this->cacheService
            ->shouldReceive('invalidateUser')
            ->with('user_123')
            ->once();
        $this->cacheService
            ->shouldReceive('invalidateOrganisation')
            ->with('org_456')
            ->once();
        $this->cacheService
            ->shouldReceive('forget')
            ->with('org.org_456.members')
            ->once();

        $this->invalidator->handleEvent($event);
    }

    /**
     * Test invalidator handles multiple events
     */
    public function test_invalidator_handles_multiple_events()
    {
        $userEvent = new UserUpdated([
            'event_type' => 'user.updated',
            'user_id' => 'user_123',
        ]);

        $orgEvent = new OrgUpdated([
            'event_type' => 'org.updated',
            'org_id' => 'org_456',
        ]);

        $this->cacheService
            ->shouldReceive('invalidateUser')
            ->with('user_123')
            ->once();
        $this->cacheService
            ->shouldReceive('forget')
            ->with('users.list')
            ->once();
        $this->cacheService
            ->shouldReceive('invalidateOrganisation')
            ->with('org_456')
            ->once();
        $this->cacheService
            ->shouldReceive('forget')
            ->with('orgs.list')
            ->once();

        $this->invalidator->handleEvent($userEvent);
        $this->invalidator->handleEvent($orgEvent);
    }

    /**
     * Test handling event with missing user_id doesn't cause errors
     */
    public function test_handle_event_with_missing_user_id()
    {
        $event = new \stdClass;
        $event->event_type = 'UserUpdated';
        // Missing user_id property

        // Should not throw exception
        $this->invalidator->handleEvent($event);

        // No user invalidation should occur
        $this->cacheService->shouldNotHaveReceived('invalidateUser');
    }

    /**
     * Test handling event with missing org_id doesn't cause errors
     */
    public function test_handle_event_with_missing_org_id()
    {
        $event = new \stdClass;
        $event->event_type = 'OrgUpdated';
        // Missing org_id property

        // Should not throw exception
        $this->invalidator->handleEvent($event);

        // No org invalidation should occur
        $this->cacheService->shouldNotHaveReceived('invalidateOrganisation');
    }

    /**
     * Test all supported events are handled
     */
    public function test_all_supported_event_types_handled()
    {
        $events = [
            'user.created' => new UserCreated([
                'event_type' => 'user.created',
                'user_id' => 'u1',
                'email' => 'a@b.com',
                'email_confirmed' => true,
                'first_name' => 'A',
                'last_name' => 'B',
                'username' => 'ab',
                'picture_url' => null,
            ]),
            'user.updated' => new UserUpdated([
                'event_type' => 'user.updated',
                'user_id' => 'u1',
            ]),
            'user.deleted' => new UserDeleted([
                'event_type' => 'user.deleted',
                'user_id' => 'u1',
                'email' => 'a@b.com',
            ]),
            'user.enabled' => new UserEnabled([
                'event_type' => 'user.enabled',
                'user_id' => 'u1',
            ]),
            'user.disabled' => new UserDisabled([
                'event_type' => 'user.disabled',
                'user_id' => 'u1',
            ]),
            'user.locked' => new UserLocked([
                'event_type' => 'user.locked',
                'user_id' => 'u1',
            ]),
            'user.added_to_org' => new UserAddedToOrg([
                'event_type' => 'user.added_to_org',
                'user_id' => 'u1',
                'org_id' => 'o1',
                'role' => 'member',
            ]),
            'user.removed_from_org' => new UserRemovedFromOrg([
                'event_type' => 'user.removed_from_org',
                'removed_user_id' => 'u1',
                'org_id' => 'o1',
            ]),
            'user.role_changed_within_org' => new UserRoleChangedWithinOrg([
                'event_type' => 'user.role_changed_within_org',
                'user_id' => 'u1',
                'org_id' => 'o1',
                'new_role' => 'admin',
            ]),
            'org.created' => new OrgCreated([
                'event_type' => 'org.created',
                'org_id' => 'o1',
                'name' => 'Org',
            ]),
            'org.updated' => new OrgUpdated([
                'event_type' => 'org.updated',
                'org_id' => 'o1',
            ]),
            'org.deleted' => new OrgDeleted([
                'event_type' => 'org.deleted',
                'org_id' => 'o1',
            ]),
        ];

        // Setup cache service to accept any calls
        $this->cacheService->shouldReceive('invalidateUser')->zeroOrMoreTimes();
        $this->cacheService->shouldReceive('invalidateOrganisation')->zeroOrMoreTimes();
        $this->cacheService->shouldReceive('forget')->zeroOrMoreTimes();

        foreach ($events as $eventType => $event) {
            // Should not throw exception
            $this->invalidator->handleEvent($event);
        }
    }
}
