<?php

namespace LittleGreenMan\Earhart\Tests\Unit;

use Illuminate\Support\Facades\Http;
use LittleGreenMan\Earhart\Earhart;
use LittleGreenMan\Earhart\PropelAuth\OrganisationData;
use LittleGreenMan\Earhart\PropelAuth\OrganisationsData;
use LittleGreenMan\Earhart\PropelAuth\UserData;
use LittleGreenMan\Earhart\Tests\TestCase;

uses(TestCase::class);

describe('Earhart', function () {
    beforeEach(function () {
        Http::preventStrayRequests();
    });

    function createEarhart(): Earhart
    {
        return new Earhart(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            callbackUrl: 'https://example.com/callback',
            authUrl: 'https://auth.example.com',
            svixSecret: 'test-svix-secret',
            apiKey: 'test-api-key',
        );
    }

    function mockUserResponse(array $overrides = []): array
    {
        return array_merge([
            'userId' => 'user123',
            'email' => 'test@example.com',
            'emailConfirmed' => true,
            'firstName' => 'Test',
            'lastName' => 'User',
            'username' => 'testuser',
            'pictureUrl' => 'https://example.com/pic.jpg',
            'properties' => [],
            'locked' => false,
            'enabled' => true,
            'hasPassword' => true,
            'updatePasswordRequired' => false,
            'mfaEnabled' => false,
            'canCreateOrgs' => true,
            'createdAt' => 1609459200,
            'lastActiveAt' => 1609459200,
            'orgs' => [],
        ], $overrides);
    }

    function mockOrgResponse(array $overrides = []): array
    {
        return array_merge([
            'orgId' => 'org123',
            'name' => 'Test Org',
            'urlSafeOrgSlug' => 'test-org',
            'createdAt' => 1609459200,
            'metadata' => [],
            'maxOrgMembers' => 100,
            'isSamlConfigured' => false,
            'customRoleMappingName' => 'default',
        ], $overrides);
    }

    describe('getUsersInOrganisation', function () {
        test('returns array of UserData objects', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/org/org123*' => Http::response([
                    'users' => [
                        mockUserResponse(['userId' => 'user1', 'email' => 'user1@example.com']),
                        mockUserResponse(['userId' => 'user2', 'email' => 'user2@example.com']),
                        mockUserResponse(['userId' => 'user3', 'email' => 'user3@example.com']),
                    ],
                    'totalUsers' => 3,
                    'currentPage' => 0,
                    'pageSize' => 100,
                    'hasMoreResults' => false,
                ]),
            ]);

            $earhart = createEarhart();
            $users = $earhart->getUsersInOrganisation('org123');

            expect($users)
                ->toBeArray()
                ->toHaveCount(3)
                ->and($users[0])
                ->toBeInstanceOf(UserData::class)
                ->and($users[0]->userId)
                ->toBe('user1')
                ->and($users[0]->email)
                ->toBe('user1@example.com')
                ->and($users[1])
                ->toBeInstanceOf(UserData::class)
                ->and($users[1]->userId)
                ->toBe('user2')
                ->and($users[2])
                ->toBeInstanceOf(UserData::class)
                ->and($users[2]->userId)
                ->toBe('user3');
        });

        test('returns empty array when organisation has no users', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/org/org456*' => Http::response([
                    'users' => [],
                    'totalUsers' => 0,
                    'currentPage' => 0,
                    'pageSize' => 100,
                    'hasMoreResults' => false,
                ]),
            ]);

            $earhart = createEarhart();
            $users = $earhart->getUsersInOrganisation('org456');

            expect($users)->toBeArray()->toBeEmpty();
        });
    });

    describe('getOrganisations', function () {
        test('returns OrganisationsData with OrganisationData objects', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/query*' => Http::response([
                    'orgs' => [
                        mockOrgResponse(['org_id' => 'org1', 'name' => 'Org One']),
                        mockOrgResponse(['org_id' => 'org2', 'name' => 'Org Two']),
                    ],
                    'total_orgs' => 2,
                    'current_page' => 0,
                    'page_size' => 1000,
                    'has_more_results' => false,
                ]),
            ]);

            $earhart = createEarhart();
            $result = $earhart->getOrganisations();

            expect($result)
                ->toBeInstanceOf(OrganisationsData::class)
                ->and($result->orgs)
                ->toHaveCount(2)
                ->and($result->orgs[0])
                ->toBeInstanceOf(OrganisationData::class)
                ->and($result->orgs[0]->orgId)
                ->toBe('org1')
                ->and($result->orgs[0]->displayName)
                ->toBe('Org One')
                ->and($result->total_orgs)
                ->toBe(2)
                ->and($result->has_more_results)
                ->toBeFalse();
        });
    });

    describe('getOrganisation', function () {
        test('returns OrganisationData object', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/org123' => Http::response(mockOrgResponse([
                    'orgId' => 'org123',
                    'name' => 'Test Organisation',
                ])),
            ]);

            $earhart = createEarhart();
            $org = $earhart->getOrganisation('org123');

            expect($org)
                ->toBeInstanceOf(OrganisationData::class)
                ->and($org->orgId)
                ->toBe('org123')
                ->and($org->displayName)
                ->toBe('Test Organisation');
        });
    });

    describe('cache management', function () {
        test('isCacheEnabled returns false by default', function () {
            $earhart = createEarhart();

            expect($earhart->isCacheEnabled())->toBeFalse();
        });

        test('isCacheEnabled returns true when cache enabled', function () {
            $earhart = new Earhart(
                clientId: 'test-client-id',
                clientSecret: 'test-client-secret',
                callbackUrl: 'https://example.com/callback',
                authUrl: 'https://auth.example.com',
                svixSecret: 'test-svix-secret',
                apiKey: 'test-api-key',
                enableCache: true,
            );

            expect($earhart->isCacheEnabled())->toBeTrue();
        });
    });

    describe('service accessors', function () {
        test('users() returns UserService', function () {
            $earhart = createEarhart();

            expect($earhart->users())->toBeInstanceOf(\LittleGreenMan\Earhart\Services\UserService::class);
        });

        test('organisations() returns OrganisationService', function () {
            $earhart = createEarhart();

            expect($earhart->organisations())
                ->toBeInstanceOf(\LittleGreenMan\Earhart\Services\OrganisationService::class);
        });

        test('cache() returns CacheService', function () {
            $earhart = createEarhart();

            expect($earhart->cache())->toBeInstanceOf(\LittleGreenMan\Earhart\Services\CacheService::class);
        });
    });
});
