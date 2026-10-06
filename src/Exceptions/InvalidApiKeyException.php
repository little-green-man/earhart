<?php

namespace LittleGreenMan\Earhart\Exceptions;

/**
 * An end-user API key is invalid, expired, unknown, or not the kind expected.
 */
class InvalidApiKeyException extends ValidationException
{
    public static function notFound(string $apiKeyId): self
    {
        return new self("API key '{$apiKeyId}' not found", ['api_key_id' => $apiKeyId], 404);
    }

    public static function notPersonal(): self
    {
        return new self('Not a personal API key', ['api_key_token' => ['Not a personal API key']]);
    }

    public static function notOrg(): self
    {
        return new self('Not an organisation API key', ['api_key_token' => ['Not an org API key']]);
    }

    public static function fromValidation(ValidationException $e): self
    {
        return new self('Invalid API key', $e->getErrors(), $e->getStatusCode(), $e->getContext());
    }
}
