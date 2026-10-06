<?php

namespace LittleGreenMan\Earhart\PropelAuth\Insights;

/**
 * PropelAuth's user reports.
 */
enum UserReportType: string
{
    /** Users who came back after being inactive. Interval: Weekly or Monthly. */
    case Reengagement = 'reengagement';

    /** Users who have stopped being active. Interval: 7, 14 or 30 days. */
    case Churn = 'churn';

    /** Users who invited the most people. Interval: 30, 60 or 90 days. */
    case TopInviter = 'top_inviter';

    /** Your most active users. Interval: 30, 60 or 90 days. */
    case Champion = 'champion';

    /**
     * @return list<string>
     */
    public function intervals(): array
    {
        return match ($this) {
            self::Reengagement => ['Weekly', 'Monthly'],
            self::Churn => ['7', '14', '30'],
            self::TopInviter, self::Champion => ['30', '60', '90'],
        };
    }
}
