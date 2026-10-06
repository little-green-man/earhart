<?php

namespace LittleGreenMan\Earhart\Testing;

use LittleGreenMan\Earhart\PropelAuth\Insights\ChartCadence;
use LittleGreenMan\Earhart\PropelAuth\Insights\ChartData;
use LittleGreenMan\Earhart\PropelAuth\Insights\ChartMetric;
use LittleGreenMan\Earhart\PropelAuth\Insights\OrgReportRecord;
use LittleGreenMan\Earhart\PropelAuth\Insights\OrgReportType;
use LittleGreenMan\Earhart\PropelAuth\Insights\Report;
use LittleGreenMan\Earhart\PropelAuth\Insights\UserReportRecord;
use LittleGreenMan\Earhart\PropelAuth\Insights\UserReportType;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Services\InsightsService;

/**
 * In-memory InsightsService used by Earhart::fake(). Reports return what was seeded.
 */
class FakeInsightsService extends InsightsService
{
    use RecordsCalls;

    public function __construct(FakeState $state)
    {
        parent::__construct('fake-api-key', 'https://auth.example.test', new CacheService(false));
        $this->state = $state;
    }

    public function getUserReport(UserReportType $type, string|int|null $interval = null, int $pageSize = 10, int $pageNumber = 0): Report
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($type, $interval, $pageSize, $pageNumber) {
            $this->interval($type->intervals(), $interval, $type->name);

            return $this->fakeReport(
                array_map(fn (array $record) => UserReportRecord::fromArray($record), $this->state->userReports[$type->value] ?? []),
                $pageSize,
                $pageNumber,
                fn (int $nextPage) => $this->getUserReport($type, $interval, $pageSize, $nextPage),
            );
        });
    }

    public function getOrgReport(OrgReportType $type, string|int|null $interval = null, int $pageSize = 10, int $pageNumber = 0): Report
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($type, $interval, $pageSize, $pageNumber) {
            $this->interval($type->intervals(), $interval, $type->name);

            return $this->fakeReport(
                array_map(fn (array $record) => OrgReportRecord::fromArray($record), $this->state->orgReports[$type->value] ?? []),
                $pageSize,
                $pageNumber,
                fn (int $nextPage) => $this->getOrgReport($type, $interval, $pageSize, $nextPage),
            );
        });
    }

    public function getChartMetrics(
        ChartMetric $metric,
        ?ChartCadence $cadence = null,
        \DateTimeInterface|string|null $startDate = null,
        \DateTimeInterface|string|null $endDate = null,
    ): ChartData {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($metric, $cadence, $startDate, $endDate) {
            $start = $this->date($startDate);
            $end = $this->date($endDate);
            $points = [];

            foreach ($this->state->chartMetrics[$metric->value] ?? [] as $date => $result) {
                if (($start === null || $date >= $start) && ($end === null || $date <= $end)) {
                    $points[] = ['date' => $date, 'result' => $result, 'cadenceCompleted' => $date < now()->format('Y-m-d')];
                }
            }

            return ChartData::fromArray([
                'chartType' => $metric->name,
                'cadence' => ($cadence ?? ChartCadence::Daily)->value,
                'metrics' => $points,
            ]);
        });
    }

    /**
     * @param  list<UserReportRecord|OrgReportRecord>  $records
     */
    protected function fakeReport(array $records, int $pageSize, int $pageNumber, \Closure $next): Report
    {
        $page = $this->state->page($records, $pageNumber, $pageSize);

        return new Report(
            items: $page['items'],
            totalItems: $page['totalUsers'],
            currentPage: $pageNumber,
            pageSize: $pageSize,
            hasMoreResults: $page['hasMoreResults'],
            fetchNextPage: $next,
            reportTime: now()->toImmutable(),
        );
    }
}
