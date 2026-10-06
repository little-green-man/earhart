<?php

namespace LittleGreenMan\Earhart\Exceptions;

/**
 * A step-up MFA request was refused. getErrorCode() gives PropelAuth's reason.
 */
class StepUpMfaException extends PropelAuthException
{
    public const INCORRECT_CODE = 'incorrect_mfa_code';

    public const MFA_NOT_ENABLED = 'mfa_not_enabled';

    public function __construct(string $message, protected string $errorCode, int $statusCode = 400, ?array $context = null, ?\Exception $previous = null)
    {
        parent::__construct($message, $statusCode, $context, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function isIncorrectCode(): bool
    {
        return $this->errorCode === self::INCORRECT_CODE;
    }

    public function isMfaNotEnabled(): bool
    {
        return $this->errorCode === self::MFA_NOT_ENABLED;
    }
}
