<?php

namespace LittleGreenMan\Earhart\Webhooks;

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

/**
 * Parses and routes PropelAuth webhook events to corresponding event classes.
 *
 * This parser transforms raw webhook payloads into strongly-typed event objects
 * that can be dispatched through Laravel's event system.
 */
class WebhookEventParser
{
    /**
     * Map of event types to their corresponding event class names.
     */
    private const EVENT_TYPE_MAP = [
        'org.created' => OrgCreated::class,
        'org.deleted' => OrgDeleted::class,
        'org.updated' => OrgUpdated::class,
        'user.added_to_org' => UserAddedToOrg::class,
        'user.created' => UserCreated::class,
        'user.deleted' => UserDeleted::class,
        'user.disabled' => UserDisabled::class,
        'user.enabled' => UserEnabled::class,
        'user.locked' => UserLocked::class,
        'user.removed_from_org' => UserRemovedFromOrg::class,
        'user.updated' => UserUpdated::class,
        'user.role_changed_within_org' => UserRoleChangedWithinOrg::class,
    ];

    /**
     * Parse a webhook payload and return the typed event object.
     *
     * @param  array  $payload  The raw webhook payload
     * @return object|null The typed event object, or null if event type is not supported
     *
     * @throws \InvalidArgumentException If payload is missing required fields
     */
    public function parse(array $payload): ?object
    {
        if (! isset($payload['event_type'])) {
            throw new \InvalidArgumentException('Webhook payload must contain an "event_type" field.');
        }

        $eventType = $payload['event_type'];

        // Return null for unsupported event types
        if (! isset(self::EVENT_TYPE_MAP[$eventType])) {
            return null;
        }

        $eventClass = self::EVENT_TYPE_MAP[$eventType];

        return new $eventClass($payload);
    }

    /**
     * Check if an event type is supported.
     *
     * @param  string  $eventType  The event type string
     * @return bool Whether the event type is supported
     */
    public function isSupported(string $eventType): bool
    {
        return isset(self::EVENT_TYPE_MAP[$eventType]);
    }

    /**
     * Get the event class name for a given event type.
     *
     * @param  string  $eventType  The event type string
     * @return string|null The fully qualified event class name, or null if not supported
     */
    public function getEventClass(string $eventType): ?string
    {
        return self::EVENT_TYPE_MAP[$eventType] ?? null;
    }

    /**
     * Get all supported event types.
     *
     * @return array Array of supported event type strings
     */
    public function getSupportedEventTypes(): array
    {
        return array_keys(self::EVENT_TYPE_MAP);
    }
}
