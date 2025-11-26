<?php

namespace LittleGreenMan\Earhart\PropelAuth;

use Carbon\Carbon;
use LittleGreenMan\Earhart\CarbonFromTimestampCast;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public string $user_id,
        public string $email,
        public bool   $email_confirmed,
        public string $first_name,
        public string $last_name,
        public ?string $username,
        public string $picture_url,
        public array  $properties,
        public bool   $locked,
        public bool   $enabled,
        public bool   $has_password,
        public bool   $update_password_required,
        public bool   $mfa_enabled,
        public bool   $can_create_orgs,
        #[WithCast(CarbonFromTimestampCast::class)]
        public \DateTime $created_at,
        #[WithCast(CarbonFromTimestampCast::class)]
        public \DateTime $last_active_at,
    ) {}
}
