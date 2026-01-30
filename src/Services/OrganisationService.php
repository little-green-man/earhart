<?php

namespace LittleGreenMan\Earhart\Services;

use Illuminate\Support\Facades\Http;
use LittleGreenMan\Earhart\Exceptions\InvalidOrgException;
use LittleGreenMan\Earhart\Exceptions\RateLimitException;
use LittleGreenMan\Earhart\PropelAuth\OrganisationData;
use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;

class OrganisationService
{
    private int $maxRetries = 3;

    private int $initialRetryDelay = 1;

    public function __construct(
        protected string $apiKey,
        protected string $authUrl,
        protected CacheService $cache,
    ) {}

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
    public function addUserToOrganisation(string $orgId, string $email, ?string $role = null): bool
    {
        $payload = array_filter(
            [
                'orgId' => $orgId,
                'userEmail' => $email,
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
    public function revokePendingInvite(string $inviteId): bool
    {
        $this->makeRequest('DELETE', '/api/backend/v1/pending_org_invites', [
            'inviteId' => $inviteId,
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

    /**
     * Make an HTTP request with retry logic.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        return $this->executeWithRetry(fn () => $this->sendRequest($method, $endpoint, $data));
    }

    /**
     * Send HTTP request to PropelAuth API.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sendRequest(string $method, string $endpoint, array $data = []): array
    {
        $request = Http::withToken($this->apiKey)->withHeaders(['Content-Type' => 'application/json'])->timeout(30);

        $response = match ($method) {
            'GET' => $request->get($this->authUrl.$endpoint, $data),
            'POST' => $request->post($this->authUrl.$endpoint, $data),
            'PUT' => $request->put($this->authUrl.$endpoint, $data),
            'DELETE' => $request->delete($this->authUrl.$endpoint),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        if ($response->status() === 429) {
            throw RateLimitException::fromHeaders($response->header('Retry-After'));
        }

        // Allow 404 to pass through - let callers handle it
        // But throw for other error responses
        if ($response->failed() && $response->status() !== 404) {
            throw new \Exception("PropelAuth API error: {$response->status()} - {$response->body()}");
        }

        $json = $response->json();
        if (! is_array($json)) {
            $json = [];
        }

        return $json + ['status' => $response->status()];
    }

    /**
     * Execute request with automatic retry logic.
     */
    protected function executeWithRetry(\Closure $callback): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            try {
                return $callback();
            } catch (RateLimitException $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt >= $this->maxRetries) {
                    break;
                }

                $delay = $this->initialRetryDelay * (2 ** ($attempt - 1));
                sleep($delay);
            }
        }

        throw $lastException ?? new \Exception('Max retries exceeded');
    }
}
