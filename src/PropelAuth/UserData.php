<?php

namespace LittleGreenMan\Earhart\PropelAuth;

use Carbon\Carbon;
use LittleGreenMan\Earhart\CarbonFromTimestampCast;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

class UserData extends Data
{
    use HasOrgMemberships;

    /**
     * @param  array<string, OrgMemberInfo>  $orgs  Memberships keyed by org ID. Empty unless fetched with orgs included.
     * @param  array<string, mixed>|null  $metadata
     * @param  ?string  $roleInOrg  The user's role, when listed by getOrganisationUsers()
     */
    public function __construct(
        public string $userId,
        public string $email,
        public bool $emailConfirmed,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $username,
        public string $pictureUrl,
        public array $properties,
        public bool $locked,
        public bool $enabled,
        public bool $hasPassword,
        public bool $updatePasswordRequired,
        public bool $mfaEnabled,
        public bool $canCreateOrgs,
        #[WithCast(CarbonFromTimestampCast::class)]
        public \DateTime $createdAt,
        #[WithCast(CarbonFromTimestampCast::class)]
        public \DateTime $lastActiveAt,
        public array $orgs = [],
        public ?array $metadata = null,
        public ?string $legacyUserId = null,
        public ?string $roleInOrg = null,
    ) {}

    /**
     * Create a UserData instance from an API response (camelCase keys).
     *
     * Memberships are read from `orgIdToOrgInfo`, PropelAuth's `org_id_to_org_info`.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['userId'],
            email: $data['email'],
            emailConfirmed: $data['emailConfirmed'],
            firstName: $data['firstName'] ?? null,
            lastName: $data['lastName'] ?? null,
            username: $data['username'] ?? null,
            pictureUrl: $data['pictureUrl'],
            properties: $data['properties'] ?? [],
            locked: $data['locked'] ?? false,
            enabled: $data['enabled'] ?? true,
            hasPassword: $data['hasPassword'] ?? false,
            updatePasswordRequired: $data['updatePasswordRequired'] ?? false,
            mfaEnabled: $data['mfaEnabled'] ?? false,
            canCreateOrgs: $data['canCreateOrgs'] ?? false,
            createdAt: Carbon::createFromTimestamp($data['createdAt']),
            lastActiveAt: Carbon::createFromTimestamp($data['lastActiveAt']),
            orgs: OrgMemberInfo::mapFromArray($data['orgIdToOrgInfo'] ?? $data['orgs'] ?? []),
            metadata: $data['metadata'] ?? null,
            legacyUserId: $data['legacyUserId'] ?? null,
            roleInOrg: $data['roleInOrg'] ?? null,
        );
    }

    public static function normalizers(): array
    {
        return [];
    }

    public static function transformers(): array
    {
        return [];
    }
}
