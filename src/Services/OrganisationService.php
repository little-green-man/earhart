<?php

namespace LittleGreenMan\Earhart\Services;

use LittleGreenMan\Earhart\Exceptions\InvalidOrgException;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\PropelAuth\OrganisationData;
use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;
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
     * @throws PropelAuthException On any API failure
     */
    public function queryOrganisations(
        ?string $orderBy = null,
        int $pageNumber = 0,
        int $pageSize = 100,
    ): PaginatedResult {
        $params = array_filter(
            [
                'orderBy' => $orderBy,
                'pageNumber' => $pageNumber,
                'pageSize' => $pageSize,
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
        ));
    }

    /**
     * Fetch users in organisation.
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getOrganisationUsers(string $orgId, int $pageSize = 100, int $pageNumber = 0): PaginatedResult
    {
        $response = $this->makeRequest('GET', "/api/backend/v1/user/org/{$orgId}", [
            'pageSize' => $pageSize,
            'pageNumber' => $pageNumber,
            'includeOrgs' => false,
        ], fn () => InvalidOrgException::notFound($orgId));

        // Convert arrays to UserData objects for consistency
        $users = array_map(
            fn ($user) => UserData::fromArray($user),
            $response['users'] ?? []
        );
        $response['items'] = $users;

        return PaginatedResult::from($response, fn (int $nextPage) => $this->getOrganisationUsers($orgId, $pageSize, $nextPage));
    }

    /**
     * Create a new organisation.
     *
     * @throws PropelAuthException On any API failure
     */
    public function createOrganisation(string $name, ?string $slug = null, ?array $metadata = null): string
    {
        $payload = array_filter(
            [
                'name' => $name,
                'urlSafeOrgSlug' => $slug,
                'metadata' => $metadata,
            ],
            fn ($v) => $v !== null,
        );

        $response = $this->makeRequest('POST', '/api/backend/v1/org/', $payload);

        return $response['orgId'];
    }

    /**
     * Update organisation.
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function updateOrganisation(string $orgId, ?string $name = null, ?array $metadata = null): bool
    {
        $payload = array_filter(
            [
                'name' => $name,
                'metadata' => $metadata,
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
    public function addUserToOrganisation(string $orgId, string $userId, ?string $role = null): bool
    {
        $payload = array_filter(
            [
                'orgId' => $orgId,
                'userId' => $userId,
                'role' => $role,
            ],
            fn ($v) => $v !== null,
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
    public function inviteUserToOrganisation(string $orgId, string $email, ?string $role = null): bool
    {
        $payload = array_filter(
            [
                'orgId' => $orgId,
                'email' => $email,
                'role' => $role,
            ],
            fn ($v) => $v !== null,
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
    public function changeUserRole(string $orgId, string $userId, string $role): bool
    {
        $this->makeRequest('POST', '/api/backend/v1/org/change_role', [
            'orgId' => $orgId,
            'userId' => $userId,
            'role' => $role,
        ]);
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Get role mappings.
     *
     * @throws PropelAuthException On any API failure
     */
    public function getRoleMappings(): array
    {
        $response = $this->makeRequest('GET', '/api/backend/v1/custom_role_mappings');

        return $response['roleMappings'] ?? [];
    }

    /**
     * Subscribe organisation to role mapping.
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function subscribeOrgToRoleMapping(string $orgId, string $mappingId): bool
    {
        $this->makeRequest('PUT', "/api/backend/v1/org/{$orgId}", [
            'customRoleMappingId' => $mappingId,
        ], fn () => InvalidOrgException::notFound($orgId));
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Get pending invites.
     *
     * @throws PropelAuthException On any API failure
     */
    public function getPendingInvites(?string $orgId = null): PaginatedResult
    {
        $params = $orgId ? ['orgId' => $orgId] : [];
        $response = $this->makeRequest('GET', '/api/backend/v1/pending_org_invites', $params);

        // Transform invites to items for PaginatedResult
        if (isset($response['invites'])) {
            $response['items'] = $response['invites'];
            unset($response['invites']);
        }

        return PaginatedResult::from($response, fn (int $nextPage) => $this->getPendingInvites($orgId));
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
    public function createSAMLConnectionLink(string $orgId): string
    {
        $response = $this->makeRequest('POST', "/api/backend/v1/org/{$orgId}/create_saml_connection_link", notFound: fn () => InvalidOrgException::notFound($orgId));

        return $response['url'] ?? '';
    }

    /**
     * Fetch SAML SP metadata.
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function fetchSAMLMetadata(string $orgId): string
    {
        $response = $this->makeRequest('GET', "/api/backend/v1/saml_sp_metadata/{$orgId}", notFound: fn () => InvalidOrgException::notFound($orgId));

        return $response['metadata'] ?? '';
    }

    /**
     * Set SAML IdP metadata.
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function setSAMLIdPMetadata(string $orgId, string $metadataXml): bool
    {
        $this->makeRequest('POST', '/api/backend/v1/saml_idp_metadata', [
            'orgId' => $orgId,
            'idpMetadata' => $metadataXml,
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
