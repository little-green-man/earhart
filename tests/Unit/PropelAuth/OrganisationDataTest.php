<?php

namespace LittleGreenMan\Earhart\Tests\Unit\PropelAuth;

use LittleGreenMan\Earhart\PropelAuth\OrganisationData;

describe('OrganisationData', function () {
    function mockOrgArray(): array
    {
        return [
            'orgId' => 'org123',
            'name' => 'Acme Corp',
            'urlSafeOrgSlug' => 'acme-corp',
            'canSetupSaml' => true,
            'isSamlConfigured' => false,
            'isSamlInTestMode' => false,
            'extraDomains' => ['acme.com', 'acmecorp.com'],
            'domainAutojoin' => false,
            'domainRestrict' => false,
            'customRoleMappingName' => 'custom_roles',
            'createdAt' => 1609459200,
            'metadata' => ['industry' => 'technology'],
            'maxOrgMembers' => 100,
        ];
    }

    test('can be instantiated with all required data', function () {
        $org = new OrganisationData(
            orgId: 'org123',
            displayName: 'Acme Corp',
            urlSafeOrgSlug: 'acme-corp',
            canSetupSaml: true,
            isSamlConfigured: false,
            isSamlInTestMode: false,
            extraDomains: ['acme.com'],
            domainAutojoin: false,
            domainRestrict: false,
            customRoleMappingName: 'default',
        );

        expect($org->orgId)
            ->toBe('org123')
            ->and($org->displayName)
            ->toBe('Acme Corp')
            ->and($org->urlSafeOrgSlug)
            ->toBe('acme-corp');
    });

    test('creates from array response', function () {
        $org = OrganisationData::fromArray(mockOrgArray());

        expect($org)
            ->toBeInstanceOf(OrganisationData::class)
            ->and($org->orgId)
            ->toBe('org123')
            ->and($org->displayName)
            ->toBe('Acme Corp')
            ->and($org->urlSafeOrgSlug)
            ->toBe('acme-corp');
    });

    test('handles optional fields', function () {
        $data = [
            'orgId' => 'org456',
            'name' => 'Simple Org',
            'customRoleMappingName' => 'default',
            'isSamlConfigured' => true,
        ];

        $org = OrganisationData::fromArray($data);

        expect($org->orgId)
            ->toBe('org456')
            ->and($org->displayName)
            ->toBe('Simple Org')
            ->and($org->urlSafeOrgSlug)
            ->toBeNull()
            ->and($org->extraDomains)
            ->toBeNull()
            ->and($org->metadata)
            ->toBeNull();
    });

    test('has proper default values for optional fields', function () {
        $data = [
            'orgId' => 'org789',
            'name' => 'Test Org',
            'isSamlConfigured' => true,
            'customRoleMappingName' => 'default',
        ];

        $org = OrganisationData::fromArray($data);

        expect($org->canSetupSaml)
            ->toBeNull()
            ->and($org->isSamlInTestMode)
            ->toBeNull()
            ->and($org->domainAutojoin)
            ->toBeNull()
            ->and($org->domainRestrict)
            ->toBeNull();
    });

    test('converts timestamps to Carbon instances', function () {
        $org = OrganisationData::fromArray(mockOrgArray());

        expect($org->createdAt)
            ->toBeInstanceOf(\DateTime::class)
            ->and($org->createdAt->getTimestamp())
            ->toBe(1609459200);
    });

    test('handles missing timestamps', function () {
        $data = mockOrgArray();
        unset($data['createdAt']);

        $org = OrganisationData::fromArray($data);

        expect($org->createdAt)->toBeNull();
    });

    test('preserves metadata array', function () {
        $org = OrganisationData::fromArray(mockOrgArray());

        expect($org->metadata)->toBeArray()->and($org->metadata['industry'])->toBe('technology');
    });

    test('preserves extra domains array', function () {
        $org = OrganisationData::fromArray(mockOrgArray());

        expect($org->extraDomains)->toBeArray()->toHaveCount(2)->toContain('acme.com')->toContain('acmecorp.com');
    });

    test('normalizers method returns empty array', function () {
        expect(OrganisationData::normalizers())->toBeArray()->toBeEmpty();
    });

    test('transformers method returns empty array', function () {
        expect(OrganisationData::transformers())->toBeArray()->toBeEmpty();
    });

    test('handles SAML configuration fields', function () {
        $org = OrganisationData::fromArray(mockOrgArray());

        expect($org->canSetupSaml)
            ->toBeTrue()
            ->and($org->isSamlConfigured)
            ->toBeFalse()
            ->and($org->isSamlInTestMode)
            ->toBeFalse();
    });

    test('handles max org members', function () {
        $org = OrganisationData::fromArray(mockOrgArray());

        expect($org->maxOrgMembers)->toBe(100);
    });

    test('preserves all fields in conversion', function () {
        $data = mockOrgArray();
        $org = OrganisationData::fromArray($data);

        expect($org->orgId)
            ->toBe($data['orgId'])
            ->and($org->displayName)
            ->toBe($data['name'])
            ->and($org->urlSafeOrgSlug)
            ->toBe($data['urlSafeOrgSlug'])
            ->and($org->canSetupSaml)
            ->toBe($data['canSetupSaml'])
            ->and($org->isSamlConfigured)
            ->toBe($data['isSamlConfigured'])
            ->and($org->customRoleMappingName)
            ->toBe($data['customRoleMappingName']);
    });
});
