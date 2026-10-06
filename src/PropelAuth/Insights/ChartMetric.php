<?php

namespace LittleGreenMan\Earhart\PropelAuth\Insights;

enum ChartMetric: string
{
    case Signups = 'signups';
    case OrgsCreated = 'orgs_created';
    case ActiveUsers = 'active_users';
    case ActiveOrgs = 'active_orgs';
}
