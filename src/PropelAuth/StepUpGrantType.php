<?php

namespace LittleGreenMan\Earhart\PropelAuth;

/**
 * How long a step-up MFA grant can be used for.
 */
enum StepUpGrantType: string
{
    /** The grant is consumed by its first successful verification. */
    case OneTimeUse = 'ONE_TIME_USE';

    /** The grant can be verified any number of times until it expires. */
    case TimeBased = 'TIME_BASED';
}
