<?php

namespace LittleGreenMan\Earhart\Services;

use LittleGreenMan\Earhart\Exceptions\ApiKeyRateLimitException;
use LittleGreenMan\Earhart\Exceptions\InvalidApiKeyException;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\Exceptions\ValidationException;
use LittleGreenMan\Earhart\PropelAuth\ApiKey;
use LittleGreenMan\Earhart\PropelAuth\ApiKeyValidation;
use LittleGreenMan\Earhart\PropelAuth\NewApiKey;
use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;

/**
 * API keys your users create to call your API (PropelAuth's "end-user API keys").
 *
 * These are separate from the PropelAuth API key Earhart itself uses. A key
 * belongs to a user (personal), an organisation, or a user within an organisation.
 */
class ApiKeyService extends BaseApiService
{
    /**
     * Create an API key. Pass $userId, $orgId, or both. Show the returned token to its owner once.
     *
     * @param  \DateTimeInterface|int|null  $expiresAt  A date or Unix timestamp; null never expires
     * @param  array<string, mixed>|null  $metadata
     *
     * @throws PropelAuthException On any API failure
     */
    public function createApiKey(
        ?string $userId = null,
        ?string $orgId = null,
        \DateTimeInterface|int|null $expiresAt = null,
        ?array $metadata = null,
        ?string $displayName = null,
    ): NewApiKey {
        $response = $this->makeRequest('POST', '/api/backend/v1/end_user_api_keys', $this->keyFields($userId, $orgId, $expiresAt, $metadata, $displayName));

        return new NewApiKey($response['apiKeyId'], $response['apiKeyToken']);
    }

    /**
     * Import an API key issued by another system, so it keeps working. Returns the new key's ID.
     *
     * @param  array<string, mixed>|null  $metadata
     *
     * @throws PropelAuthException On any API failure
     */
    public function importApiKey(
        #[\SensitiveParameter] string $importedApiKey,
        ?string $userId = null,
        ?string $orgId = null,
        \DateTimeInterface|int|null $expiresAt = null,
        ?array $metadata = null,
        ?string $displayName = null,
    ): string {
        $response = $this->makeRequest('POST', '/api/backend/v1/end_user_api_keys/import', [
            'importedApiKey' => $importedApiKey,
        ] + $this->keyFields($userId, $orgId, $expiresAt, $metadata, $displayName));

        return $response['apiKeyId'];
    }

    /**
     * @throws InvalidApiKeyException If the key does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getApiKey(string $apiKeyId): ApiKey
    {
        $response = $this->makeRequest(
            'GET',
            "/api/backend/v1/end_user_api_keys/{$apiKeyId}",
            notFound: fn () => InvalidApiKeyException::notFound($apiKeyId),
        );

        return ApiKey::fromArray($response);
    }

    /**
     * List keys that haven't expired or been deleted, filtered by owner.
     *
     * @return PaginatedResult Items are ApiKey
     *
     * @throws PropelAuthException On any API failure
     */
    public function getActiveApiKeys(?string $userId = null, ?string $orgId = null, ?string $userEmail = null, int $pageSize = 10, int $pageNumber = 0): PaginatedResult
    {
        return $this->listKeys('', $userId, $orgId, $userEmail, $pageSize, $pageNumber);
    }

    /**
     * List expired and deleted keys, filtered by owner.
     *
     * @return PaginatedResult Items are ApiKey
     *
     * @throws PropelAuthException On any API failure
     */
    public function getArchivedApiKeys(?string $userId = null, ?string $orgId = null, ?string $userEmail = null, int $pageSize = 10, int $pageNumber = 0): PaginatedResult
    {
        return $this->listKeys('/archived', $userId, $orgId, $userEmail, $pageSize, $pageNumber);
    }

