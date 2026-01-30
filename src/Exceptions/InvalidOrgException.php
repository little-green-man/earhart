<?php

namespace LittleGreenMan\Earhart\Exceptions;

class InvalidOrgException extends PropelAuthException
{
    public static function notFound(string $orgId): self
    {
        return new self("Organization '{$orgId}' not found", 404, ['org_id' => $orgId]);
    }

    public static function invalidRole(string $role): self
    {
        return new self("Invalid role '{$role}' for organization", 400, ['role' => $role]);
    }

    public static function userNotInOrg(string $userId, string $orgId): self
    {
        return new self("User '{$userId}' is not a member of organization '{$orgId}'", 403, [
            'user_id' => $userId,
            'org_id' => $orgId,
        ]);
    }
}
