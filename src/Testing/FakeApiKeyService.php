<?php

namespace LittleGreenMan\Earhart\Testing;

use LittleGreenMan\Earhart\Exceptions\InvalidApiKeyException;
use LittleGreenMan\Earhart\PropelAuth\ApiKey;
use LittleGreenMan\Earhart\PropelAuth\ApiKeyValidation;
use LittleGreenMan\Earhart\PropelAuth\NewApiKey;
use LittleGreenMan\Earhart\PropelAuth\OrganisationData;
use LittleGreenMan\Earhart\PropelAuth\OrgMemberInfo;
use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;
use LittleGreenMan\Earhart\Services\ApiKeyService;
use LittleGreenMan\Earhart\Services\CacheService;

/**
 * In-memory ApiKeyService used by Earhart::fake().
 */
class FakeApiKeyService extends ApiKeyService
{
    use RecordsCalls;

    public function __construct(FakeState $state)
    {
        parent::__construct('fake-api-key', 'https://auth.example.test', new CacheService(false));
        $this->state = $state;
    }

    /**
     * Store a key without recording a call.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function store(
        ?string $userId = null,
        ?string $orgId = null,
        \DateTimeInterface|int|null $expiresAt = null,
        ?array $metadata = null,
        ?string $displayName = null,
        ?string $token = null,
        bool $imported = false,
    ): NewApiKey {
        $apiKeyId = bin2hex(random_bytes(16));
        $token ??= $apiKeyId.bin2hex(random_bytes(32));

        $this->state->apiKeys[$apiKeyId] = [
            'token' => $token,
            'userId' => $userId,
            'orgId' => $orgId,
            'expiresAt' => $this->timestamp($expiresAt),
            'metadata' => $metadata,
            'displayName' => $displayName,
            'createdAt' => now()->getTimestamp(),
            'archived' => false,
            'imported' => $imported,
        ];

        return new NewApiKey($apiKeyId, $token);
    }

    public function createApiKey(
        ?string $userId = null,
        ?string $orgId = null,
        \DateTimeInterface|int|null $expiresAt = null,
        ?array $metadata = null,
        ?string $displayName = null,
    ): NewApiKey {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->store($userId, $orgId, $expiresAt, $metadata, $displayName));
    }

    public function importApiKey(
        #[\SensitiveParameter] string $importedApiKey,
        ?string $userId = null,
        ?string $orgId = null,
        \DateTimeInterface|int|null $expiresAt = null,
        ?array $metadata = null,
        ?string $displayName = null,
    ): string {
        return $this->fake(__FUNCTION__, ['userId' => $userId, 'orgId' => $orgId, 'displayName' => $displayName], fn () => $this->store(
            $userId, $orgId, $expiresAt, $metadata, $displayName, $importedApiKey, imported: true,
        )->apiKeyId);
    }

    public function getApiKey(string $apiKeyId): ApiKey
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->toApiKey($apiKeyId, $this->requireKey($apiKeyId)));
    }

    public function getActiveApiKeys(?string $userId = null, ?string $orgId = null, ?string $userEmail = null, int $pageSize = 10, int $pageNumber = 0): PaginatedResult
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->list(false, $userId, $orgId, $userEmail, $pageSize, $pageNumber));
    }

    public function getArchivedApiKeys(?string $userId = null, ?string $orgId = null, ?string $userEmail = null, int $pageSize = 10, int $pageNumber = 0): PaginatedResult
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->list(true, $userId, $orgId, $userEmail, $pageSize, $pageNumber));
    }

    public function updateApiKey(
        string $apiKeyId,
        \DateTimeInterface|int|null $expiresAt = null,
        ?array $metadata = null,
        bool $neverExpire = false,
    ): bool {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($apiKeyId, $expiresAt, $metadata, $neverExpire) {
            $this->requireKey($apiKeyId);

            if ($neverExpire) {
                $this->state->apiKeys[$apiKeyId]['expiresAt'] = null;
            } elseif ($expiresAt !== null) {
                $this->state->apiKeys[$apiKeyId]['expiresAt'] = $this->timestamp($expiresAt);
            }

            if ($metadata !== null) {
                $this->state->apiKeys[$apiKeyId]['metadata'] = $metadata;
            }

            return true;
        });
    }

    public function deleteApiKey(string $apiKeyId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($apiKeyId) {
            $this->requireKey($apiKeyId);
            $this->state->apiKeys[$apiKeyId]['archived'] = true;

            return true;
        });
    }

    public function validateApiKey(#[\SensitiveParameter] string $apiKeyToken): ApiKeyValidation
    {
        return $this->fake(__FUNCTION__, [], fn () => $this->validateToken($apiKeyToken, false));
    }

    public function validatePersonalApiKey(#[\SensitiveParameter] string $apiKeyToken): ApiKeyValidation
    {
        return $this->fake(__FUNCTION__, [], function () use ($apiKeyToken) {
            $validation = $this->validateToken($apiKeyToken, false);

            return $validation->isPersonal() ? $validation : throw InvalidApiKeyException::notPersonal();
        });
    }

    public function validateOrgApiKey(#[\SensitiveParameter] string $apiKeyToken): ApiKeyValidation
    {
        return $this->fake(__FUNCTION__, [], function () use ($apiKeyToken) {
            $validation = $this->validateToken($apiKeyToken, false);

            return $validation->isOrg() ? $validation : throw InvalidApiKeyException::notOrg();
        });
    }

    public function validateImportedApiKey(#[\SensitiveParameter] string $apiKeyToken): ApiKeyValidation
    {
        return $this->fake(__FUNCTION__, [], fn () => $this->validateToken($apiKeyToken, true));
    }

    public function getApiKeyUsage(\DateTimeInterface|string $date, ?string $apiKeyId = null, ?string $userId = null, ?string $orgId = null): int
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($date, $apiKeyId, $userId, $orgId) {
            $day = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date;
            $total = 0;

            foreach ($this->state->apiKeyUsage[$day] ?? [] as $id => $count) {
                $key = $this->state->apiKeys[$id] ?? null;

                if ($key !== null
                    && ($apiKeyId === null || $id === $apiKeyId)
                    && ($userId === null || $key['userId'] === $userId)
                    && ($orgId === null || $key['orgId'] === $orgId)) {
                    $total += $count;
                }
            }

            return $total;
        });
    }

    protected function validateToken(string $apiKeyToken, bool $imported): ApiKeyValidation
    {
        $token = preg_replace('/^Bearer\s+/i', '', trim($apiKeyToken));

        foreach ($this->state->apiKeys as $id => $key) {
            if (! hash_equals($key['token'], $token) || $key['imported'] !== $imported) {
                continue;
            }

            if ($key['archived'] || ($key['expiresAt'] !== null && $key['expiresAt'] < now()->getTimestamp())) {
                break;
            }

            $day = now()->format('Y-m-d');
            $this->state->apiKeyUsage[$day][$id] = ($this->state->apiKeyUsage[$day][$id] ?? 0) + 1;

            $user = $key['userId'] !== null && isset($this->state->users[$key['userId']]) ? $this->state->userData($key['userId']) : null;
            $org = $key['orgId'] !== null && isset($this->state->orgs[$key['orgId']]) ? OrganisationData::fromArray($this->state->orgs[$key['orgId']]) : null;
            $userInOrg = $user !== null && $key['orgId'] !== null && isset($this->state->orgInfo($key['userId'])[$key['orgId']])
                ? OrgMemberInfo::fromArray($this->state->orgInfo($key['userId'])[$key['orgId']])
                : null;

            return new ApiKeyValidation($key['metadata'], $user, $org, $userInOrg);
        }

        throw new InvalidApiKeyException('Invalid API key', ['api_key_token' => ['Invalid API key']]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function requireKey(string $apiKeyId): array
    {
        return $this->state->apiKeys[$apiKeyId] ?? throw InvalidApiKeyException::notFound($apiKeyId);
    }

    /**
     * @param  array<string, mixed>  $key
     */
    protected function toApiKey(string $apiKeyId, array $key): ApiKey
    {
        return ApiKey::fromArray([
            'apiKeyId' => $apiKeyId,
            'createdAt' => $key['createdAt'],
            'expiresAtSeconds' => $key['expiresAt'],
            'metadata' => $key['metadata'],
            'userId' => $key['userId'],
            'orgId' => $key['orgId'],
            'displayName' => $key['displayName'],
        ]);
    }

    protected function list(bool $archived, ?string $userId, ?string $orgId, ?string $userEmail, int $pageSize, int $pageNumber): PaginatedResult
    {
        $now = now()->getTimestamp();
        $emailUserId = $userEmail === null ? null : (array_search(
            strtolower($userEmail),
            array_map(fn (array $user) => strtolower($user['email']), $this->state->users),
            true,
        ) ?: '');

        $keys = array_filter($this->state->apiKeys, fn (array $key) => (($key['archived'] || ($key['expiresAt'] !== null && $key['expiresAt'] < $now)) === $archived)
            && ($userId === null || $key['userId'] === $userId)
            && ($orgId === null || $key['orgId'] === $orgId)
            && ($emailUserId === null || $key['userId'] === $emailUserId));

        $items = array_map(fn (string $id, array $key) => $this->toApiKey($id, $key), array_keys($keys), $keys);

        return PaginatedResult::from(
            $this->state->page($items, $pageNumber, $pageSize),
            fn (int $nextPage) => $this->list($archived, $userId, $orgId, $userEmail, $pageSize, $nextPage),
        );
    }
}
