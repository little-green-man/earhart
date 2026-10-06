<?php

namespace LittleGreenMan\Earhart\PropelAuth;

/**
 * A user's membership of one organisation, from `org_id_to_org_info` in API
 * responses or `org_id_to_org_member_info` in access tokens.
 */
class OrgMemberInfo
{
    /**
     * @param  list<string>  $inheritedRoles  The user's role plus every role it inherits from
     * @param  list<string>  $userPermissions
     * @param  list<string>  $additionalRoles
     * @param  array<string, mixed>  $orgMetadata
     */
    public function __construct(
        public string $orgId,
        public string $orgName,
        public string $userRole,
        public array $inheritedRoles = [],
        public array $userPermissions = [],
        public array $additionalRoles = [],
        public array $orgMetadata = [],
        public ?string $urlSafeOrgName = null,
        public ?string $legacyOrgId = null,
    ) {
        if ($this->inheritedRoles === []) {
            $this->inheritedRoles = array_values(array_unique([$userRole, ...$additionalRoles]));
        }
    }

    /**
     * Build from PropelAuth's snake_case fields (camelCase keys are accepted too).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $get = fn (string $snake, string $camel, mixed $default = null) => $data[$snake] ?? $data[$camel] ?? $default;

        return new self(
            orgId: $get('org_id', 'orgId'),
            orgName: $get('org_name', 'orgName', ''),
            userRole: $get('user_role', 'userRole', ''),
            inheritedRoles: $data['inherited_user_roles_plus_current_role'] ?? $data['inheritedUserRolesPlusCurrentRole'] ?? $data['inheritedRoles'] ?? [],
            userPermissions: $get('user_permissions', 'userPermissions', []),
            additionalRoles: $get('additional_roles', 'additionalRoles', []),
            orgMetadata: $get('org_metadata', 'orgMetadata', []) ?? [],
            urlSafeOrgName: $get('url_safe_org_name', 'urlSafeOrgName'),
            legacyOrgId: $get('legacy_org_id', 'legacyOrgId'),
        );
    }

    /**
     * Build a map keyed by org ID from `org_id_to_org_info`, or from a list of entries.
     *
     * @param  array<array-key, array<string, mixed>|self>  $orgs
     * @return array<string, self>
     */
    public static function mapFromArray(array $orgs): array
    {
        $map = [];

        foreach ($orgs as $org) {
            $info = $org instanceof self ? $org : self::fromArray($org);
            $map[$info->orgId] = $info;
        }

        return $map;
    }

    /**
     * Whether the user's role in this organisation is exactly $role.
     */
    public function isRole(string $role): bool
    {
        return strcasecmp($this->userRole, $role) === 0
            || in_array(strtolower($role), array_map('strtolower', $this->additionalRoles), true);
    }

    /**
     * Whether the user has $role or a role that inherits it (e.g. an Owner is at least a Member).
     */
    public function isAtLeastRole(string $role): bool
    {
        return in_array(strtolower($role), array_map('strtolower', $this->inheritedRoles), true);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->userPermissions, true);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        return array_diff($permissions, $this->userPermissions) === [];
    }
}
