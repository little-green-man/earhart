<?php

namespace LittleGreenMan\Earhart\Exceptions;

class ValidationException extends PropelAuthException
{
    protected ?array $errors;

    public function __construct(string $message, ?array $errors = null, int $statusCode = 400, ?array $context = null)
    {
        $this->errors = $errors;
        parent::__construct($message, $statusCode, $context ?? $errors);
    }

    public static function invalidEmail(string $email): self
    {
        return new self("Invalid email address: '{$email}'", ['email' => $email]);
    }

    public static function invalidPassword(string $reason = ''): self
    {
        return new self('Invalid password'.($reason ? ": {$reason}" : ''), ['reason' => $reason]);
    }

    public static function missingRequired(array $fields): self
    {
        return new self('Missing required fields: '.implode(', ', $fields), ['fields' => $fields]);
    }

    public static function invalidData(array $errors): self
    {
        return new self('Validation failed', $errors);
    }

    /**
     * The field errors. For an API response, this is PropelAuth's decoded error body.
     */
    public function getErrors(): ?array
    {
        return $this->errors;
    }
}
