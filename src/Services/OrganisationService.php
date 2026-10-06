<?php

namespace LittleGreenMan\Earhart\Services;

use LittleGreenMan\Earhart\Exceptions\InvalidOrgException;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\PropelAuth\OrganisationData;
use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;
use LittleGreenMan\Earhart\PropelAuth\SamlSpMetadata;
use LittleGreenMan\Earhart\PropelAuth\ScimGroup;
use LittleGreenMan\Earhart\PropelAuth\UserData;

class OrganisationService extends BaseApiService
{
    /**
     * Fetch organisation by ID.
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getOrganisation(string $orgId, bool $fresh = false): OrganisationData
    {
        if (! $fresh && $this->cache->isEnabled()) {
            return $this->cache->get("org.{$orgId}", fn () => $this->fetchOrgFromAPI($orgId));
        }

        return $this->fetchOrgFromAPI($orgId);
    }

    /**
     * Query organisations with pagination.
     *
     * @param  ?string  $orderBy  CREATED_AT_ASC, CREATED_AT_DESC or NAME
     *
     * @throws PropelAuthException On any API failure
     */
    public function queryOrganisations(
        ?string $orderBy = null,
        int $pageNumber = 0,
        int $pageSize = 100,
        ?string $name = null,
        ?string $legacyOrgId = null,
        ?string $domain = null,
    ): PaginatedResult {
        $params = array_filter(
            [
                'orderBy' => $orderBy,
                'pageNumber' => $pageNumber,
                'pageSize' => $pageSize,
                'name' => $name,
                'legacyOrgId' => $legacyOrgId,
                'domain' => $domain,
            ],
            fn ($v) => $v !== null,
        );

        $response = $this->makeRequest('GET', '/api/backend/v1/org/query', $params);

        // Convert arrays to OrganisationData objects for consistency
        // Note: 'orgs' array is protected from auto-conversion, so manually convert each org
        $orgs = array_map(
            fn ($org) => OrganisationData::fromArray($this->toCamelCase($org)),
            $response['orgs'] ?? []
        );
        $response['items'] = $orgs;

        return PaginatedResult::from($response, fn (int $nextPage) => $this->queryOrganisations(
            $orderBy,
            $nextPage,
            $pageSize,
            $name,
            $legacyOrgId,
            $domain,
        ));
    }

    /**
     * Fetch users in organisation. Each user's `roleInOrg` holds their role in this organisation.
     *
     * @param  ?string  $role  Only return users with this role
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getOrganisationUsers(
        string $orgId,
        int $pageSize = 100,
        int $pageNumber = 0,
        ?string $role = null,
        bool $includeOrgs = false,
    ): PaginatedResult {
        $response = $this->makeRequest('GET', "/api/backend/v1/user/org/{$orgId}", array_filter([
            'pageSize' => $pageSize,
            'pageNumber' => $pageNumber,
            'role' => $role,
            'includeOrgs' => $includeOrgs,
        ], fn ($v) => $v !== null), fn () => InvalidOrgException::notFound($orgId));

        // Convert arrays to UserData objects for consistency
        $users = array_map(
            fn ($user) => UserData::fromArray($user),
            $response['users'] ?? []
        );
        $response['items'] = $users;

        return PaginatedResult::from($response, fn (int $nextPage) => $this->getOrganisationUsers($orgId, $pageSize, $nextPage, $role, $includeOrgs));
    }

    /**
     * Create a new organisation. To set metadata, follow up with updateOrganisation().
     *
     * @param  ?bool  $enableAutoJoiningByDomain  Let users with a matching email domain join without an invite
     * @param  ?bool  $membersMustHaveMatchingDomain  Only allow members whose email domain matches
     *
     * @throws PropelAuthException On any API failure
     */
    public function createOrganisation(
        string $name,
        ?string $domain = null,
        ?bool $enableAutoJoiningByDomain = null,
        ?bool $membersMustHaveMatchingDomain = null,
        ?int $maxUsers = null,
        ?string $legacyOrgId = null,
        ?string $customRoleMappingName = null,
    ): string {
        $payload = array_filter(
            [
                'name' => $name,
                'domain' => $domain,
                'enableAutoJoiningByDomain' => $enableAutoJoiningByDomain,
                'membersMustHaveMatchingDomain' => $membersMustHaveMatchingDomain,
                'maxUsers' => $maxUsers,
                'legacyOrgId' => $legacyOrgId,
                'customRoleMappingName' => $customRoleMappingName,
            ],
            fn ($v) => $v !== null,
        );

        $response = $this->makeRequest('POST', '/api/backend/v1/org/', $payload);

        return $response['orgId'];
    }

