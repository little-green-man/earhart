<?php

namespace LittleGreenMan\Earhart\Exceptions;

/**
 * Thrown when an access token is malformed, expired, or not signed by your PropelAuth environment.
 */
class InvalidTokenException extends PropelAuthException
{
    public static function because(string $reason, ?\Exception $previous = null): self
    {
        return new self("Invalid PropelAuth access token: {$reason}", 401, null, $previous);
    }
}