    /**
     * Change a key's expiry or metadata. Only the arguments you pass are changed.
     *
     * @param  array<string, mixed>|null  $metadata
     *
     * @throws InvalidApiKeyException If the key does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function updateApiKey(
        string $apiKeyId,
        \DateTimeInterface|int|null $expiresAt = null,
        ?array $metadata = null,
        bool $neverExpire = false,
    ): bool {
        $this->makeRequest('PATCH', "/api/backend/v1/end_user_api_keys/{$apiKeyId}", array_filter([
            'expiresAtSeconds' => $this->timestamp($expiresAt),
            'metadata' => $metadata,
            'setToNeverExpire' => $neverExpire ?: null,
        ], fn ($v) => $v !== null), fn () => InvalidApiKeyException::notFound($apiKeyId));

        return true;
    }

    /**
     * Delete (archive) a key so it stops validating.
     *
     * @throws InvalidApiKeyException If the key does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function deleteApiKey(string $apiKeyId): bool
    {
        $this->makeRequest('DELETE', "/api/backend/v1/end_user_api_keys/{$apiKeyId}", notFound: fn () => InvalidApiKeyException::notFound($apiKeyId));

        return true;
    }

    /**
     * Validate a key presented to your API and return its owner. A "Bearer " prefix is stripped.
     *
     * @throws InvalidApiKeyException If the key is invalid, expired or deleted
     * @throws ApiKeyRateLimitException If the key has hit the rate limit set for it in PropelAuth
     * @throws PropelAuthException On any other API failure
     */
    public function validateApiKey(#[\SensitiveParameter] string $apiKeyToken): ApiKeyValidation
    {
        return $this->validate('/api/backend/v1/end_user_api_keys/validate', $apiKeyToken);
    }

    /**
     * Validate a key that must belong to a user rather than an organisation.
     *
     * @throws InvalidApiKeyException If the key is invalid or not personal
     * @throws ApiKeyRateLimitException If the key has hit its rate limit
     * @throws PropelAuthException On any other API failure
     */
    public function validatePersonalApiKey(#[\SensitiveParameter] string $apiKeyToken): ApiKeyValidation
    {
        $validation = $this->validateApiKey($apiKeyToken);

        return $validation->isPersonal() ? $validation : throw InvalidApiKeyException::notPersonal();
    }

    /**
     * Validate a key that must belong to an organisation.
     *
     * @throws InvalidApiKeyException If the key is invalid or not an organisation key
     * @throws ApiKeyRateLimitException If the key has hit its rate limit
     * @throws PropelAuthException On any other API failure
     */
    public function validateOrgApiKey(#[\SensitiveParameter] string $apiKeyToken): ApiKeyValidation
    {
        $validation = $this->validateApiKey($apiKeyToken);

        return $validation->isOrg() ? $validation : throw InvalidApiKeyException::notOrg();
    }

    /**
     * Validate a key that was brought in with importApiKey().
     *
     * @throws InvalidApiKeyException If the key is invalid
     * @throws ApiKeyRateLimitException If the key has hit its rate limit
     * @throws PropelAuthException On any other API failure
     */
    public function validateImportedApiKey(#[\SensitiveParameter] string $apiKeyToken): ApiKeyValidation
    {
        return $this->validate('/api/backend/v1/end_user_api_keys/validate_imported', $apiKeyToken);
    }

    /**
     * How many times keys were validated on a day, optionally for one key, user or organisation.
     *
     * @param  \DateTimeInterface|string  $date  A date, or a Y-m-d string
     *
     * @throws PropelAuthException On any API failure
     */
    public function getApiKeyUsage(\DateTimeInterface|string $date, ?string $apiKeyId = null, ?string $userId = null, ?string $orgId = null): int
    {
        $response = $this->makeRequest('GET', '/api/backend/v1/end_user_api_keys/usage', array_filter([
            'date' => $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date,
            'apiKeyId' => $apiKeyId,
            'userId' => $userId,
            'orgId' => $orgId,
        ], fn ($v) => $v !== null));

        return (int) ($response['count'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    protected function keyFields(?string $userId, ?string $orgId, \DateTimeInterface|int|null $expiresAt, ?array $metadata, ?string $displayName): array
    {
        return array_filter([
            'userId' => $userId,
            'orgId' => $orgId,
            'expiresAtSeconds' => $this->timestamp($expiresAt),
            'metadata' => $metadata,
            'displayName' => $displayName,
        ], fn ($v) => $v !== null);
    }

    protected function timestamp(\DateTimeInterface|int|null $time): ?int
    {
        return $time instanceof \DateTimeInterface ? $time->getTimestamp() : $time;
    }

    protected function listKeys(string $suffix, ?string $userId, ?string $orgId, ?string $userEmail, int $pageSize, int $pageNumber): PaginatedResult
    {
        $response = $this->makeRequest('GET', "/api/backend/v1/end_user_api_keys{$suffix}", array_filter([
            'userId' => $userId,
            'orgId' => $orgId,
            'userEmail' => $userEmail,
            'pageSize' => $pageSize,
            'pageNumber' => $pageNumber,
        ], fn ($v) => $v !== null));

        return PaginatedResult::from([
            'items' => array_map(fn (array $key) => ApiKey::fromArray($key), $response['apiKeys'] ?? []),
            'totalUsers' => $response['totalApiKeys'] ?? 0,
            'currentPage' => $response['currentPage'] ?? $pageNumber,
            'pageSize' => $response['pageSize'] ?? $pageSize,
            'hasMoreResults' => $response['hasMoreResults'] ?? false,
        ], fn (int $nextPage) => $this->listKeys($suffix, $userId, $orgId, $userEmail, $pageSize, $nextPage));
    }

    protected function validate(string $endpoint, #[\SensitiveParameter] string $apiKeyToken): ApiKeyValidation
    {
        try {
            $response = $this->makeRequest('POST', $endpoint, [
                'apiKeyToken' => preg_replace('/^Bearer\s+/i', '', trim($apiKeyToken)),
            ]);
        } catch (InvalidApiKeyException $e) {
            throw $e;
        } catch (ValidationException $e) {
            throw InvalidApiKeyException::fromValidation($e);
        }

        return ApiKeyValidation::fromArray($response);
    }
}