    /**
     * Update organisation. Only the arguments you pass are changed.
     *
     * @param  ?bool  $autojoinByDomain  Let users with a matching email domain join without an invite
     * @param  ?bool  $restrictToDomain  Only allow members whose email domain matches
     * @param  ?string  $ssoTrustLevel  e.g. NeverTrust
     * @param  \DateTimeInterface|string|null  $require2faBy  A date, or a string like "2026-01-20 12:34:56 UTC"
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
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
        if ($require2faBy instanceof \DateTimeInterface) {
            $require2faBy = \DateTimeImmutable::createFromInterface($require2faBy)
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s').' UTC';
        }

        $payload = array_filter(
            [
                'name' => $name,
                'metadata' => $metadata,
                'domain' => $domain,
                'extraDomains' => $extraDomains,
                'autojoinByDomain' => $autojoinByDomain,
                'restrictToDomain' => $restrictToDomain,
                'maxUsers' => $maxUsers,
                'canSetupSaml' => $canSetupSaml,
                'legacyOrgId' => $legacyOrgId,
                'ssoTrustLevel' => $ssoTrustLevel,
                // Snake case given directly: the converter would produce require2fa_by
                'require_2fa_by' => $require2faBy,
                'passwordRotationEnabled' => $passwordRotationEnabled,
                'passwordRotationHistorySize' => $passwordRotationHistorySize,
                'passwordRotationPeriod' => $passwordRotationPeriod,
            ],
            fn ($v) => $v !== null,
        );

        $this->makeRequest('PUT', "/api/backend/v1/org/{$orgId}", $payload, fn () => InvalidOrgException::notFound($orgId));
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Delete organisation.
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function deleteOrganisation(string $orgId): bool
    {
        $this->makeRequest('DELETE', "/api/backend/v1/org/{$orgId}", notFound: fn () => InvalidOrgException::notFound($orgId));

        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Add user to organisation.
     *
     * @throws PropelAuthException On any API failure
     */
    public function addUserToOrganisation(string $orgId, string $userId, string $role, array $additionalRoles = []): bool
    {
        $payload = array_filter(
            [
                'orgId' => $orgId,
                'userId' => $userId,
                'role' => $role,
                'additionalRoles' => $additionalRoles,
            ],
            fn ($v) => $v !== [],
        );

        $this->makeRequest('POST', '/api/backend/v1/org/add_user', $payload);
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Invite user to organisation.
     *
     * @throws PropelAuthException On any API failure
     */
    public function inviteUserToOrganisation(string $orgId, string $email, string $role, array $additionalRoles = []): bool
    {
        $payload = array_filter(
            [
                'orgId' => $orgId,
                'email' => $email,
                'role' => $role,
                'additionalRoles' => $additionalRoles,
            ],
            fn ($v) => $v !== [],
        );

        $this->makeRequest('POST', '/api/backend/v1/invite_user', $payload);

        return true;
    }

    /**
     * Remove user from organisation.
     *
     * @throws PropelAuthException On any API failure
     */
    public function removeUserFromOrganisation(string $orgId, string $userId): bool
    {
        $this->makeRequest('POST', '/api/backend/v1/org/remove_user', [
            'orgId' => $orgId,
            'userId' => $userId,
        ]);
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Change user role in organisation.
     *
     * @throws PropelAuthException On any API failure
     */
    public function changeUserRole(string $orgId, string $userId, string $role, array $additionalRoles = []): bool
    {
        $this->makeRequest('POST', '/api/backend/v1/org/change_role', array_filter([
            'orgId' => $orgId,
            'userId' => $userId,
            'role' => $role,
            'additionalRoles' => $additionalRoles,
        ], fn ($v) => $v !== []));
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Get role mappings (called role configurations in the dashboard).
     *
     * @return list<array{customRoleMappingName: string, numOrgsSubscribed: int}>
     *
     * @throws PropelAuthException On any API failure
     */
    public function getRoleMappings(): array
    {
        $response = $this->makeRequest('GET', '/api/backend/v1/custom_role_mappings');

        return $response['customRoleMappings'] ?? [];
    }

    /**
     * Subscribe organisation to role mapping.
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function subscribeOrgToRoleMapping(string $orgId, string $mappingName): bool
    {
        $this->makeRequest('PUT', "/api/backend/v1/org/{$orgId}", [
            'customRoleMappingName' => $mappingName,
        ], fn () => InvalidOrgException::notFound($orgId));
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Get pending invites, for one organisation or all.
     *
     * Each item has inviteeEmail, orgId, orgName, roleInOrg, additionalRolesInOrg,
     * createdAt, expiresAt, inviterEmail and inviterUserId.
     *
     * @throws PropelAuthException On any API failure
     */
    public function getPendingInvites(?string $orgId = null, int $pageSize = 10, int $pageNumber = 0): PaginatedResult
    {
        $response = $this->makeRequest('GET', '/api/backend/v1/pending_org_invites', array_filter([
            'orgId' => $orgId,
            'pageSize' => $pageSize,
            'pageNumber' => $pageNumber,
        ], fn ($v) => $v !== null));

        return PaginatedResult::from(
            $response,
            fn (int $nextPage) => $this->getPendingInvites($orgId, $pageSize, $nextPage),
        );
    }

    /**
     * Revoke pending invite.
     *
     * @throws PropelAuthException On any API failure
     */
    public function revokePendingInvite(string $orgId, string $inviteeEmail): bool
    {
        $this->makeRequest('DELETE', '/api/backend/v1/pending_org_invites', [
            'orgId' => $orgId,
            'inviteeEmail' => $inviteeEmail,
        ]);

        return true;
    }

    /**
     * Allow organisation to setup SAML.
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function allowOrgToSetupSAML(string $orgId): bool
    {
        $this->makeRequest('POST', "/api/backend/v1/org/{$orgId}/allow_saml", notFound: fn () => InvalidOrgException::notFound($orgId));
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Disallow organisation to setup SAML.
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function disallowOrgToSetupSAML(string $orgId): bool
    {
        $this->makeRequest('POST', "/api/backend/v1/org/{$orgId}/disallow_saml", notFound: fn () => InvalidOrgException::notFound($orgId));
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Create SAML connection link.
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function createSAMLConnectionLink(string $orgId, ?int $expiresInSeconds = null): string
    {
        $response = $this->makeRequest(
            'POST',
            "/api/backend/v1/org/{$orgId}/create_saml_connection_link",
            array_filter(['expiresInSeconds' => $expiresInSeconds], fn ($v) => $v !== null),
            fn () => InvalidOrgException::notFound($orgId),
        );

        return $response['url'] ?? '';
    }

    /**
     * Fetch the SAML service-provider details (entity ID, ACS URL, logout URL) to give the organisation's IdP.
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function fetchSAMLMetadata(string $orgId): SamlSpMetadata
    {
        $response = $this->makeRequest('GET', "/api/backend/v1/saml_sp_metadata/{$orgId}", notFound: fn () => InvalidOrgException::notFound($orgId));

        return SamlSpMetadata::fromArray($response);
    }

    /**
     * Set the organisation's SAML identity provider details.
     *
     * @param  string  $provider  The IdP, e.g. Okta, Azure, Google, Rippling, OneLogin, JumpCloud, Duo or Generic
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function setSAMLIdPMetadata(
        string $orgId,
        string $idpEntityId,
        string $idpSsoUrl,
        string $idpCertificate,
        string $provider,
    ): bool {
        $this->makeRequest('POST', '/api/backend/v1/saml_idp_metadata', [
            'orgId' => $orgId,
            'idpEntityId' => $idpEntityId,
            'idpSsoUrl' => $idpSsoUrl,
            'idpCertificate' => $idpCertificate,
            'provider' => $provider,
        ], fn () => InvalidOrgException::notFound($orgId));
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Enable SAML connection.
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function enableSAMLConnection(string $orgId): bool
    {
        $this->makeRequest('POST', "/api/backend/v1/saml_idp_metadata/go_live/{$orgId}", notFound: fn () => InvalidOrgException::notFound($orgId));
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Delete SAML connection.
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function deleteSAMLConnection(string $orgId): bool
    {
        $this->makeRequest('DELETE', "/api/backend/v1/saml_idp_metadata/{$orgId}", notFound: fn () => InvalidOrgException::notFound($orgId));
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Migrate organisation to isolated.
     *
     * Documented by PropelAuth but not used by their official SDKs, so less proven than other calls.
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function migrateOrgToIsolated(string $orgId): bool
    {
        $this->makeRequest('POST', '/api/backend/v1/isolate_org', [
            'orgId' => $orgId,
        ], fn () => InvalidOrgException::notFound($orgId));
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Invite an existing user to an organisation by user ID.
     *
     * @param  list<string>  $additionalRoles
     *
     * @throws PropelAuthException On any API failure, with status 404 if the user or organisation does not exist
     */
    public function inviteUserToOrganisationById(string $orgId, string $userId, string $role, array $additionalRoles = []): bool
    {
        $this->makeRequest('POST', '/api/backend/v1/invite_user_by_id', array_filter([
            'orgId' => $orgId,
            'userId' => $userId,
            'role' => $role,
            'additionalRoles' => $additionalRoles,
        ], fn ($v) => $v !== []));

        return true;
    }

    /**
     * Set up an organisation's SSO with an OIDC identity provider.
     *
     * @param  string  $idpType  Okta (needs $oktaSsoDomain), Azure (needs $entraTenantId) or Generic (needs the three URLs)
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
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
        $this->makeRequest('POST', '/api/backend/v1/oidc_idp_metadata', array_filter([
            'orgId' => $orgId,
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'usesPkce' => $usesPkce,
            'idpType' => $idpType,
            'oktaSsoDomain' => $oktaSsoDomain,
            'entraTenantId' => $entraTenantId,
            'authUrl' => $authUrl,
            'tokenUrl' => $tokenUrl,
            'userinfoUrl' => $userinfoUrl,
        ], fn ($v) => $v !== null), fn () => InvalidOrgException::notFound($orgId));
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * List an organisation's SCIM groups, optionally only those a user belongs to.
     *
     * @return PaginatedResult Items are ScimGroup, without members
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getScimGroups(string $orgId, ?string $userId = null, int $pageSize = 10, int $pageNumber = 0): PaginatedResult
    {
        $response = $this->makeRequest('GET', "/api/backend/v1/scim/{$orgId}/groups", array_filter([
            'userId' => $userId,
            'pageSize' => $pageSize,
            'pageNumber' => $pageNumber,
        ], fn ($v) => $v !== null), fn () => InvalidOrgException::notFound($orgId));

        $total = $response['totalGroups'] ?? 0;
        $currentPage = $response['pageNumber'] ?? $pageNumber;
        $size = $response['pageSize'] ?? $pageSize;

        return PaginatedResult::from([
            'items' => array_map(fn (array $group) => ScimGroup::fromArray($group), $response['groups'] ?? []),
            'totalUsers' => $total,
            'currentPage' => $currentPage,
            'pageSize' => $size,
            'hasMoreResults' => ($currentPage + 1) * $size < $total,
        ], fn (int $nextPage) => $this->getScimGroups($orgId, $userId, $pageSize, $nextPage));
    }

    /**
     * Fetch one SCIM group with a page of its members.
     *
     * @throws InvalidOrgException If the organisation or group does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getScimGroup(string $orgId, string $groupId, ?int $membersPageSize = null, ?int $membersPageNumber = null): ScimGroup
    {
        $response = $this->makeRequest('GET', "/api/backend/v1/scim/{$orgId}/groups/{$groupId}", array_filter([
            'membersPageSize' => $membersPageSize,
            'membersPageNumber' => $membersPageNumber,
        ], fn ($v) => $v !== null), fn () => InvalidOrgException::notFound($orgId));

        return ScimGroup::fromArray($response);
    }

    // Protected helper methods

    /**
     * Fetch organisation from API (bypasses cache).
     */
    protected function fetchOrgFromAPI(string $orgId): OrganisationData
    {
        $response = $this->makeRequest('GET', "/api/backend/v1/org/{$orgId}", notFound: fn () => InvalidOrgException::notFound($orgId));

        return OrganisationData::fromArray($response);
    }
}
