<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LittleGreenMan\Earhart\Middleware\VerifyPropelAuthPermission;
use LittleGreenMan\Earhart\PropelAuth\UserData;
use LittleGreenMan\Earhart\Tests\TestCase;

uses(TestCase::class);

describe('VerifyPropelAuthPermission', function () {
    function mockUserWithRole(string $orgId, string $role): UserData
    {
        return UserData::fromArray([
            'userId' => 'user123',
            'email' => 'test@example.com',
            'emailConfirmed' => true,
            'firstName' => 'John',
            'lastName' => 'Doe',
            'username' => 'johndoe',
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
            'orgs' => [
                [
                    'id' => $orgId,
                    'display_name' => 'Test Org',
                    'user_role' => $role,
                ],
            ],
        ]);
    }

    function createPermissionMiddleware(): VerifyPropelAuthPermission
    {
        return new VerifyPropelAuthPermission;
    }

    test('allows request when user has required role', function () {
        $user = mockUserWithRole('org123', 'owner');
        $middleware = createPermissionMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('propelauth_org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'member');

        expect($response->getStatusCode())->toBe(200);
    });

    test('allows owner to access member routes', function () {
        $user = mockUserWithRole('org123', 'owner');
        $middleware = createPermissionMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('propelauth_org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'member');

        expect($response->getStatusCode())->toBe(200);
    });

    test('allows admin to access member routes', function () {
        $user = mockUserWithRole('org123', 'admin');
        $middleware = createPermissionMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('propelauth_org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'member');

        expect($response->getStatusCode())->toBe(200);
    });

    test('rejects member accessing admin routes', function () {
        $user = mockUserWithRole('org123', 'member');
        $middleware = createPermissionMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('propelauth_org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'admin');

        expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
        $data = json_decode($response->getContent(), true);
        expect($data['message'])->toContain('does not have required role');
    });

    test('rejects member accessing owner routes', function () {
        $user = mockUserWithRole('org123', 'member');
        $middleware = createPermissionMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('propelauth_org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'owner');

        expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    });

    test('rejects request without authenticated user', function () {
        $middleware = createPermissionMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'member');

        expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
        $data = json_decode($response->getContent(), true);
        expect($data['message'])->toContain('not authenticated');
    });

    test('rejects request without org context', function () {
        $user = mockUserWithRole('org123', 'owner');
        $middleware = createPermissionMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'member');

        expect($response->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST);
        $data = json_decode($response->getContent(), true);
        expect($data['message'])->toContain('Organisation context not found');
    });

    test('supports custom role names', function () {
        $user = mockUserWithRole('org123', 'custom_editor');
        $middleware = createPermissionMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('propelauth_org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'custom_editor');

        expect($response->getStatusCode())->toBe(200);
    });

    test('rejects custom role when not exact match', function () {
        $user = mockUserWithRole('org123', 'custom_viewer');
        $middleware = createPermissionMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('propelauth_org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'custom_editor');

        expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    });

    test('handles role data as objects', function () {
        $user = UserData::fromArray([
            'userId' => 'user123',
            'email' => 'test@example.com',
            'emailConfirmed' => true,
            'firstName' => 'John',
            'lastName' => 'Doe',
            'username' => 'johndoe',
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
            'orgs' => [
                (object) [
                    'id' => 'org123',
                    'display_name' => 'Test Org',
                    'userRole' => 'admin',
                ],
            ],
        ]);

        $middleware = createPermissionMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('propelauth_org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'member');

        expect($response->getStatusCode())->toBe(200);
    });

    test('supports alternative key names', function () {
        $user = UserData::fromArray([
            'userId' => 'user123',
            'email' => 'test@example.com',
            'emailConfirmed' => true,
            'firstName' => 'John',
            'lastName' => 'Doe',
            'username' => 'johndoe',
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
            'orgs' => [
                [
                    'orgId' => 'org123',
                    'display_name' => 'Test Org',
                    'role' => 'owner',
                ],
            ],
        ]);

        $middleware = createPermissionMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('propelauth_org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'member');

        expect($response->getStatusCode())->toBe(200);
    });

    test('handles case insensitive role matching', function () {
        $user = mockUserWithRole('org123', 'OWNER');
        $middleware = createPermissionMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('propelauth_org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'member');

        expect($response->getStatusCode())->toBe(200);
    });

    test('rejects when user not in specified org', function () {
        $user = UserData::fromArray([
            'userId' => 'user123',
            'email' => 'test@example.com',
            'emailConfirmed' => true,
            'firstName' => 'John',
            'lastName' => 'Doe',
            'username' => 'johndoe',
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
            'orgs' => [
                [
                    'id' => 'org999',
                    'display_name' => 'Other Org',
                    'user_role' => 'owner',
                ],
            ],
        ]);

        $middleware = createPermissionMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('propelauth_org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'member');

        expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    });

    test('allows admin to access owner routes (via role hierarchy check)', function () {
        $user = mockUserWithRole('org123', 'owner');
        $middleware = createPermissionMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('propelauth_org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'owner');

        expect($response->getStatusCode())->toBe(200);
    });

    test('rejects admin accessing owner-only routes', function () {
        $user = mockUserWithRole('org123', 'admin');
        $middleware = createPermissionMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('propelauth_org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'owner');

        expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    });
});
