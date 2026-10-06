<?php

namespace LittleGreenMan\Earhart\PropelAuth\Insights;

use Carbon\CarbonImmutable;

/**
 * One user in a user report.
 */
class UserReportRecord
{
    /**
     * @param  list<array{orgId: string, displayName: string, userRole: string}>  $orgs
     * @param  array<string, mixed>  $extraProperties  Report-specific values, e.g. num_invites for the top inviter report
     */
    public function __construct(
        public string $userId,
        public string $email,
        public CarbonImmutable $userCreatedAt,
        public ?CarbonImmutable $lastActiveAt = null,
        public ?string $username = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public array $orgs = [],
        public array $extraProperties = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  camelCase API fields
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['userId'],
            email: $data['email'],
            userCreatedAt: CarbonImmutable::createFromTimestamp($data['userCreatedAt']),
            lastActiveAt: isset($data['lastActiveAt']) ? CarbonImmutable::createFromTimestamp($data['lastActiveAt']) : null,
            username: $data['username'] ?? null,
            firstName: $data['firstName'] ?? null,
            lastName: $data['lastName'] ?? null,
            orgs: array_map(fn (array $org) => [
                'orgId' => $org['orgId'],
                'displayName' => $org['displayName'],
                'userRole' => $org['userRole'],
            ], $data['orgData'] ?? []),
            extraProperties: $data['extraProperties'] ?? [],
        );
    }
}
