<?php

namespace LittleGreenMan\Earhart\PropelAuth;

use Carbon\CarbonImmutable;

/**
 * The verified claims of a PropelAuth access token. Built locally, with no API call.
 */
class AccessToken
{
    use HasOrgMemberships;

    /**
     * @param  array<string, OrgMemberInfo>  $orgs  Keyed by org ID
     * @param  array<string, mixed>  $properties
     * @param  array<string, mixed>  $claims  Every claim in the token
     */
    public function __construct(
        public string $userId,
        public ?string $email,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $username,
        public ?string $legacyUserId,
        public ?string $impersonatorUserId,
        public array $properties,
        public array $orgs,
        public ?string $activeOrgId,
        public CarbonImmutable $expiresAt,
        public array $claims,
    ) {}

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function fromClaims(array $claims): self
    {
        $orgs = $claims['org_id_to_org_member_info'] ?? [];
        $activeOrg = $claims['org_member_info'] ?? null;

        if ($activeOrg !== null) {
            $orgs = [$activeOrg['org_id'] => $activeOrg];
        }

        return new self(
            userId: $claims['user_id'],
            email: $claims['email'] ?? null,
            firstName: $claims['first_name'] ?? null,
            lastName: $claims['last_name'] ?? null,
            username: $claims['username'] ?? null,
            legacyUserId: $claims['legacy_user_id'] ?? null,
            impersonatorUserId: $claims['impersonator_user_id'] ?? null,
            properties: $claims['properties'] ?? [],
            orgs: OrgMemberInfo::mapFromArray($orgs),
            activeOrgId: $activeOrg['org_id'] ?? null,
            expiresAt: CarbonImmutable::createFromTimestamp($claims['exp']),
            claims: $claims,
        );
    }

    public function isImpersonated(): bool
    {
        return $this->impersonatorUserId !== null;
    }
}
