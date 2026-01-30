<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LittleGreenMan\Earhart\Middleware\VerifyPropelAuthOrg;
use LittleGreenMan\Earhart\PropelAuth\UserData;
use LittleGreenMan\Earhart\Services\OrganisationService;
use LittleGreenMan\Earhart\Tests\TestCase;

uses(TestCase::class);

describe('VerifyPropelAuthOrg', function () {
    function mockUserWithOrgs(array $orgs = []): UserData
    {
        $defaultOrgs = [
            [
                'id' => 'org123',
                'display_name' => 'Acme Corp',
                'user_role' => 'owner',
            ],
        ];

        return UserData::fromArray([
            'user_id' => 'user123',
            'email' => 'test@example.com',
            'email_confirmed' => true,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'picture_url' => 'https://example.com/pic.jpg',
            'properties' => [],
            'locked' => false,
            'enabled' => true,
            'has_password' => true,
            'update_password_required' => false,
            'mfa_enabled' => false,
            'can_create_orgs' => true,
            'created_at' => 1609459200,
            'last_active_at' => 1609459200,
            'orgs' => $orgs ?: $defaultOrgs,
        ]);
    }

    function createOrgMiddleware(?OrganisationService $orgService = null): VerifyPropelAuthOrg
    {
        if (! $orgService) {
            $orgService = mock(OrganisationService::class);
        }

        return new VerifyPropelAuthOrg($orgService);
    }

    test('allows request when user belongs to organisation', function () {
        $user = mockUserWithOrgs([
            [
                'id' => 'org123',
                'display_name' => 'Acme Corp',
                'user_role' => 'owner',
            ],
        ]);

        $middleware = createOrgMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'org_id');

        expect($response->getStatusCode())->toBe(200);
        expect($request->attributes->get('propelauth_org_id'))->toBe('org123');
    });

    test('rejects request when user does not belong to organisation', function () {
        $user = mockUserWithOrgs([
            [
                'id' => 'org999',
                'display_name' => 'Other Corp',
                'user_role' => 'member',
            ],
        ]);

        $middleware = createOrgMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'org_id');

        expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
        expect($response->getContent())->toContain('does not belong to organisation');
    });

    test('rejects request when user not authenticated', function () {
        $middleware = createOrgMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'org_id');

        expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
        $data = json_decode($response->getContent(), true);
        expect($data['error'])->toBe('Unauthorized');
    });

    test('rejects request when org_id parameter missing', function () {
        $user = mockUserWithOrgs();

        $middleware = createOrgMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'org_id');

        expect($response->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST);
        $data = json_decode($response->getContent(), true);
        expect($data['message'])->toContain('Missing organisation parameter');
    });

    test('supports custom organisation parameter name', function () {
        $user = mockUserWithOrgs([
            [
                'id' => 'org456',
                'display_name' => 'Tech Corp',
                'user_role' => 'admin',
            ],
        ]);

        $middleware = createOrgMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('orgId', 'org456');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'orgId');

        expect($response->getStatusCode())->toBe(200);
    });

    test('supports organisation data as objects', function () {
        $user = mockUserWithOrgs();
        // Convert arrays to objects
        $orgs = [];
        foreach ($user->orgs as $org) {
            $orgs[] = (object) $org;
        }
        $user->orgs = $orgs;

        $middleware = createOrgMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'org_id');

        expect($response->getStatusCode())->toBe(200);
    });

    test('stores organisation id in request attributes', function () {
        $user = mockUserWithOrgs();

        $middleware = createOrgMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('org_id', 'org123');

        $passedRequest = null;
        $middleware->handle(
            $request,
            function ($req) use (&$passedRequest) {
                $passedRequest = $req;

                return response('OK');
            },
            'org_id',
        );

        expect($passedRequest->attributes->get('propelauth_org_id'))->toBe('org123');
    });

    test('handles missing user orgs array', function () {
        $user = UserData::fromArray([
            'user_id' => 'user123',
            'email' => 'test@example.com',
            'email_confirmed' => true,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'picture_url' => 'https://example.com/pic.jpg',
            'properties' => [],
            'locked' => false,
            'enabled' => true,
            'has_password' => true,
            'update_password_required' => false,
            'mfa_enabled' => false,
            'can_create_orgs' => true,
            'created_at' => 1609459200,
            'last_active_at' => 1609459200,
        ]);

        $middleware = createOrgMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'org_id');

        expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    });

    test('handles missing orgs gracefully', function () {
        $user = UserData::fromArray([
            'user_id' => 'user123',
            'email' => 'test@example.com',
            'email_confirmed' => true,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'picture_url' => 'https://example.com/pic.jpg',
            'properties' => [],
            'locked' => false,
            'enabled' => true,
            'has_password' => true,
            'update_password_required' => false,
            'mfa_enabled' => false,
            'can_create_orgs' => true,
            'created_at' => 1609459200,
            'last_active_at' => 1609459200,
            // No orgs property
        ]);

        $middleware = createOrgMiddleware();
        $request = Request::create('/', 'GET');
        $request->attributes->set('propelauth_user', $user);
        $request->attributes->set('org_id', 'org123');

        $response = $middleware->handle($request, fn ($req) => response('OK'), 'org_id');

        expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
        $data = json_decode($response->getContent(), true);
        expect($data['message'])->toContain('does not belong to organisation');
    });
});
