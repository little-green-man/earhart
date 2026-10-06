<?php

namespace LittleGreenMan\Earhart\Exceptions;

/**
 * An end-user API key has hit the rate limit set for it in PropelAuth.
 *
 * Unlike RateLimitException, this is about the key's owner, not your backend,
 * so it is never retried. Pass $userFacingError and $waitSeconds on to the caller.
 */
class ApiKeyRateLimitException extends PropelAuthException
{
    public function __construct(
        string $message,
        public readonly int $waitSeconds,
        public readonly ?string $userFacingError = null,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($message, 429, [
            'wait_seconds' => $waitSeconds,
            'error_code' => $errorCode,
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromBody(array $body): self
    {
        return new self(
            'PropelAuth end-user API key rate limit exceeded',
            (int) ceil((float) $body['wait_seconds']),
            $body['user_facing_error'] ?? null,
            $body['error_code'] ?? null,
        );
    }
}
