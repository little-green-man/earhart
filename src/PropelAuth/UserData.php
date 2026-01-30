<?php

namespace LittleGreenMan\Earhart\PropelAuth;

use Carbon\Carbon;
use LittleGreenMan\Earhart\CarbonFromTimestampCast;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public string $user_id,
        public string $email,
        public bool $email_confirmed,
        public string $first_name,
        public string $last_name,
        public ?string $username,
        public string $picture_url,
        public array $properties,
        public bool $locked,
        public bool $enabled,
        public bool $has_password,
        public bool $update_password_required,
        public bool $mfa_enabled,
        public bool $can_create_orgs,
        #[WithCast(CarbonFromTimestampCast::class)]
        public \DateTime $created_at,
        #[WithCast(CarbonFromTimestampCast::class)]
        public \DateTime $last_active_at,
        public array $orgs = [],
    ) {}

    /**
     * Create a UserData instance from an array response.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            user_id: $data['user_id'],
            email: $data['email'],
            email_confirmed: $data['email_confirmed'],
            first_name: $data['first_name'],
            last_name: $data['last_name'],
            username: $data['username'] ?? null,
            picture_url: $data['picture_url'],
            properties: $data['properties'] ?? [],
            locked: $data['locked'] ?? false,
            enabled: $data['enabled'] ?? true,
            has_password: $data['has_password'] ?? false,
            update_password_required: $data['update_password_required'] ?? false,
            mfa_enabled: $data['mfa_enabled'] ?? false,
            can_create_orgs: $data['can_create_orgs'] ?? false,
            created_at: Carbon::createFromTimestamp($data['created_at']),
            last_active_at: Carbon::createFromTimestamp($data['last_active_at']),
            orgs: $data['orgs'] ?? [],
        );
    }

    public static function normalizers(): array
    {
        return [];
    }

    public static function transformers(): array
    {
        return [];
    }
}
