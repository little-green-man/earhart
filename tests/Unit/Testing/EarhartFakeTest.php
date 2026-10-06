<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Testing;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use LittleGreenMan\Earhart\Earhart;
use LittleGreenMan\Earhart\Exceptions\InvalidOrgException;
use LittleGreenMan\Earhart\Exceptions\InvalidUserException;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\Exceptions\RateLimitException;
use LittleGreenMan\Earhart\Exceptions\UnauthorizedException;
use LittleGreenMan\Earhart\Exceptions\ValidationException;
use LittleGreenMan\Earhart\Facades\PropelAuth;
use LittleGreenMan\Earhart\Middleware\VerifyPropelAuthUser;
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
            ->and(PropelAuth::getUser($user->userId)->orgs[$org->orgId]['userRole'])->toBe('Admin');

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

        PropelAuth::organisations()->setSAMLIdPMetadata($orgId, '<xml/>');
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
