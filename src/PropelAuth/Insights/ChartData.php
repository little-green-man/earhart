<?php

namespace LittleGreenMan\Earhart\PropelAuth\Insights;

use Carbon\CarbonImmutable;

/**
 * A chart metric over time.
 */
class ChartData
{
    /**
     * @param  list<array{date: CarbonImmutable, result: int, cadenceCompleted: bool}>  $points  cadenceCompleted is false for a period still in progress
     */
    public function __construct(
        public string $chartType,
        public ?ChartCadence $cadence,
        public array $points,
    ) {}

    /**
     * @param  array<string, mixed>  $data  camelCase API fields
     */
    public static function fromArray(array $data): self
    {
        return new self(
            chartType: $data['chartType'],
            cadence: ChartCadence::tryFrom((string) ($data['cadence'] ?? '')),
            points: array_map(fn (array $point) => [
                'date' => CarbonImmutable::parse($point['date']),
                'result' => (int) $point['result'],
                'cadenceCompleted' => (bool) $point['cadenceCompleted'],
            ], $data['metrics'] ?? []),
        );
    }

    /**
     * @return array<string, int> Results keyed by Y-m-d date
     */
    public function toArray(): array
    {
        return array_column(
            array_map(fn (array $point) => ['date' => $point['date']->format('Y-m-d'), 'result' => $point['result']], $this->points),
            'result',
            'date',
        );
    }
}
