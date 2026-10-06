<?php

namespace LittleGreenMan\Earhart\PropelAuth\Insights;

use Carbon\CarbonImmutable;

/**
 * One organisation in an organisation report.
 */
class OrgReportRecord
{
    /**
     * @param  array<string, mixed>  $extraProperties  Report-specific values
     */
    public function __construct(
        public string $orgId,
        public string $name,
        public int $numUsers,
        public CarbonImmutable $orgCreatedAt,
        public array $extraProperties = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data  camelCase API fields
     */
    public static function fromArray(array $data): self
    {
        return new self(
            orgId: $data['orgId'],
            name: $data['name'],
            numUsers: (int) $data['numUsers'],
            orgCreatedAt: CarbonImmutable::createFromTimestamp($data['orgCreatedAt']),
            extraProperties: $data['extraProperties'] ?? [],
        );
    }
}
