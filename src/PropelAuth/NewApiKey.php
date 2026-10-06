<?php

namespace LittleGreenMan\Earhart\PropelAuth;

/**
 * A newly created end-user API key. Show the token to its owner once; PropelAuth can't return it again.
 */
class NewApiKey
{
    public function __construct(
        public string $apiKeyId,
        #[\SensitiveParameter]
        public string $apiKeyToken,
    ) {}
}
