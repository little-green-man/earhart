<?php

namespace LittleGreenMan\Earhart\Services;

use Carbon\CarbonImmutable;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\PropelAuth\Insights\ChartCadence;
use LittleGreenMan\Earhart\PropelAuth\Insights\ChartData;
use LittleGreenMan\Earhart\PropelAuth\Insights\ChartMetric;
use LittleGreenMan\Earhart\PropelAuth\Insights\OrgReportRecord;
use LittleGreenMan\Earhart\PropelAuth\Insights\OrgReportType;
use LittleGreenMan\Earhart\PropelAuth\Insights\Report;
use LittleGreenMan\Earhart\PropelAuth\Insights\UserReportRecord;
use LittleGreenMan\Earhart\PropelAuth\Insights\UserReportType;

/**
 * PropelAuth's user and organisation insights: reports and chart metrics.
 */
class InsightsService extends BaseApiService
{
    /**
     * Fetch a page of a user report.
     *
     * @param  string|int|null  $interval  One of $type->intervals(), e.g. 30 or "Weekly"; PropelAuth's default when null
     *
     * @throws \InvalidArgumentException If the interval isn't valid for the report
     * @throws PropelAuthException On any API failure
     */
    public function getUserReport(UserReportType $type, string|int|null $interval = null, int $pageSize = 10, int $pageNumber = 0): Report
    {
        return $this->report('user_report', $type->value, $this->interval($type->intervals(), $interval, $type->name), $pageSize, $pageNumber,
            fn (array $record) => UserReportRecord::fromArray($record),
            fn (int $nextPage) => $this->getUserReport($type, $interval, $pageSize, $nextPage),
        );
    }

    /**
     * Fetch a page of an organisation report.
     *
     * @param  string|int|null  $interval  One of $type->intervals(), e.g. 30 or "Weekly"; PropelAuth's default when null
     *
     * @throws \InvalidArgumentException If the interval isn't valid for the report
     * @throws PropelAuthException On any API failure
     */
    public function getOrgReport(OrgReportType $type, string|int|null $interval = null, int $pageSize = 10, int $pageNumber = 0): Report
    {
        return $this->report('org_report', $type->value, $this->interval($type->intervals(), $interval, $type->name), $pageSize, $pageNumber,
            fn (array $record) => OrgReportRecord::fromArray($record),
            fn (int $nextPage) => $this->getOrgReport($type, $interval, $pageSize, $nextPage),
        );
    }

    /**
     * Fetch a metric over a date range.
     *
     * @param  \DateTimeInterface|string|null  $startDate  A date or Y-m-d string
     * @param  \DateTimeInterface|string|null  $endDate  A date or Y-m-d string
     *
     * @throws PropelAuthException On any API failure
     */
    public function getChartMetrics(
        ChartMetric $metric,
        ?ChartCadence $cadence = null,
        \DateTimeInterface|string|null $startDate = null,
        \DateTimeInterface|string|null $endDate = null,
    ): ChartData {
        $response = $this->makeRequest('GET', "/api/backend/v1/chart_metrics/{$metric->value}", array_filter([
            'cadence' => $cadence?->value,
            'startDate' => $this->date($startDate),
            'endDate' => $this->date($endDate),
        ], fn ($v) => $v !== null));

        return ChartData::fromArray($response);
    }

    /**
     * @param  \Closure(array<string, mixed>): (UserReportRecord|OrgReportRecord)  $record
     * @param  \Closure(int): Report  $next
     */
    protected function report(string $path, string $type, ?string $interval, int $pageSize, int $pageNumber, \Closure $record, \Closure $next): Report
    {
        $response = $this->makeRequest('GET', "/api/backend/v1/{$path}/{$type}", array_filter([
            'reportInterval' => $interval,
            'pageSize' => $pageSize,
            'pageNumber' => $pageNumber,
        ], fn ($v) => $v !== null));

        $records = $response['userReports'] ?? $response['orgReports'] ?? [];

        return new Report(
            items: array_map($record, $records),
            totalItems: (int) ($response['totalCount'] ?? count($records)),
            currentPage: (int) ($response['currentPage'] ?? $pageNumber),
            pageSize: (int) ($response['pageSize'] ?? $pageSize),
            hasMoreResults: (bool) ($response['hasMoreResults'] ?? false),
            fetchNextPage: $next,
            reportTime: isset($response['reportTime']) ? CarbonImmutable::createFromTimestamp($response['reportTime']) : null,
        );
    }

    /**
     * @param  list<string>  $allowed
     */
    protected function interval(array $allowed, string|int|null $interval, string $report): ?string
    {
        if ($interval === null) {
            return null;
        }

        foreach ($allowed as $value) {
            if (strcasecmp($value, (string) $interval) === 0) {
                return $value;
            }
        }

        throw new \InvalidArgumentException("Invalid interval '{$interval}' for the {$report} report; use one of: ".implode(', ', $allowed));
    }

    protected function date(\DateTimeInterface|string|null $date): ?string
    {
        return $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date;
    }
}
