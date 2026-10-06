<?php

namespace LittleGreenMan\Earhart\PropelAuth;

use Carbon\CarbonImmutable;

/**
 * An OAuth token PropelAuth holds for a user's social login (e.g. Google, GitHub).
 */
class SocialLoginToken
{
    /**
     * @param  list<string>  $authorizedScopes
     */
    public function __construct(
        public string $provider,
        public string $accessToken,
        public ?string $refreshToken = null,
        public ?CarbonImmutable $expiresAt = null,
        public array $authorizedScopes = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  camelCase API fields
     */
    public static function fromArray(array $data, ?string $provider = null): self
    {
        return new self(
            provider: $data['tokenProvider'] ?? $provider ?? '',
            accessToken: $data['accessToken'],
            refreshToken: $data['refreshToken'] ?? null,
            expiresAt: isset($data['tokenExpiration']) ? CarbonImmutable::createFromTimestamp($data['tokenExpiration']) : null,
            authorizedScopes: $data['authorizedScopes'] ?? [],
        );
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt->isPast();
    }
}
