<?php

namespace LittleGreenMan\Earhart\PropelAuth;

/**
 * Organisation helpers for anything with an `$orgs` map of OrgMemberInfo keyed by org ID.
 */
trait HasOrgMemberships
{
    public function org(string $orgId): ?OrgMemberInfo
    {
        return $this->orgs[$orgId] ?? null;
    }

    public function isMemberOf(string $orgId): bool
    {
        return isset($this->orgs[$orgId]);
    }

    public function roleIn(string $orgId): ?string
    {
        return $this->org($orgId)?->userRole;
    }

    public function isRoleIn(string $orgId, string $role): bool
    {
        return $this->org($orgId)?->isRole($role) ?? false;
    }

    public function isAtLeastRoleIn(string $orgId, string $role): bool
    {
        return $this->org($orgId)?->isAtLeastRole($role) ?? false;
    }

    public function hasPermissionIn(string $orgId, string $permission): bool
    {
        return $this->org($orgId)?->hasPermission($permission) ?? false;
    }
}
