<?php

namespace LittleGreenMan\Earhart\Exceptions;

class InvalidUserException extends PropelAuthException
{
    public static function notFound(string $userId): self
    {
        return new self("User '{$userId}' not found in PropelAuth", 404, ['user_id' => $userId]);
    }

    public static function byEmail(string $email): self
    {
        return new self("User with email '{$email}' not found", 404, ['email' => $email]);
    }

    public static function byUsername(string $username): self
    {
        return new self("User with username '{$username}' not found", 404, ['username' => $username]);
    }

    public static function disabled(string $userId): self
    {
        return new self("User '{$userId}' is disabled", 403, ['user_id' => $userId]);
    }
}
