<?php

namespace LittleGreenMan\Earhart\Exceptions;

class RateLimitException extends PropelAuthException
{
    public int $retryAfterSeconds;

    public function __construct(string $message = 'Rate limit exceeded', int $retryAfterSeconds = 60)
    {
        $this->retryAfterSeconds = $retryAfterSeconds;
        parent::__construct($message, 429, ['retry_after' => $retryAfterSeconds]);
    }

    public static function fromHeaders(?string $retryAfterHeader = null): self
    {
        $retryAfter = 60;

        if ($retryAfterHeader !== null && is_numeric($retryAfterHeader)) {
            $parsed = (int) $retryAfterHeader;
            $retryAfter = max(60, $parsed);
        }

        return new self('PropelAuth API rate limit exceeded', $retryAfter);
    }
}
