<?php

namespace LittleGreenMan\Earhart\PropelAuth;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class OrganisationsData extends Data
{
    public function __construct(
        /** @var Collection<int, OrganisationData> */
        public Collection $orgs,
        public int $total_orgs,
        public int $current_page,
        public int $page_size,
        public bool $has_more_results,
    ) {}
}
