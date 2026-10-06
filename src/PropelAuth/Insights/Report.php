<?php

namespace LittleGreenMan\Earhart\PropelAuth\Insights;

use Carbon\CarbonImmutable;
use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;

/**
 * A page of a user or organisation report. Items are UserReportRecord or OrgReportRecord.
 */
class Report extends PaginatedResult
{
    /**
     * @param  array<mixed>  $items
     * @param  \Closure(int): self  $fetchNextPage
     */
    public function __construct(
        array $items,
        int $totalItems,
        int $currentPage,
        int $pageSize,
        bool $hasMoreResults,
        \Closure $fetchNextPage,
        public ?CarbonImmutable $reportTime = null,
    ) {
        parent::__construct($items, $totalItems, $currentPage, $pageSize, $hasMoreResults, $fetchNextPage);
    }
}
