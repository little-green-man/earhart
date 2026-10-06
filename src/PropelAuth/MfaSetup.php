<?php

namespace LittleGreenMan\Earhart\PropelAuth;

/**
 * How a user has set up multi-factor authentication.
 */
class MfaSetup
{
    /**
     * @param  string  $type  "Totp" (authenticator app) or "Phone" (SMS)
     * @param  array<string, string>  $phoneNumbers  Phone number suffixes keyed by MFA phone ID, for SMS
     */
    public function __construct(
        public string $type,
        public array $phoneNumbers = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  camelCase API fields
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            phoneNumbers: array_column($data['phoneNumbers'] ?? [], 'mfaPhoneNumberSuffix', 'mfaPhoneId'),
        );
    }

    public function usesTotp(): bool
    {
        return strcasecmp($this->type, 'Totp') === 0;
    }

    public function usesSms(): bool
    {
        return strcasecmp($this->type, 'Phone') === 0;
    }
}
