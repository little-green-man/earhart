<?php

namespace LittleGreenMan\Earhart\PropelAuth;

use Carbon\Carbon;
use LittleGreenMan\Earhart\CarbonFromTimestampCast;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

class OrganisationData extends Data
{
    public function __construct(
        public string $orgId,
        public string $displayName,
        public ?string $urlSafeOrgSlug,
        public ?bool $canSetupSaml,
        public bool $isSamlConfigured,
        public ?bool $isSamlInTestMode,
        public ?array $extraDomains,
        public ?bool $domainAutojoin,
        public ?bool $domainRestrict,
        public string $customRoleMappingName,
        #[WithCast(CarbonFromTimestampCast::class)]
        public ?\DateTime $createdAt = null,
        public ?array $metadata = null,
        public ?int $maxOrgMembers = null,
    ) {}

    /**
     * Create an OrganisationData instance from an array response.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            orgId: $data['orgId'],
            displayName: $data['name'], // API returns 'name', we map to displayName
            urlSafeOrgSlug: $data['urlSafeOrgSlug'] ?? null,
            canSetupSaml: $data['canSetupSaml'] ?? null,
            isSamlConfigured: $data['isSamlConfigured'] ?? false,
            isSamlInTestMode: $data['isSamlInTestMode'] ?? null,
            extraDomains: $data['extraDomains'] ?? null,
            domainAutojoin: $data['domainAutojoin'] ?? null,
            domainRestrict: $data['domainRestrict'] ?? null,
            customRoleMappingName: $data['customRoleMappingName'] ?? 'default',
            createdAt: isset($data['createdAt']) ? Carbon::createFromTimestamp($data['createdAt']) : null,
            metadata: $data['metadata'] ?? null,
            maxOrgMembers: $data['maxOrgMembers'] ?? null,
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
