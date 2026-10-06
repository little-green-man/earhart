<?php

namespace LittleGreenMan\Earhart\PropelAuth;

use Carbon\CarbonImmutable;

/**
 * An end-user API key's details. The secret token is only returned when the key is created.
 */
class ApiKey
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $apiKeyId,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $expiresAt = null,
        public ?array $metadata = null,
        public ?string $userId = null,
        public ?string $orgId = null,
        public ?string $displayName = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  camelCase API fields
     */
    public static function fromArray(array $data): self
    {
        return new self(
            apiKeyId: $data['apiKeyId'],
            createdAt: CarbonImmutable::createFromTimestamp($data['createdAt']),
            expiresAt: isset($data['expiresAtSeconds']) ? CarbonImmutable::createFromTimestamp($data['expiresAtSeconds']) : null,
            metadata: $data['metadata'] ?? null,
            userId: $data['userId'] ?? null,
            orgId: $data['orgId'] ?? null,
            displayName: $data['displayName'] ?? null,
        );
    }

    public function isPersonal(): bool
    {
        return $this->orgId === null;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt->isPast();
    }
}
