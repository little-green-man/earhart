<?php

namespace LittleGreenMan\Earhart\Exceptions;

/**
 * Thrown on a 426: the feature (e.g. organisations, which need B2B support) is not enabled in your PropelAuth dashboard.
 */
class FeatureNotEnabledException extends PropelAuthException {}
