<?php

namespace LittleGreenMan\Earhart\PropelAuth\Insights;

/**
 * PropelAuth's organisation reports.
 */
enum OrgReportType: string
{
    /** Organisations that came back after being inactive. Interval: Weekly or Monthly. */
    case Reengagement = 'reengagement';

    /** Organisations that have stopped being active. Interval: 7, 14 or 30 days. */
    case Churn = 'churn';

    /** Organisations growing fastest. Interval: 30, 60 or 90 days. */
    case Growth = 'growth';

    /** Organisations losing members. Interval: 30, 60 or 90 days. */
    case Attrition = 'attrition';

    /**
     * @return list<string>
     */
    public function intervals(): array
    {
        return match ($this) {
            self::Reengagement => ['Weekly', 'Monthly'],
            self::Churn => ['7', '14', '30'],
            self::Growth, self::Attrition => ['30', '60', '90'],
        };
    }
}
