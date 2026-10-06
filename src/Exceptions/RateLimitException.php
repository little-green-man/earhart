<?php

namespace LittleGreenMan\Earhart\Exceptions;

use Carbon\Carbon;

class RateLimitException extends PropelAuthException
{
    public int $retryAfterSeconds;

    /**
     * Whether $retryAfterSeconds came from a Retry-After header rather than the default.
     */
    public bool $retryAfterFromHeader;

    public function __construct(string $message = 'Rate limit exceeded', int $retryAfterSeconds = 60, bool $retryAfterFromHeader = false)
    {
        $this->retryAfterSeconds = $retryAfterSeconds;
        $this->retryAfterFromHeader = $retryAfterFromHeader;
        parent::__construct($message, 429, ['retry_after' => $retryAfterSeconds]);
    }

    /**
     * Accepts a Retry-After header in seconds or as an HTTP date. Defaults to 60 seconds.
     */
    public static function fromHeaders(?string $retryAfterHeader = null): self
    {
        $retryAfter = null;

        if ($retryAfterHeader !== null && is_numeric($retryAfterHeader)) {
            $retryAfter = max(0, (int) $retryAfterHeader);
        } elseif ($retryAfterHeader !== null && ($timestamp = strtotime($retryAfterHeader)) !== false) {
            $retryAfter = max(0, $timestamp - Carbon::now()->getTimestamp());
        }

        return new self('PropelAuth API rate limit exceeded', $retryAfter ?? 60, $retryAfter !== null);
    }
}
