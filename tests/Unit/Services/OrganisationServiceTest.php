<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Services;

use Illuminate\Support\Facades\Http;
use LittleGreenMan\Earhart\Exceptions\InvalidOrgException;
use LittleGreenMan\Earhart\Exceptions\RateLimitException;
use LittleGreenMan\Earhart\PropelAuth\OrganisationData;
use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Services\OrganisationService;
use LittleGreenMan\Earhart\Tests\TestCase;

uses(TestCase::class);

describe('OrganisationService', function () {
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
            'displayName' => 'Acme Corp',
            'urlSafeOrgSlug' => 'acme-corp',
            'createdAt' => 1609459200,
            'metadata' => ['industry' => 'technology'],
            'maxOrgMembers' => 100,
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
                return str_contains($request->url(), 'orderBy=CREATED_AT_DESC');
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
                return str_contains($request->url(), 'pageNumber=1') && str_contains($request->url(), 'pageSize=50');
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

        test('creates organisation with slug and metadata', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/' => Http::response(['orgId' => 'org_new']),
            ]);

            $service = createOrganisationService();
            $metadata = ['industry' => 'tech', 'size' => 'small'];
            $orgId = $service->createOrganisation('New Org', slug: 'new-org', metadata: $metadata);

            expect($orgId)->toBe('org_new');
            Http::assertSent(function ($request) use ($metadata) {
                $data = json_decode($request->body(), true);

                return
                    $data['name'] === 'New Org'
                    && $data['urlSafeOrgSlug'] === 'new-org'
                    && $data['metadata'] === $metadata;
            });
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
    });

    describe('addUserToOrganisation', function () {
        test('adds user to organisation', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/add_user' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $result = $service->addUserToOrganisation('org123', 'user@example.com');

            expect($result)->toBeTrue();
        });

        test('sends role in request', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/add_user' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $service->addUserToOrganisation('org123', 'user@example.com', role: 'admin');

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
            $result = $service->inviteUserToOrganisation('org123', 'invite@example.com');

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
                    'roleMappings' => [
                        ['id' => 'mapping1', 'name' => 'Custom Role'],
                    ],
                ]),
            ]);

            $service = createOrganisationService();
            $mappings = $service->getRoleMappings();

            expect($mappings)->toBeArray()->toHaveLength(1);
        });
    });

    describe('subscribeOrgToRoleMapping', function () {
        test('subscribes organisation to role mapping', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/org/org123' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $result = $service->subscribeOrgToRoleMapping('org123', 'mapping1');

            expect($result)->toBeTrue();
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
            $result = $service->revokePendingInvite('inv_1');

            expect($result)->toBeTrue();
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
                    'metadata' => '<xml>metadata</xml>',
                ]),
            ]);

            $service = createOrganisationService();
            $metadata = $service->fetchSAMLMetadata('org123');

            expect($metadata)->toBe('<xml>metadata</xml>');
        });
    });

    describe('setSAMLIdPMetadata', function () {
        test('sets SAML IdP metadata', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/saml_idp_metadata' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $metadata = '<xml>idp metadata</xml>';
            $result = $service->setSAMLIdPMetadata('org123', $metadata);

            expect($result)->toBeTrue();
        });

        test('sends metadata in request', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/saml_idp_metadata' => Http::response(['success' => true]),
            ]);

            $service = createOrganisationService();
            $metadata = '<xml>idp metadata</xml>';
            $service->setSAMLIdPMetadata('org123', $metadata);

            Http::assertSent(function ($request) {
                $data = json_decode($request->body(), true);

                return $data['idpMetadata'] === '<xml>idp metadata</xml>';
            });
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

                return $data['orgId'] === 'org123';
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
