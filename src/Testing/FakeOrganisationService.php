<?php

namespace LittleGreenMan\Earhart\Testing;

use Illuminate\Support\Str;
use LittleGreenMan\Earhart\Exceptions\InvalidOrgException;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\PropelAuth\OrganisationData;
use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;
use LittleGreenMan\Earhart\PropelAuth\SamlSpMetadata;
use LittleGreenMan\Earhart\PropelAuth\ScimGroup;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Services\OrganisationService;

/**
 * In-memory OrganisationService used by Earhart::fake().
 */
class FakeOrganisationService extends OrganisationService
{
    use RecordsCalls;

    public function __construct(FakeState $state)
    {
        parent::__construct('fake-api-key', 'https://auth.example.test', new CacheService(false));
        $this->state = $state;
    }

    /**
     * Store an organisation without recording a call. Returns its attributes.
     *
     * @param  array<string, mixed>  $attributes  API fields in camelCase; the display name is `name`
     * @return array<string, mixed>
     */
    public function store(array $attributes = []): array
    {
        $orgId = $attributes['orgId'] ?? (string) Str::uuid();
        $this->state->members[$orgId] ??= [];

        return $this->state->orgs[$orgId] = array_merge([
            'orgId' => $orgId,
            'name' => "Organisation {$orgId}",
            'urlSafeOrgSlug' => $orgId,
            'canSetupSaml' => false,
            'isSamlConfigured' => false,
            'isSamlInTestMode' => false,
            'customRoleMappingName' => 'Default',
            'createdAt' => now()->getTimestamp(),
            'metadata' => [],
            'isolated' => false,
            'domain' => null,
            'extraDomains' => [],
            'domainAutojoin' => false,
            'domainRestrict' => false,
            'maxUsers' => null,
            'legacyOrgId' => null,
        ], $attributes, ['orgId' => $orgId]);
    }

    /**
     * Add a user to an organisation without recording a call.
     */
    public function storeMember(string $orgId, string $userId, string $role = 'Member'): void
    {
        $this->state->members[$orgId][$userId] = $role;
    }

