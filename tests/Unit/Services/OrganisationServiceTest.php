<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Services;

use Illuminate\Support\Facades\Http;
use LittleGreenMan\Earhart\Exceptions\InvalidOrgException;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\Exceptions\RateLimitException;
use LittleGreenMan\Earhart\PropelAuth\OrganisationData;
use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Services\OrganisationService;
use LittleGreenMan\Earhart\Tests\TestCase;

uses(TestCase::class);

describe('OrganisationService', function () {
    beforeEach(function () {
        Http::preventStrayRequests();
    });

    function createOrganisationService($cacheEnabled = false): OrganisationService
    {
        return new OrganisationService(
            apiKey: 'test-api-key',
            authUrl: 'https://auth.example.com',
            cache: new CacheService($cacheEnabled),
        );
    }

    function mockOrgResponse(): array
    {
        return [
            'orgId' => 'org123',
            'name' => 'Acme Corp',
            'urlSafeOrgSlug' => 'acme-corp',
            'createdAt' => 1609459200,
            'metadata' => ['industry' => 'technology'],
            'maxUsers' => 100,
            'isSamlConfigured' => false,
            'customRoleMappingName' => 'default',
        ];
    }

    describe('getOrganisation', function () {
        test('fetches organisation from API', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/org123' => Http::response(mockOrgResponse()),
            ]);

            $service = createOrganisationService();
            $org = $service->getOrganisation('org123');

            expect($org)
                ->toBeInstanceOf(OrganisationData::class)
                ->and($org->orgId)
                ->toBe('org123')
                ->and($org->displayName)
                ->toBe('Acme Corp');
        });

        test('throws exception when organisation not found', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/invalid' => Http::response([], 404),
            ]);

            $service = createOrganisationService();

            expect(fn () => $service->getOrganisation('invalid'))->toThrow(InvalidOrgException::class);
        });

        test('uses cache when enabled', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/org123' => Http::response(mockOrgResponse()),
            ]);

            $service = createOrganisationService(cacheEnabled: true);

            $org1 = $service->getOrganisation('org123');
            $org2 = $service->getOrganisation('org123');

            expect($org1->orgId)->toBe($org2->orgId);
            Http::assertSentCount(1);
        });

        test('bypasses cache when fresh flag is true', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/org123' => Http::response(mockOrgResponse()),
            ]);

            $service = createOrganisationService(cacheEnabled: true);

            $org1 = $service->getOrganisation('org123');
            $org2 = $service->getOrganisation('org123', fresh: true);

            expect($org1->orgId)->toBe($org2->orgId);
            Http::assertSentCount(2);
        });
    });

    describe('queryOrganisations', function () {
        test('queries organisations with pagination', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/query*' => Http::response([
                    'orgs' => [mockOrgResponse()],
                    'hasMoreResults' => false,
                ]),
            ]);

            $service = createOrganisationService();
            $result = $service->queryOrganisations();

            expect($result)
                ->toBeInstanceOf(PaginatedResult::class)
                ->and($result->count())
                ->toBe(1)
                ->and($result->hasNextPage())
                ->toBeFalse();
        });

        test('supports sorting', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/query*' => Http::response([
                    'orgs' => [mockOrgResponse()],
                    'hasMoreResults' => false,
                ]),
            ]);

            $service = createOrganisationService();
            $result = $service->queryOrganisations(orderBy: 'CREATED_AT_DESC');

            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'order_by=CREATED_AT_DESC');
            });
        });

        test('supports pagination', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/query*' => Http::response([
                    'orgs' => [mockOrgResponse()],
                    'hasMoreResults' => false,
                ]),
            ]);

            $service = createOrganisationService();
            $result = $service->queryOrganisations(pageNumber: 1, pageSize: 50);

            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'page_number=1') && str_contains($request->url(), 'page_size=50');
            });
        });
    });

    describe('createOrganisation', function () {
        test('creates organisation with name only', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/' => Http::response(['orgId' => 'org_new']),
            ]);

            $service = createOrganisationService();
            $orgId = $service->createOrganisation('New Org');

            expect($orgId)->toBe('org_new');
            Http::assertSent(function ($request) {
                $data = json_decode($request->body(), true);

                return $data['name'] === 'New Org';
            });
        });

        test('sends the create fields PropelAuth accepts', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/' => Http::response(['org_id' => 'org_new', 'name' => 'New Org']),
            ]);

            $orgId = createOrganisationService()->createOrganisation(
                'New Org',
                domain: 'acme.com',
                enableAutoJoiningByDomain: true,
                membersMustHaveMatchingDomain: false,
                maxUsers: 100,
                legacyOrgId: '1234',
                customRoleMappingName: 'Business Plan',
            );

            expect($orgId)->toBe('org_new');
            Http::assertSent(fn ($request) => json_decode($request->body(), true) === [
                'name' => 'New Org',
                'domain' => 'acme.com',
                'enable_auto_joining_by_domain' => true,
                'members_must_have_matching_domain' => false,
                'max_users' => 100,
                'legacy_org_id' => '1234',
                'custom_role_mapping_name' => 'Business Plan',
            ]);
        });
    });

    describe('updateOrganisation', function () {
        test('updates organisation metadata', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/org123' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $result = $service->updateOrganisation('org123', metadata: ['industry' => 'retail']);

            expect($result)->toBeTrue();
        });

        test('sends only non-null fields', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/org123' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $service->updateOrganisation('org123', name: 'Updated Org');

            Http::assertSent(function ($request) {
                $data = json_decode($request->body(), true);

                return isset($data['name']) && ! isset($data['metadata']);
            });
        });

        test('invalidates organisation cache after update', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/org123' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService(cacheEnabled: true);
            $result = $service->updateOrganisation('org123', name: 'Updated');

            expect($result)->toBeTrue();
        });
    });

    describe('deleteOrganisation', function () {
        test('deletes organisation', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/org123' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $result = $service->deleteOrganisation('org123');

            expect($result)->toBeTrue();
        });

        test('throws exception when organisation not found', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/invalid' => Http::response([], 404),
            ]);

            $service = createOrganisationService();

            expect(fn () => $service->deleteOrganisation('invalid'))->toThrow(InvalidOrgException::class);
        });

        test('getOrganisationUsers fetches the next page', function () {
            Http::fake(function ($request) {
                parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);
                $page = (int) ($query['page_number'] ?? 0);

                return Http::response([
                    'users' => [],
                    'total_users' => 2,
                    'current_page' => $page,
                    'page_size' => 1,
                    'has_more_results' => $page === 0,
                ]);
            });

            $next = createOrganisationService()->getOrganisationUsers('org1', pageSize: 1)->nextPage();

            expect($next->currentPage)->toBe(1);
            Http::assertSent(fn ($request) => str_contains($request->url(), 'page_number=1'));
        });

        test('other organisation writes throw when the organisation is not found', function (string $endpoint, \Closure $call) {
            Http::fake([
                "https://auth.example.com{$endpoint}" => Http::response([], 404),
            ]);

            expect(fn () => $call(createOrganisationService()))->toThrow(InvalidOrgException::class);
        })->with([
            'updateOrganisation' => ['/api/backend/v1/org/gone', fn ($s) => $s->updateOrganisation('gone', name: 'X')],
            'allowOrgToSetupSAML' => ['/api/backend/v1/org/gone/allow_saml', fn ($s) => $s->allowOrgToSetupSAML('gone')],
            'migrateOrgToIsolated' => ['/api/backend/v1/isolate_org', fn ($s) => $s->migrateOrgToIsolated('gone')],
        ]);

        test('membership writes throw a 404 PropelAuthException', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/remove_user' => Http::response([], 404),
            ]);

            expect(fn () => createOrganisationService()->removeUserFromOrganisation('org1', 'user1'))
                ->toThrow(PropelAuthException::class, 'PropelAuth API error: 404 on POST /api/backend/v1/org/remove_user');
        });
    });

    describe('addUserToOrganisation', function () {
        test('adds user to organisation', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/add_user' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $result = $service->addUserToOrganisation('org123', 'user_123', 'Member');

            expect($result)->toBeTrue();
        });

        test('sends userId in request', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/add_user' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $service->addUserToOrganisation('org123', 'user_123', 'Admin', ['Member']);

            Http::assertSent(fn ($request) => json_decode($request->body(), true) === [
                'org_id' => 'org123',
                'user_id' => 'user_123',
                'role' => 'Admin',
                'additional_roles' => ['Member'],
            ]);
        });

        test('sends role in request', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/add_user' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $service->addUserToOrganisation('org123', 'user_123', role: 'admin');

            Http::assertSent(function ($request) {
                $data = json_decode($request->body(), true);

                return $data['role'] === 'admin';
            });
        });
    });

    describe('removeUserFromOrganisation', function () {
        test('removes user from organisation', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/remove_user' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $result = $service->removeUserFromOrganisation('org123', 'user123');

            expect($result)->toBeTrue();
        });
    });

    describe('changeUserRole', function () {
        test('changes user role in organisation', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/change_role' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $result = $service->changeUserRole('org123', 'user123', 'admin');

            expect($result)->toBeTrue();
        });

        test('validates role in request', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/change_role' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $service->changeUserRole('org123', 'user123', 'owner');

            Http::assertSent(function ($request) {
                $data = json_decode($request->body(), true);

                return $data['role'] === 'owner';
            });
        });
    });

    describe('inviteUserToOrganisation', function () {
        test('sends invite to user email', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/invite_user' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $result = $service->inviteUserToOrganisation('org123', 'invite@example.com', 'Member');

            expect($result)->toBeTrue();
        });

        test('includes role in invite', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/invite_user' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $service->inviteUserToOrganisation('org123', 'invite@example.com', role: 'admin');

            Http::assertSent(function ($request) {
                $data = json_decode($request->body(), true);

                return $data['role'] === 'admin';
            });
        });
    });

    describe('getRoleMappings', function () {
        test('fetches role mappings', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/custom_role_mappings' => Http::response([
                    'custom_role_mappings' => [
                        ['custom_role_mapping_name' => 'Business Plan', 'num_orgs_subscribed' => 2],
                    ],
                ]),
            ]);

            $service = createOrganisationService();
            $mappings = $service->getRoleMappings();

            expect($mappings)->toBe([['customRoleMappingName' => 'Business Plan', 'numOrgsSubscribed' => 2]]);
        });
    });

    describe('subscribeOrgToRoleMapping', function () {
        test('subscribes organisation to role mapping', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/org123' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $result = $service->subscribeOrgToRoleMapping('org123', 'Paid Plan');

            expect($result)->toBeTrue();
            Http::assertSent(fn ($request) => json_decode($request->body(), true) === ['custom_role_mapping_name' => 'Paid Plan']);
        });
    });

    describe('getPendingInvites', function () {
        test('fetches pending invites for organisation', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/pending_org_invites*' => Http::response([
                    'items' => [
                        [
                            'inviteId' => 'inv_1',
                            'email' => 'pending@example.com',
                            'invitedAt' => 1609459200,
                        ],
                    ],
                    'hasMoreResults' => false,
                ]),
            ]);

            $service = createOrganisationService();
            $invites = $service->getPendingInvites();

            expect($invites)->toBeInstanceOf(PaginatedResult::class)->and($invites->count())->toBe(1);
        });
    });

    describe('revokePendingInvite', function () {
        test('revokes pending invite', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/pending_org_invites' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $result = $service->revokePendingInvite('org123', 'user@example.com');

            expect($result)->toBeTrue();
        });

        test('sends orgId and inviteeEmail in request', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/pending_org_invites*' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $service->revokePendingInvite('org123', 'user@example.com');

            Http::assertSent(function ($request) {
                return $request->method() === 'DELETE' && str_contains($request->url(), 'pending_org_invites');
            });
        });
    });

    describe('allowOrgToSetupSAML', function () {
        test('allows organisation to setup SAML', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/org123/allow_saml' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $result = $service->allowOrgToSetupSAML('org123');

            expect($result)->toBeTrue();
        });
    });

    describe('disallowOrgToSetupSAML', function () {
        test('disallows organisation from setting up SAML', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/org123/disallow_saml' => Http::response([
                    'success' => true,
                ]),
            ]);

            $service = createOrganisationService();
            $result = $service->disallowOrgToSetupSAML('org123');

            expect($result)->toBeTrue();
        });
    });

    describe('createSAMLConnectionLink', function () {
        test('creates SAML connection setup link', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/org123/create_saml_connection_link' => Http::response([
                    'url' => 'https://example.com/saml/setup/link123',
                ]),
            ]);

            $service = createOrganisationService();
            $link = $service->createSAMLConnectionLink('org123');

            expect($link)->toBe('https://example.com/saml/setup/link123');
        });
    });

    describe('fetchSAMLMetadata', function () {
        test('fetches SAML metadata', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/saml_sp_metadata/org123' => Http::response([
                    'entity_id' => 'https://auth.example.com/saml/acme/metadata',
                    'acs_url' => 'https://auth.example.com/saml/acme/acs',
                    'logout_url' => 'https://auth.example.com/saml/acme/logout',
                ]),
            ]);

            $metadata = createOrganisationService()->fetchSAMLMetadata('org123');

            expect($metadata->entityId)->toBe('https://auth.example.com/saml/acme/metadata')
                ->and($metadata->acsUrl)->toBe('https://auth.example.com/saml/acme/acs')
                ->and($metadata->logoutUrl)->toBe('https://auth.example.com/saml/acme/logout');
        });
    });

    describe('setSAMLIdPMetadata', function () {
        test('sets SAML IdP metadata', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/saml_idp_metadata' => Http::response(['success' => true]),
            ]);

            $result = createOrganisationService()->setSAMLIdPMetadata('org123', 'https://idp/entity', 'https://idp/sso', 'CERT', 'Okta');

            expect($result)->toBeTrue();
        });

        test('sends metadata in request', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/saml_idp_metadata' => Http::response(['success' => true]),
            ]);

            createOrganisationService()->setSAMLIdPMetadata('org123', 'https://idp/entity', 'https://idp/sso', 'CERT', 'Okta');

            Http::assertSent(fn ($request) => json_decode($request->body(), true) === [
                'org_id' => 'org123',
                'idp_entity_id' => 'https://idp/entity',
                'idp_sso_url' => 'https://idp/sso',
                'idp_certificate' => 'CERT',
                'provider' => 'Okta',
            ]);
        });
    });

    describe('enableSAMLConnection', function () {
        test('enables SAML connection', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/saml_idp_metadata/go_live/org123' => Http::response([
                    'success' => true,
                ]),
            ]);

            $service = createOrganisationService();
            $result = $service->enableSAMLConnection('org123');

            expect($result)->toBeTrue();
        });
    });

    describe('deleteSAMLConnection', function () {
        test('deletes SAML connection', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/saml_idp_metadata/org123' => Http::response([
                    'success' => true,
                ]),
            ]);

            $service = createOrganisationService();
            $result = $service->deleteSAMLConnection('org123');

            expect($result)->toBeTrue();
        });
    });

    describe('migrateOrgToIsolated', function () {
        test('migrates organisation to isolated mode', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/isolate_org' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $result = $service->migrateOrgToIsolated('org123');

            expect($result)->toBeTrue();
        });

        test('sends org id in request', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/isolate_org' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $service->migrateOrgToIsolated('org123');

            Http::assertSent(function ($request) {
                $data = json_decode($request->body(), true);

                return $data['org_id'] === 'org123';
            });
        });
    });

    describe('error handling', function () {
        test('throws exception on API error', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/org123' => Http::response([
                    'error' => 'Internal error',
                ], 500),
            ]);

            $service = createOrganisationService();

            expect(fn () => $service->getOrganisation('org123'))->toThrow(\Exception::class);
        });

        test('handles validation errors', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/' => Http::response(['error' => 'Invalid request'], 400),
            ]);

            $service = createOrganisationService();

            expect(fn () => $service->createOrganisation(''))->toThrow(\Exception::class);
        });

        test('throws rate limit exception on 429 response', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/org123' => Http::response([], 429, [
                    'Retry-After' => '90',
                ]),
            ]);

            $service = createOrganisationService();

            expect(fn () => $service->getOrganisation('org123'))->toThrow(RateLimitException::class);
        });
    });
});
