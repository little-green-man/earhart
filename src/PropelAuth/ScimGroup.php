<?php

namespace LittleGreenMan\Earhart\PropelAuth;

/**
 * A group provisioned into an organisation by its identity provider over SCIM.
 */
class ScimGroup
{
    /**
     * @param  list<string>  $memberUserIds  Empty when listed with getScimGroups(); filled by getScimGroup()
     */
    public function __construct(
        public string $groupId,
        public string $displayName,
        public ?string $externalIdFromIdp = null,
        public array $memberUserIds = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  camelCase API fields
     */
    public static function fromArray(array $data): self
    {
        return new self(
            groupId: $data['groupId'],
            displayName: $data['displayName'],
            externalIdFromIdp: $data['externalIdFromIdp'] ?? null,
            memberUserIds: array_column($data['members'] ?? [], 'userId'),
        );
    }
}