    public function getOrganisation(string $orgId, bool $fresh = false): OrganisationData
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId) {
            $this->requireOrg($orgId);

            return OrganisationData::fromArray($this->state->orgs[$orgId]);
        });
    }

    public function queryOrganisations(
        ?string $orderBy = null,
        int $pageNumber = 0,
        int $pageSize = 100,
        ?string $name = null,
        ?string $legacyOrgId = null,
        ?string $domain = null,
    ): PaginatedResult {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orderBy, $pageNumber, $pageSize, $name, $legacyOrgId, $domain) {
            $orgs = array_filter($this->state->orgs, fn (array $org) => ($name === null || str_contains(strtolower($org['name']), strtolower($name)))
                && ($legacyOrgId === null || $org['legacyOrgId'] === $legacyOrgId)
                && ($domain === null || $org['domain'] === $domain));

            $items = array_values(array_map(fn (array $org) => OrganisationData::fromArray($org), $orgs));

            return PaginatedResult::from(
                $this->state->page($items, $pageNumber, $pageSize),
                fn (int $nextPage) => $this->queryOrganisations($orderBy, $nextPage, $pageSize, $name, $legacyOrgId, $domain),
            );
        });
    }

    public function getOrganisationUsers(
        string $orgId,
        int $pageSize = 100,
        int $pageNumber = 0,
        ?string $role = null,
        bool $includeOrgs = false,
    ): PaginatedResult {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId, $pageSize, $pageNumber, $role, $includeOrgs) {
            $this->requireOrg($orgId);

            $members = array_filter($this->state->members[$orgId], fn (string $r) => $role === null || $r === $role);

            $items = array_map(
                fn (string $userId) => $this->state->userData($userId, $includeOrgs, $members[$userId]),
                array_keys($members),
            );

            return PaginatedResult::from(
                $this->state->page($items, $pageNumber, $pageSize),
                fn (int $nextPage) => $this->getOrganisationUsers($orgId, $pageSize, $nextPage, $role, $includeOrgs),
            );
        });
    }

    public function createOrganisation(
        string $name,
        ?string $domain = null,
        ?bool $enableAutoJoiningByDomain = null,
        ?bool $membersMustHaveMatchingDomain = null,
        ?int $maxUsers = null,
        ?string $legacyOrgId = null,
        ?string $customRoleMappingName = null,
    ): string {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->store(array_filter([
            'name' => $name,
            'domain' => $domain,
            'domainAutojoin' => $enableAutoJoiningByDomain,
            'domainRestrict' => $membersMustHaveMatchingDomain,
            'maxUsers' => $maxUsers,
            'legacyOrgId' => $legacyOrgId,
            'customRoleMappingName' => $customRoleMappingName,
        ], fn ($v) => $v !== null))['orgId']);
    }

    public function updateOrganisation(
        string $orgId,
        ?string $name = null,
        ?array $metadata = null,
        ?string $domain = null,
        ?array $extraDomains = null,
        ?bool $autojoinByDomain = null,
        ?bool $restrictToDomain = null,
        ?int $maxUsers = null,
        ?bool $canSetupSaml = null,
        ?string $legacyOrgId = null,
        ?string $ssoTrustLevel = null,
        \DateTimeInterface|string|null $require2faBy = null,
        ?bool $passwordRotationEnabled = null,
        ?int $passwordRotationHistorySize = null,
        ?int $passwordRotationPeriod = null,
    ): bool {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($orgId, array_filter([
            'name' => $name,
            'metadata' => $metadata,
            'domain' => $domain,
            'extraDomains' => $extraDomains,
            'domainAutojoin' => $autojoinByDomain,
            'domainRestrict' => $restrictToDomain,
            'maxUsers' => $maxUsers,
            'canSetupSaml' => $canSetupSaml,
            'legacyOrgId' => $legacyOrgId,
            'passwordRotationEnabled' => $passwordRotationEnabled,
            'passwordRotationHistorySize' => $passwordRotationHistorySize,
            'passwordRotationPeriod' => $passwordRotationPeriod,
        ], fn ($v) => $v !== null)));
    }

    public function deleteOrganisation(string $orgId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId) {
            $this->requireOrg($orgId);

            unset($this->state->orgs[$orgId], $this->state->members[$orgId]);
            $this->state->invites = array_values(array_filter($this->state->invites, fn (array $invite) => $invite['orgId'] !== $orgId));

            return true;
        });
    }

    public function addUserToOrganisation(string $orgId, string $userId, string $role, array $additionalRoles = []): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId, $userId, $role) {
            if (! isset($this->state->orgs[$orgId], $this->state->users[$userId])) {
                throw $this->notFound(__FUNCTION__);
            }

            $this->storeMember($orgId, $userId, $role);

            return true;
        });
    }

    public function inviteUserToOrganisation(string $orgId, string $email, string $role, array $additionalRoles = []): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId, $email, $role, $additionalRoles) {
            $this->requireOrg($orgId);

            $this->state->invites[] = [
                'inviteeEmail' => $email,
                'orgId' => $orgId,
                'orgName' => $this->state->orgs[$orgId]['name'],
                'roleInOrg' => $role,
                'additionalRolesInOrg' => $additionalRoles,
                'createdAt' => now()->getTimestamp(),
                'expiresAt' => now()->addDays(5)->getTimestamp(),
                'inviterEmail' => null,
                'inviterUserId' => null,
            ];

            return true;
        });
    }

    public function removeUserFromOrganisation(string $orgId, string $userId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId, $userId) {
            $this->requireMember($orgId, $userId, __FUNCTION__);

            unset($this->state->members[$orgId][$userId]);

            return true;
        });
    }

    public function changeUserRole(string $orgId, string $userId, string $role, array $additionalRoles = []): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId, $userId, $role) {
            $this->requireMember($orgId, $userId, __FUNCTION__);

            $this->storeMember($orgId, $userId, $role);

            return true;
        });
    }

    public function getRoleMappings(): array
    {
        return $this->fake(__FUNCTION__, [], function () {
            $counts = array_count_values(array_column($this->state->orgs, 'customRoleMappingName'));

            return array_map(
                fn (string $name, int $count) => ['customRoleMappingName' => $name, 'numOrgsSubscribed' => $count],
                array_keys($counts),
                $counts,
            );
        });
    }

    public function subscribeOrgToRoleMapping(string $orgId, string $mappingName): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($orgId, ['customRoleMappingName' => $mappingName]));
    }

    public function getPendingInvites(?string $orgId = null, int $pageSize = 10, int $pageNumber = 0): PaginatedResult
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId, $pageSize, $pageNumber) {
            $items = array_values(array_filter($this->state->invites, fn (array $invite) => $orgId === null || $invite['orgId'] === $orgId));

            return PaginatedResult::from(
                $this->state->page($items, $pageNumber, $pageSize),
                fn (int $nextPage) => $this->getPendingInvites($orgId, $pageSize, $nextPage),
            );
        });
    }

    public function revokePendingInvite(string $orgId, string $inviteeEmail): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId, $inviteeEmail) {
            $remaining = array_values(array_filter(
                $this->state->invites,
                fn (array $invite) => ! ($invite['orgId'] === $orgId && strcasecmp($invite['inviteeEmail'], $inviteeEmail) === 0),
            ));

            if (count($remaining) === count($this->state->invites)) {
                throw $this->notFound(__FUNCTION__);
            }

            $this->state->invites = $remaining;

            return true;
        });
    }

    public function allowOrgToSetupSAML(string $orgId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($orgId, ['canSetupSaml' => true]));
    }

    public function disallowOrgToSetupSAML(string $orgId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($orgId, ['canSetupSaml' => false]));
    }

    public function createSAMLConnectionLink(string $orgId, ?int $expiresInSeconds = null): string
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId) {
            $this->requireOrg($orgId);

            return "https://auth.example.test/saml/{$orgId}";
        });
    }

    public function fetchSAMLMetadata(string $orgId): SamlSpMetadata
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId) {
            $this->requireOrg($orgId);

            return new SamlSpMetadata(
                entityId: "https://auth.example.test/saml/{$orgId}/metadata",
                acsUrl: "https://auth.example.test/saml/{$orgId}/acs",
                logoutUrl: "https://auth.example.test/saml/{$orgId}/logout",
            );
        });
    }

    public function setSAMLIdPMetadata(
        string $orgId,
        string $idpEntityId,
        string $idpSsoUrl,
        string $idpCertificate,
        string $provider,
    ): bool {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($orgId, [
            'isSamlConfigured' => true,
            'isSamlInTestMode' => true,
        ]));
    }

    public function enableSAMLConnection(string $orgId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($orgId, ['isSamlInTestMode' => false]));
    }

    public function deleteSAMLConnection(string $orgId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($orgId, [
            'isSamlConfigured' => false,
            'isSamlInTestMode' => false,
        ]));
    }

    public function migrateOrgToIsolated(string $orgId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($orgId, ['isolated' => true]));
    }

    public function inviteUserToOrganisationById(string $orgId, string $userId, string $role, array $additionalRoles = []): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId, $userId, $role, $additionalRoles) {
            if (! isset($this->state->orgs[$orgId], $this->state->users[$userId])) {
                throw $this->notFound(__FUNCTION__);
            }

            $this->state->invites[] = [
                'inviteeEmail' => $this->state->users[$userId]['email'],
                'orgId' => $orgId,
                'orgName' => $this->state->orgs[$orgId]['name'],
                'roleInOrg' => $role,
                'additionalRolesInOrg' => $additionalRoles,
                'createdAt' => now()->getTimestamp(),
                'expiresAt' => now()->addDays(5)->getTimestamp(),
                'inviterEmail' => null,
                'inviterUserId' => null,
            ];

            return true;
        });
    }

    public function setOIDCIdPMetadata(
        string $orgId,
        string $clientId,
        string $clientSecret,
        string $idpType,
        bool $usesPkce = true,
        ?string $oktaSsoDomain = null,
        ?string $entraTenantId = null,
        ?string $authUrl = null,
        ?string $tokenUrl = null,
        ?string $userinfoUrl = null,
    ): bool {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($orgId, [
            'isSamlConfigured' => true,
            'isSamlInTestMode' => true,
        ]));
    }

    public function getScimGroups(string $orgId, ?string $userId = null, int $pageSize = 10, int $pageNumber = 0): PaginatedResult
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId, $userId, $pageSize, $pageNumber) {
            $this->requireOrg($orgId);

            $groups = array_filter(
                $this->state->scimGroups[$orgId] ?? [],
                fn (array $group) => $userId === null || in_array($userId, $group['members'], true),
            );

            $items = array_map(
                fn (string $groupId, array $group) => new ScimGroup($groupId, $group['displayName'], $group['externalIdFromIdp']),
                array_keys($groups),
                $groups,
            );

            return PaginatedResult::from(
                $this->state->page($items, $pageNumber, $pageSize),
                fn (int $nextPage) => $this->getScimGroups($orgId, $userId, $pageSize, $nextPage),
            );
        });
    }

    public function getScimGroup(string $orgId, string $groupId, ?int $membersPageSize = null, ?int $membersPageNumber = null): ScimGroup
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($orgId, $groupId, $membersPageSize, $membersPageNumber) {
            $group = $this->state->scimGroups[$orgId][$groupId] ?? throw InvalidOrgException::notFound($orgId);
            $members = $membersPageSize === null
                ? $group['members']
                : array_slice($group['members'], ($membersPageNumber ?? 0) * $membersPageSize, $membersPageSize);

            return new ScimGroup($groupId, $group['displayName'], $group['externalIdFromIdp'], $members);
        });
    }

    protected function requireOrg(string $orgId): void
    {
        if (! isset($this->state->orgs[$orgId])) {
            throw InvalidOrgException::notFound($orgId);
        }
    }

    protected function requireMember(string $orgId, string $userId, string $method): void
    {
        if (! isset($this->state->members[$orgId][$userId])) {
            throw $this->notFound($method);
        }
    }

    protected function notFound(string $method): PropelAuthException
    {
        return PropelAuthException::forStatus(404, "PropelAuth API error: 404 on {$method} (fake)");
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    protected function set(string $orgId, array $changes): bool
    {
        $this->requireOrg($orgId);
        $this->state->orgs[$orgId] = array_merge($this->state->orgs[$orgId], $changes);

        return true;
    }
}
