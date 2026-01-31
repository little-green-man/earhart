<?php

namespace LittleGreenMan\Earhart\Services;

use LittleGreenMan\Earhart\Exceptions\InvalidOrgException;
use LittleGreenMan\Earhart\PropelAuth\OrganisationData;
use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;
use LittleGreenMan\Earhart\PropelAuth\UserData;

class OrganisationService extends BaseApiService
{
    /**
     * Fetch organisation by ID.
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
     */
    public function getOrganisationUsers(string $orgId, int $pageSize = 100): PaginatedResult
    {
        $response = $this->makeRequest('GET', "/api/backend/v1/user/org/{$orgId}", [
            'pageSize' => $pageSize,
            'includeOrgs' => false,
        ]);

        // Convert arrays to UserData objects for consistency
        $users = array_map(
            fn ($user) => UserData::fromArray($user),
            $response['users'] ?? []
        );
        $response['items'] = $users;

        return PaginatedResult::from($response, fn (int $nextPage) => $this->getOrganisationUsers($orgId, $pageSize));
    }

    /**
     * Create a new organisation.
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

        $this->makeRequest('PUT', "/api/backend/v1/org/{$orgId}", $payload);
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Delete organisation.
     */
    public function deleteOrganisation(string $orgId): bool
    {
        $response = $this->makeRequest('DELETE', "/api/backend/v1/org/{$orgId}");

        if (($response['status'] ?? 200) === 404) {
            throw InvalidOrgException::notFound($orgId);
        }

        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Add user to organisation.
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
     */
    public function getRoleMappings(): array
    {
        $response = $this->makeRequest('GET', '/api/backend/v1/custom_role_mappings');

        return $response['roleMappings'] ?? [];
    }

    /**
     * Subscribe organisation to role mapping.
     */
    public function subscribeOrgToRoleMapping(string $orgId, string $mappingId): bool
    {
        $this->makeRequest('PUT', "/api/backend/v1/org/{$orgId}", [
            'customRoleMappingId' => $mappingId,
        ]);
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Get pending invites.
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
     */
    public function allowOrgToSetupSAML(string $orgId): bool
    {
        $this->makeRequest('POST', "/api/backend/v1/org/{$orgId}/allow_saml");
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Disallow organisation to setup SAML.
     */
    public function disallowOrgToSetupSAML(string $orgId): bool
    {
        $this->makeRequest('POST', "/api/backend/v1/org/{$orgId}/disallow_saml");
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Create SAML connection link.
     */
    public function createSAMLConnectionLink(string $orgId): string
    {
        $response = $this->makeRequest('POST', "/api/backend/v1/org/{$orgId}/create_saml_connection_link");

        return $response['url'] ?? '';
    }

    /**
     * Fetch SAML SP metadata.
     */
    public function fetchSAMLMetadata(string $orgId): string
    {
        $response = $this->makeRequest('GET', "/api/backend/v1/saml_sp_metadata/{$orgId}");

        return $response['metadata'] ?? '';
    }

    /**
     * Set SAML IdP metadata.
     */
    public function setSAMLIdPMetadata(string $orgId, string $metadataXml): bool
    {
        $this->makeRequest('POST', '/api/backend/v1/saml_idp_metadata', [
            'orgId' => $orgId,
            'idpMetadata' => $metadataXml,
        ]);
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Enable SAML connection.
     */
    public function enableSAMLConnection(string $orgId): bool
    {
        $this->makeRequest('POST', "/api/backend/v1/saml_idp_metadata/go_live/{$orgId}");
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Delete SAML connection.
     */
    public function deleteSAMLConnection(string $orgId): bool
    {
        $this->makeRequest('DELETE', "/api/backend/v1/saml_idp_metadata/{$orgId}");
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    /**
     * Migrate organisation to isolated.
     */
    public function migrateOrgToIsolated(string $orgId): bool
    {
        $this->makeRequest('POST', '/api/backend/v1/isolate_org', [
            'orgId' => $orgId,
        ]);
        $this->cache->invalidateOrganisation($orgId);

        return true;
    }

    // Protected helper methods

    /**
     * Fetch organisation from API (bypasses cache).
     */
    protected function fetchOrgFromAPI(string $orgId): OrganisationData
    {
        $response = $this->makeRequest('GET', "/api/backend/v1/org/{$orgId}");

        if (($response['status'] ?? 200) === 404) {
            throw InvalidOrgException::notFound($orgId);
        }

        return OrganisationData::fromArray($response);
    }
}
