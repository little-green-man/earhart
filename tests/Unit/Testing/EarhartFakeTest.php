<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Testing;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use LittleGreenMan\Earhart\Earhart;
use LittleGreenMan\Earhart\Exceptions\InvalidApiKeyException;
use LittleGreenMan\Earhart\Exceptions\InvalidOrgException;
use LittleGreenMan\Earhart\Exceptions\InvalidUserException;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\Exceptions\RateLimitException;
use LittleGreenMan\Earhart\Exceptions\StepUpMfaException;
use LittleGreenMan\Earhart\Exceptions\UnauthorizedException;
use LittleGreenMan\Earhart\Exceptions\ValidationException;
use LittleGreenMan\Earhart\Facades\PropelAuth;
use LittleGreenMan\Earhart\Middleware\VerifyPropelAuthApiKey;
use LittleGreenMan\Earhart\Middleware\VerifyPropelAuthUser;
use LittleGreenMan\Earhart\PropelAuth\Insights\ChartMetric;
use LittleGreenMan\Earhart\PropelAuth\Insights\OrgReportType;
use LittleGreenMan\Earhart\PropelAuth\Insights\UserReportType;
use LittleGreenMan\Earhart\Services\MfaService;
use LittleGreenMan\Earhart\Services\OrganisationService;
use LittleGreenMan\Earhart\Services\UserService;
use LittleGreenMan\Earhart\Testing\EarhartFake;
use LittleGreenMan\Earhart\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;

