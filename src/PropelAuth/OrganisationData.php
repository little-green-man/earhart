<?php

namespace LittleGreenMan\Earhart\PropelAuth;

use Spatie\LaravelData\Data;

class OrganisationData extends Data
{
    public function __construct(
        public string $org_id,
        public string $name,
        public ?string $url_safe_org_slug,
        public ?bool   $can_setup_saml,
        public bool   $is_saml_configured,
        public ?bool   $is_saml_in_test_mode,
        public ?array  $extra_domains,
        public ?bool   $domain_autojoin,
        public ?bool   $domain_restrict,
        public string $custom_role_mapping_name,
    ) {}
}

//Parameters missing: url_safe_org_slug, can_setup_saml, is_saml_in_test_mode, extra_domains, domain_autojoin, domain_restrict.
