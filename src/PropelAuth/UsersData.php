<?php

namespace LittleGreenMan\Earhart\PropelAuth;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class UsersData extends Data
{
    public function __construct(
        /** @var Collection<int, UserData> */
        public Collection $users,
        public int $total_users,
        public int $current_page,
        public int $page_size,
        public bool $has_more_results,
    ) {}
}
