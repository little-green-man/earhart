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
use LittleGreenMan\Earhart\Tests\TestCase;
use LittleGreenMan\Earhart\Webhooks\WebhookEventParser;

class WebhookEventParserTest extends TestCase
{
    private WebhookEventParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new WebhookEventParser;
    }

    /**
     * Test parsing a user.created event
     */
    public function test_parse_user_created_event()
    {
        $payload = [
            'event_type' => 'user.created',
            'user_id' => 'user_123',
            'email' => 'john@example.com',
            'email_confirmed' => true,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'picture_url' => 'https://example.com/pic.jpg',
        ];

        $event = $this->parser->parse($payload);

        $this->assertInstanceOf(UserCreated::class, $event);
        $this->assertEquals('user_123', $event->user_id);
        $this->assertEquals('john@example.com', $event->email);
        $this->assertTrue($event->email_confirmed);
    }

    /**
     * Test parsing a user.updated event
     */
    public function test_parse_user_updated_event()
    {
        $payload = [
            'event_type' => 'user.updated',
            'user_id' => 'user_123',
            'email' => 'jane@example.com',
            'email_confirmed' => false,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'username' => 'janesmith',
            'picture_url' => null,
        ];

        $event = $this->parser->parse($payload);

        $this->assertInstanceOf(UserUpdated::class, $event);
        $this->assertEquals('user_123', $event->user_id);
    }

    /**
     * Test parsing a user.deleted event
     */
    public function test_parse_user_deleted_event()
    {
        $payload = [
            'event_type' => 'user.deleted',
            'user_id' => 'user_123',
            'email' => 'john@example.com',
        ];

        $event = $this->parser->parse($payload);

        $this->assertInstanceOf(UserDeleted::class, $event);
        $this->assertEquals('user_123', $event->user_id);
    }

    /**
     * Test parsing a user.enabled event
     */
    public function test_parse_user_enabled_event()
    {
        $payload = [
            'event_type' => 'user.enabled',
            'user_id' => 'user_123',
        ];

        $event = $this->parser->parse($payload);

        $this->assertInstanceOf(UserEnabled::class, $event);
        $this->assertEquals('user_123', $event->user_id);
    }

    /**
     * Test parsing a user.disabled event
     */
    public function test_parse_user_disabled_event()
    {
        $payload = [
            'event_type' => 'user.disabled',
            'user_id' => 'user_123',
        ];

        $event = $this->parser->parse($payload);

        $this->assertInstanceOf(UserDisabled::class, $event);
        $this->assertEquals('user_123', $event->user_id);
    }

    /**
     * Test parsing a user.locked event
     */
    public function test_parse_user_locked_event()
    {
        $payload = [
            'event_type' => 'user.locked',
            'user_id' => 'user_123',
        ];

        $event = $this->parser->parse($payload);

        $this->assertInstanceOf(UserLocked::class, $event);
        $this->assertEquals('user_123', $event->user_id);
    }

    /**
     * Test parsing a user.added_to_org event
     */
    public function test_parse_user_added_to_org_event()
    {
        $payload = [
            'event_type' => 'user.added_to_org',
            'user_id' => 'user_123',
            'org_id' => 'org_456',
            'role' => 'member',
        ];

        $event = $this->parser->parse($payload);

        $this->assertInstanceOf(UserAddedToOrg::class, $event);
        $this->assertEquals('user_123', $event->user_id);
        $this->assertEquals('org_456', $event->org_id);
    }

    /**
     * Test parsing a user.removed_from_org event
     */
    public function test_parse_user_removed_from_org_event()
    {
        $payload = [
            'event_type' => 'user.removed_from_org',
            'removed_user_id' => 'user_123',
            'org_id' => 'org_456',
        ];

        $event = $this->parser->parse($payload);

        $this->assertInstanceOf(UserRemovedFromOrg::class, $event);
        $this->assertEquals('user_123', $event->removed_user_id);
        $this->assertEquals('org_456', $event->org_id);
    }

    /**
     * Test parsing a user.role_changed_within_org event
     */
    public function test_parse_user_role_changed_within_org_event()
    {
        $payload = [
            'event_type' => 'user.role_changed_within_org',
            'user_id' => 'user_123',
            'org_id' => 'org_456',
            'old_role' => 'member',
            'new_role' => 'admin',
        ];

        $event = $this->parser->parse($payload);

        $this->assertInstanceOf(UserRoleChangedWithinOrg::class, $event);
        $this->assertEquals('user_123', $event->user_id);
        $this->assertEquals('org_456', $event->org_id);
        $this->assertEquals('admin', $event->new_role);
    }

    /**
     * Test parsing an org.created event
     */
    public function test_parse_org_created_event()
    {
        $payload = [
            'event_type' => 'org.created',
            'org_id' => 'org_456',
            'name' => 'Acme Corp',
        ];

        $event = $this->parser->parse($payload);

        $this->assertInstanceOf(OrgCreated::class, $event);
        $this->assertEquals('org_456', $event->org_id);
        $this->assertEquals('Acme Corp', $event->name);
    }

    /**
     * Test parsing an org.updated event
     */
    public function test_parse_org_updated_event()
    {
        $payload = [
            'event_type' => 'org.updated',
            'org_id' => 'org_456',
            'name' => 'Acme Corp Updated',
        ];

        $event = $this->parser->parse($payload);

        $this->assertInstanceOf(OrgUpdated::class, $event);
        $this->assertEquals('org_456', $event->org_id);
    }

    /**
     * Test parsing an org.deleted event
     */
    public function test_parse_org_deleted_event()
    {
        $payload = [
            'event_type' => 'org.deleted',
            'org_id' => 'org_456',
        ];

        $event = $this->parser->parse($payload);

        $this->assertInstanceOf(OrgDeleted::class, $event);
        $this->assertEquals('org_456', $event->org_id);
    }

    /**
     * Test parsing returns null for unsupported event types
     */
    public function test_parse_unsupported_event_returns_null()
    {
        $payload = [
            'event_type' => 'org.scim_key_created',
        ];

        $event = $this->parser->parse($payload);

        $this->assertNull($event);
    }

    /**
     * Test parsing throws exception for missing event_type
     */
    public function test_parse_throws_exception_for_missing_event_type()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('event_type');

        $this->parser->parse([
            'user_id' => 'user_123',
        ]);
    }

    /**
     * Test is_supported returns true for supported event types
     */
    public function test_is_supported_returns_true_for_supported_types()
    {
        $this->assertTrue($this->parser->isSupported('user.created'));
        $this->assertTrue($this->parser->isSupported('org.updated'));
        $this->assertTrue($this->parser->isSupported('user.role_changed_within_org'));
    }

    /**
     * Test is_supported returns false for unsupported event types
     */
    public function test_is_supported_returns_false_for_unsupported_types()
    {
        $this->assertFalse($this->parser->isSupported('org.scim_key_created'));
        $this->assertFalse($this->parser->isSupported('user.invited_to_org'));
        $this->assertFalse($this->parser->isSupported('nonexistent.event'));
    }

    /**
     * Test get_event_class returns correct class names
     */
    public function test_get_event_class_returns_correct_class_names()
    {
        $this->assertEquals(UserCreated::class, $this->parser->getEventClass('user.created'));
        $this->assertEquals(OrgUpdated::class, $this->parser->getEventClass('org.updated'));
        $this->assertEquals(
            UserRoleChangedWithinOrg::class,
            $this->parser->getEventClass('user.role_changed_within_org'),
        );
    }

    /**
     * Test get_event_class returns null for unsupported types
     */
    public function test_get_event_class_returns_null_for_unsupported_types()
    {
        $this->assertNull($this->parser->getEventClass('unknown.event'));
    }

    /**
     * Test get_supported_event_types returns all supported types
     */
    public function test_get_supported_event_types_returns_all_supported()
    {
        $supported = $this->parser->getSupportedEventTypes();

        $this->assertIsArray($supported);
        $this->assertCount(12, $supported);
        $this->assertContains('user.created', $supported);
        $this->assertContains('org.deleted', $supported);
        $this->assertContains('user.role_changed_within_org', $supported);
    }

    /**
     * Test parsing with minimal required fields
     */
    public function test_parse_with_minimal_fields()
    {
        $payload = [
            'event_type' => 'user.enabled',
            'user_id' => 'user_123',
        ];

        $event = $this->parser->parse($payload);

        $this->assertInstanceOf(UserEnabled::class, $event);
        $this->assertEquals('user_123', $event->user_id);
    }

    /**
     * Test parsing with extra fields (should be ignored)
     */
    public function test_parse_with_extra_fields()
    {
        $payload = [
            'event_type' => 'user.created',
            'user_id' => 'user_123',
            'email' => 'john@example.com',
            'email_confirmed' => true,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'picture_url' => 'https://example.com/pic.jpg',
            'extra_field' => 'should be ignored',
            'metadata' => ['key' => 'value'],
        ];

        $event = $this->parser->parse($payload);

        $this->assertInstanceOf(UserCreated::class, $event);
        $this->assertEquals('user_123', $event->user_id);
    }

    /**
     * Test parsing is case-sensitive for event types
     */
    public function test_parse_is_case_sensitive_for_event_types()
    {
        $payload = [
            'event_type' => 'USER.CREATED',
        ];

        $event = $this->parser->parse($payload);

        // Should return null for incorrect case
        $this->assertNull($event);
    }

    /**
     * Test parser instance can be reused
     */
    public function test_parser_can_be_reused_for_multiple_events()
    {
        $payload1 = [
            'event_type' => 'user.created',
            'user_id' => 'user_1',
            'email' => 'user1@example.com',
            'email_confirmed' => true,
            'first_name' => 'User',
            'last_name' => 'One',
            'username' => 'userone',
            'picture_url' => null,
        ];

        $payload2 = [
            'event_type' => 'org.created',
            'org_id' => 'org_1',
            'name' => 'Org One',
        ];

        $event1 = $this->parser->parse($payload1);
        $event2 = $this->parser->parse($payload2);

        $this->assertInstanceOf(UserCreated::class, $event1);
        $this->assertInstanceOf(OrgCreated::class, $event2);
        $this->assertEquals('user_1', $event1->user_id);
        $this->assertEquals('org_1', $event2->org_id);
    }

    /**
     * Test all supported event types can be parsed
     */
    public function test_all_supported_event_types_can_be_parsed()
    {
        $testData = [
            'user.created' => [
                'user_id' => 'u1',
                'email' => 'a@b.com',
                'email_confirmed' => true,
                'first_name' => 'A',
                'last_name' => 'B',
                'username' => 'ab',
                'picture_url' => null,
            ],
            'user.updated' => [
                'user_id' => 'u1',
                'email' => 'a@b.com',
                'email_confirmed' => true,
                'first_name' => 'A',
                'last_name' => 'B',
                'username' => 'ab',
                'picture_url' => null,
            ],
            'user.deleted' => ['user_id' => 'u1', 'email' => 'a@b.com'],
            'user.enabled' => ['user_id' => 'u1'],
            'user.disabled' => ['user_id' => 'u1'],
            'user.locked' => ['user_id' => 'u1'],
            'user.added_to_org' => ['user_id' => 'u1', 'org_id' => 'o1', 'role' => 'member'],
            'user.removed_from_org' => ['removed_user_id' => 'u1', 'org_id' => 'o1'],
            'user.role_changed_within_org' => [
                'user_id' => 'u1',
                'org_id' => 'o1',
                'old_role' => 'member',
                'new_role' => 'admin',
            ],
            'org.created' => ['org_id' => 'o1', 'name' => 'Org'],
            'org.updated' => ['org_id' => 'o1', 'name' => 'Org'],
            'org.deleted' => ['org_id' => 'o1'],
        ];

        foreach ($testData as $eventType => $data) {
            $payload = array_merge(['event_type' => $eventType], $data);
            $event = $this->parser->parse($payload);

            $this->assertNotNull($event, "Failed to parse event type: {$eventType}");
            $this->assertTrue($this->parser->isSupported($eventType));
        }
    }
}