uses(TestCase::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

describe('installing', function () {
    test('swaps the facade, container and services', function () {
        $fake = Earhart::fake();

        expect(app('earhart'))->toBe($fake)
            ->and(app(Earhart::class))->toBe($fake)
            ->and(PropelAuth::getFacadeRoot())->toBe($fake)
            ->and(app(UserService::class))->toBe($fake->users())
            ->and(app(OrganisationService::class))->toBe($fake->organisations());
    });

    test('the facade can install it', function () {
        expect(PropelAuth::fake())->toBeInstanceOf(EarhartFake::class)
            ->and(app('earhart'))->toBeInstanceOf(EarhartFake::class);
    });
});

describe('users', function () {
    test('reads seeded users', function () {
        $fake = Earhart::fake();
        $user = $fake->addUser(['email' => 'jane@example.com', 'username' => 'jane']);

        expect(PropelAuth::getUser($user->userId)->email)->toBe('jane@example.com')
            ->and(PropelAuth::getUserByEmail('JANE@example.com')->userId)->toBe($user->userId)
            ->and(PropelAuth::getUserByUsername('jane')->userId)->toBe($user->userId)
            ->and(PropelAuth::queryUsers('jane')->items)->toHaveCount(1);
    });

    test('missing users throw like the real service', function () {
        Earhart::fake();

        expect(fn () => PropelAuth::getUser('gone'))->toThrow(InvalidUserException::class)
            ->and(fn () => PropelAuth::disableUser('gone'))->toThrow(InvalidUserException::class)
            ->and(fn () => PropelAuth::deleteUser('gone'))->toThrow(InvalidUserException::class)
            ->and(fn () => PropelAuth::getUserByEmail('nobody@example.com'))->toThrow(InvalidUserException::class);
    });

    test('writes change the stored user', function () {
        $fake = Earhart::fake();
        $userId = PropelAuth::createUser('jane@example.com', firstName: 'Jane');

        PropelAuth::disableUser($userId);
        expect(PropelAuth::getUser($userId)->enabled)->toBeFalse();

        PropelAuth::enableUser($userId);
        PropelAuth::updateUser($userId, lastName: 'Doe', properties: ['plan' => 'pro']);
        $user = PropelAuth::getUser($userId);

        expect($user->enabled)->toBeTrue()
            ->and($user->firstName)->toBe('Jane')
            ->and($user->lastName)->toBe('Doe')
            ->and($user->properties)->toBe(['plan' => 'pro']);

        PropelAuth::deleteUser($userId);
        expect(fn () => PropelAuth::getUser($userId))->toThrow(InvalidUserException::class);

        $fake->assertUserCreated('jane@example.com')
            ->assertUserDisabled($userId)
            ->assertUserEnabled($userId)
            ->assertUserUpdated($userId)
            ->assertUserDeleted($userId);
    });

    test('creating a duplicate email is a validation error', function () {
        Earhart::fake()->addUser(['email' => 'jane@example.com']);

        expect(fn () => PropelAuth::createUser('jane@example.com'))->toThrow(ValidationException::class);
    });

    test('issued tokens authenticate through the middleware', function () {
        $fake = Earhart::fake();
        $user = $fake->addUser();
        $token = $fake->issueToken($user->userId);

        $status = function (string $token) {
            $request = Request::create('/protected');
            $request->headers->set('Authorization', "Bearer {$token}");

            return app(VerifyPropelAuthUser::class)->handle($request, fn () => response('ok'))->getStatusCode();
        };

        expect($status($token))->toBe(200)
            ->and($status('wrong'))->toBe(401);

        PropelAuth::logoutAllSessions($user->userId);

        expect($status($token))->toBe(401);
    });
});

describe('organisations', function () {
    test('tracks membership and roles', function () {
        $fake = Earhart::fake();
        $user = $fake->addUser();
        $org = $fake->addOrganisation(['name' => 'Acme']);

        PropelAuth::organisations()->addUserToOrganisation($org->orgId, $user->userId, 'Admin');

        expect(PropelAuth::getUsersInOrganisation($org->orgId))->toHaveCount(1)
            ->and(PropelAuth::getUser($user->userId)->roleIn($org->orgId))->toBe('Admin')
            ->and(PropelAuth::getUser($user->userId)->isAtLeastRoleIn($org->orgId, 'Member'))->toBeTrue()
            ->and(PropelAuth::organisations()->getOrganisationUsers($org->orgId)->items[0]->roleInOrg)->toBe('Admin');

        PropelAuth::organisations()->removeUserFromOrganisation($org->orgId, $user->userId);

        expect(PropelAuth::getUsersInOrganisation($org->orgId))->toBeEmpty();

        $fake->assertUserAddedToOrganisation($org->orgId, $user->userId, 'Admin')
            ->assertUserRemovedFromOrganisation($org->orgId, $user->userId);
    });

    test('seeds members and paginates them', function () {
        $fake = Earhart::fake();
        $a = $fake->addUser();
        $b = $fake->addUser();
        $org = $fake->addOrganisation(members: [$a->userId => 'Owner', $b->userId => 'Member']);

        $page = PropelAuth::organisations()->getOrganisationUsers($org->orgId, pageSize: 1);

        expect($page->items)->toHaveCount(1)
            ->and($page->hasNextPage())->toBeTrue()
            ->and($page->allPages())->toHaveCount(2);
    });

    test('SAML and lifecycle writes', function () {
        $fake = Earhart::fake();
        $orgId = PropelAuth::organisations()->createOrganisation('Acme');

        PropelAuth::organisations()->setSAMLIdPMetadata($orgId, 'https://idp/entity', 'https://idp/sso', 'CERT', 'Okta');
        PropelAuth::organisations()->enableSAMLConnection($orgId);

        $org = PropelAuth::getOrganisation($orgId);
        expect($org->isSamlConfigured)->toBeTrue()
            ->and($org->isSamlInTestMode)->toBeFalse()
            ->and(PropelAuth::getOrganisations()->orgs)->toHaveCount(1);

        PropelAuth::organisations()->deleteOrganisation($orgId);

        expect(fn () => PropelAuth::getOrganisation($orgId))->toThrow(InvalidOrgException::class);
        $fake->assertOrganisationCreated('Acme')->assertOrganisationDeleted($orgId);
    });

    test('invites', function () {
        $fake = Earhart::fake();
        $org = $fake->addOrganisation();

        PropelAuth::organisations()->inviteUserToOrganisation($org->orgId, 'new@example.com', 'Member');
        expect(PropelAuth::organisations()->getPendingInvites($org->orgId)->items)->toHaveCount(1);

        PropelAuth::organisations()->revokePendingInvite($org->orgId, 'new@example.com');
        expect(PropelAuth::organisations()->getPendingInvites($org->orgId)->items)->toBeEmpty()
            ->and(fn () => PropelAuth::organisations()->revokePendingInvite($org->orgId, 'new@example.com'))
            ->toThrow(PropelAuthException::class);

        $fake->assertUserInvitedToOrganisation($org->orgId, 'new@example.com');
    });
});

describe('scripted failures', function () {
    test('failNext with a status throws the matching exception once', function () {
        $fake = Earhart::fake();
        $user = $fake->addUser();

        $fake->failNext(UserService::class, 500);

        expect(fn () => PropelAuth::disableUser($user->userId))
            ->toThrow(PropelAuthException::class, 'PropelAuth API error: 500 on disableUser (fake)');

        $fake->assertNotCalled('disableUser');

        PropelAuth::disableUser($user->userId);
        $fake->assertUserDisabled($user->userId);
    });

    test('failNext maps statuses and can target a method', function () {
        $fake = Earhart::fake();
        $user = $fake->addUser();

        $fake->failNext('getUser', 429)->failNext('getUser', 403);

        PropelAuth::enableUser($user->userId);

        expect(fn () => PropelAuth::getUser($user->userId))->toThrow(RateLimitException::class)
            ->and(fn () => PropelAuth::getUser($user->userId))->toThrow(UnauthorizedException::class)
            ->and(PropelAuth::getUser($user->userId)->userId)->toBe($user->userId);
    });

    test('failNext accepts an exception', function () {
        $fake = Earhart::fake();
        $fake->failNext(OrganisationService::class, InvalidOrgException::notFound('x'));

        expect(fn () => PropelAuth::organisations()->createOrganisation('Acme'))->toThrow(InvalidOrgException::class);
    });
});

describe('assertions', function () {
    test('assertCalled checks named arguments', function () {
        $fake = Earhart::fake();
        $user = $fake->addUser();

        PropelAuth::createAccessToken($user->userId, durationInMinutes: 5);

        $fake->assertCalled('createAccessToken', fn ($args) => $args['durationInMinutes'] === 5)
            ->assertCalledTimes('createAccessToken', 1)
            ->assertNotCalled('createAccessToken', fn ($args) => $args['durationInMinutes'] === 10);
    });

    test('assertions fail when the call did not happen', function () {
        $fake = Earhart::fake();

        expect(fn () => $fake->assertUserDisabled('user1'))->toThrow(AssertionFailedError::class);
    });

    test('assertNothingCalled ignores seeding', function () {
        $fake = Earhart::fake();
        $fake->addUser();
        $fake->addOrganisation();

        $fake->assertNothingCalled();

        PropelAuth::queryUsers();

        expect(fn () => $fake->assertNothingCalled())->toThrow(AssertionFailedError::class);
    });

    test('never sends HTTP requests', function () {
        $fake = Earhart::fake();
        $user = $fake->addUser();

        PropelAuth::getUser($user->userId);

        Http::assertNothingSent();
    });
});

describe('3.1 endpoints', function () {
    test('batch fetch, can create orgs, tokens and employees', function () {
        $fake = Earhart::fake();
        $a = $fake->addUser(['email' => 'a@example.com', 'username' => 'a']);
        $b = $fake->addUser(['email' => 'b@example.com']);
        $fake->addOAuthToken($a->userId, 'google', 'ya29')->addEmployee('e1', 'staff@propelauth.com');

        PropelAuth::users()->enableCanCreateOrgs($a->userId);

        expect(array_keys(PropelAuth::users()->getUsersByIds([$a->userId, 'missing', $b->userId])))->toBe([$a->userId, $b->userId])
            ->and(array_keys(PropelAuth::users()->getUsersByEmails(['b@example.com'])))->toBe(['b@example.com'])
            ->and(PropelAuth::getUser($a->userId)->canCreateOrgs)->toBeTrue()
            ->and(PropelAuth::users()->getOAuthTokens($a->userId)['google']->accessToken)->toBe('ya29')
            ->and(PropelAuth::users()->getFreshOAuthToken($a->userId, 'google')->accessToken)->toBe('ya29')
            ->and(PropelAuth::users()->getEmployeeEmail('e1'))->toBe('staff@propelauth.com');

        $fake->assertCalled('enableCanCreateOrgs', fn ($args) => $args['userId'] === $a->userId);
    });

    test('invite by ID, OIDC and SCIM groups', function () {
        $fake = Earhart::fake();
        $user = $fake->addUser(['email' => 'a@example.com']);
        $org = $fake->addOrganisation();
        $groupId = $fake->addScimGroup($org->orgId, 'Engineering', [$user->userId]);
        $fake->addScimGroup($org->orgId, 'Sales');

        PropelAuth::organisations()->inviteUserToOrganisationById($org->orgId, $user->userId, 'Member');
        PropelAuth::organisations()->setOIDCIdPMetadata($org->orgId, 'cid', 'secret', 'Okta', oktaSsoDomain: 'x.okta.com');

        expect(PropelAuth::organisations()->getPendingInvites($org->orgId)->items[0]['inviteeEmail'])->toBe('a@example.com')
            ->and(PropelAuth::getOrganisation($org->orgId)->isSamlConfigured)->toBeTrue()
            ->and(PropelAuth::organisations()->getScimGroups($org->orgId)->totalItems)->toBe(2)
            ->and(PropelAuth::organisations()->getScimGroups($org->orgId, $user->userId)->items[0]->groupId)->toBe($groupId)
            ->and(PropelAuth::organisations()->getScimGroup($org->orgId, $groupId)->memberUserIds)->toBe([$user->userId]);
    });

    test('step-up MFA with TOTP and SMS', function () {
        $fake = Earhart::fake();
        $totpUser = $fake->addUser();
        $smsUser = $fake->addUser();
        $fake->withMfa($totpUser->userId)->withMfa($smsUser->userId, ['p1' => '1234']);

        $grant = PropelAuth::mfa()->verifyTotp($totpUser->userId, '123456', 'DELETE_ACCOUNT');

        expect(PropelAuth::mfa()->verifyGrant($totpUser->userId, 'DELETE_ACCOUNT', $grant))->toBeTrue()
            ->and(PropelAuth::mfa()->verifyGrant($totpUser->userId, 'DELETE_ACCOUNT', $grant))->toBeFalse()
            ->and(fn () => PropelAuth::mfa()->verifyTotp($totpUser->userId, '000000', 'DELETE_ACCOUNT'))
            ->toThrow(StepUpMfaException::class)
            ->and(fn () => PropelAuth::mfa()->verifyTotp($smsUser->userId, '123456', 'DELETE_ACCOUNT'))
            ->toThrow(StepUpMfaException::class);

        $challenge = PropelAuth::mfa()->sendSmsCode($smsUser->userId, 'p1', 'EXPORT');
        $smsGrant = PropelAuth::mfa()->verifySmsCode($smsUser->userId, $challenge, '123456');

        expect(PropelAuth::mfa()->getUserMfaMethods($smsUser->userId)->phoneNumbers)->toBe(['p1' => '1234'])
            ->and(PropelAuth::mfa()->verifyGrant($smsUser->userId, 'EXPORT', $smsGrant))->toBeTrue()
            ->and(app(MfaService::class))->toBe($fake->mfa());
    });
});

describe('API keys', function () {
    test('create, validate, list, update, delete and usage', function () {
        $fake = Earhart::fake();
        $user = $fake->addUser();
        $org = $fake->addOrganisation(members: [$user->userId => 'Admin']);

        $personal = PropelAuth::apiKeys()->createApiKey(userId: $user->userId, displayName: 'CLI');
        $orgKey = $fake->addApiKey(userId: $user->userId, orgId: $org->orgId);

        $validation = PropelAuth::apiKeys()->validateApiKey('Bearer '.$personal->apiKeyToken);
        $orgValidation = PropelAuth::apiKeys()->validateOrgApiKey($orgKey->apiKeyToken);

        expect($validation->isPersonal())->toBeTrue()
            ->and($validation->user->userId)->toBe($user->userId)
            ->and($orgValidation->org->orgId)->toBe($org->orgId)
            ->and($orgValidation->userInOrg->userRole)->toBe('Admin')
            ->and(fn () => PropelAuth::apiKeys()->validatePersonalApiKey($orgKey->apiKeyToken))->toThrow(InvalidApiKeyException::class)
            ->and(PropelAuth::apiKeys()->getActiveApiKeys(userId: $user->userId)->totalItems)->toBe(2)
            ->and(PropelAuth::apiKeys()->getApiKeyUsage(now(), $personal->apiKeyId))->toBe(1);

        PropelAuth::apiKeys()->updateApiKey($personal->apiKeyId, metadata: ['scope' => 'read']);
        PropelAuth::apiKeys()->deleteApiKey($personal->apiKeyId);

        expect(PropelAuth::apiKeys()->getApiKey($personal->apiKeyId)->metadata)->toBe(['scope' => 'read'])
            ->and(fn () => PropelAuth::apiKeys()->validateApiKey($personal->apiKeyToken))->toThrow(InvalidApiKeyException::class)
            ->and(PropelAuth::apiKeys()->getArchivedApiKeys(userId: $user->userId)->items[0]->apiKeyId)->toBe($personal->apiKeyId);

        $fake->assertApiKeyCreated(userId: $user->userId)->assertApiKeyDeleted($personal->apiKeyId);
    });

    test('expired and imported keys', function () {
        $fake = Earhart::fake();
        $user = $fake->addUser();
        $expired = $fake->addApiKey(userId: $user->userId, expiresAt: now()->subMinute());

        PropelAuth::apiKeys()->importApiKey('legacy-secret', userId: $user->userId);

        expect(fn () => PropelAuth::apiKeys()->validateApiKey($expired->apiKeyToken))->toThrow(InvalidApiKeyException::class)
            ->and(PropelAuth::apiKeys()->validateImportedApiKey('legacy-secret')->user->userId)->toBe($user->userId)
            ->and(fn () => PropelAuth::apiKeys()->validateApiKey('legacy-secret'))->toThrow(InvalidApiKeyException::class);
    });

    test('the middleware works with the fake', function () {
        $fake = Earhart::fake();
        $user = $fake->addUser();
        $key = $fake->addApiKey(userId: $user->userId);

        $request = Request::create('/');
        $request->headers->set('Authorization', "Bearer {$key->apiKeyToken}");

        $response = app(VerifyPropelAuthApiKey::class)->handle($request, fn () => response('OK'), 'personal');

        expect($response->getStatusCode())->toBe(200)
            ->and($request->attributes->get('propelauth_user')->userId)->toBe($user->userId);
    });
});

describe('insights', function () {
    test('returns seeded reports and metrics', function () {
        $fake = Earhart::fake()
            ->withUserReport(UserReportType::TopInviter, [
                ['userId' => 'u1', 'email' => 'a@example.com', 'extraProperties' => ['num_invites' => 4]],
            ])
            ->withOrgReport(OrgReportType::Growth, [['orgId' => 'o1', 'name' => 'Acme', 'numUsers' => 12]])
            ->withChartMetrics(ChartMetric::Signups, ['2026-01-02' => 5, '2026-01-01' => 3, '2026-02-01' => 9]);

        $users = PropelAuth::insights()->getUserReport(UserReportType::TopInviter, 30);
        $orgs = PropelAuth::insights()->getOrgReport(OrgReportType::Growth);
        $chart = PropelAuth::insights()->getChartMetrics(ChartMetric::Signups, startDate: '2026-01-01', endDate: '2026-01-31');

        expect($users->items[0]->extraProperties)->toBe(['num_invites' => 4])
            ->and($orgs->items[0]->numUsers)->toBe(12)
            ->and($chart->toArray())->toBe(['2026-01-01' => 3, '2026-01-02' => 5])
            ->and(fn () => PropelAuth::insights()->getUserReport(UserReportType::Churn, 90))->toThrow(\InvalidArgumentException::class);

        $fake->assertCalled('getUserReport', fn ($args) => $args['type'] === UserReportType::TopInviter);
    });
});
