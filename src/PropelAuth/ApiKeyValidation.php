<?php

namespace LittleGreenMan\Earhart\PropelAuth;

/**
 * The result of validating an end-user API key: who it belongs to.
 *
 * A personal key has a user and no org. An org key has an org, and a user
 * (with their membership in $userInOrg) if it was created for a user in the org.
 */
class ApiKeyValidation
{
    /**
     * @param  array<string, mixed>|null  $metadata  The metadata stored with the key
     */
    public function __construct(
        public ?array $metadata = null,
        public ?UserData $user = null,
        public ?OrganisationData $org = null,
        public ?OrgMemberInfo $userInOrg = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  camelCase API fields
     */
    public static function fromArray(array $data): self
    {
        return new self(
            metadata: $data['metadata'] ?? null,
            user: isset($data['user']) ? UserData::fromArray($data['user']) : null,
            org: isset($data['org']) ? OrganisationData::fromArray($data['org']) : null,
            userInOrg: isset($data['userInOrg']) ? OrgMemberInfo::fromArray($data['userInOrg']) : null,
        );
    }

    public function isPersonal(): bool
    {
        return $this->user !== null && $this->org === null;
    }

    public function isOrg(): bool
    {
        return $this->org !== null;
    }
}
