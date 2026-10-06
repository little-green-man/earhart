<?php

namespace LittleGreenMan\Earhart\PropelAuth;

/**
 * The service-provider details an organisation's IdP needs to set up SAML.
 */
class SamlSpMetadata
{
    public function __construct(
        public string $entityId,
        public string $acsUrl,
        public string $logoutUrl,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            entityId: $data['entityId'],
            acsUrl: $data['acsUrl'],
            logoutUrl: $data['logoutUrl'],
        );
    }
}
