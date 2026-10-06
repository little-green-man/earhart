<?php

namespace LittleGreenMan\Earhart\Exceptions;

/**
 * Thrown when PropelAuth rejects the API key (401) or the key lacks access (403).
 */
class UnauthorizedException extends PropelAuthException {}
