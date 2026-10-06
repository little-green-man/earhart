<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Middleware;

use Illuminate\Http\Request;
use LittleGreenMan\Earhart\Middleware\VerifyPropelAuthOrg;
use LittleGreenMan\Earhart\PropelAuth\UserData;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Services\OrganisationService;
use LittleGreenMan\Earhart\Tests\TestCase;

uses(TestCase::class);

function memberOf(string ...$orgIds): UserData
{
    $orgs = [];

    foreach ($orgIds as $orgId) {
        $orgs[$orgId] = ['org_id' => $orgId, 'org_name' => 'Acme', 'user_role' => 'Member'];
    }

    return UserData::fromArray([
        'userId' => 'user123',
        'email' => 'test@example.com',
        'emailConfirmed' => true,
        'pictureUrl' => 'https://example.com/pic.jpg',
        'createdAt' => 1609459200,
        'lastActiveAt' => 1609459200,
        'orgIdToOrgInfo' => $orgs,
    ]);
}

function orgRequest(mixed $user, array $attributes = []): Request
{
    $request = Request::create('/');
    $request->attributes->set('propelauth_user', $user);

    foreach ($attributes as $key => $value) {
        $request->attributes->set($key, $value);
    }

    return $request;
}

function orgMiddleware(): VerifyPropelAuthOrg
{
    return new VerifyPropelAuthOrg(new OrganisationService('key', 'https://auth.example.com', new CacheService(false)));
}

describe('VerifyPropelAuthOrg', function () {
    test('allows a member and stores the org ID', function () {
        $request = orgRequest(memberOf('org123'), ['org_id' => 'org123']);

        $response = orgMiddleware()->handle($request, fn () => response('OK'));

        expect($response->getStatusCode())->toBe(200)
            ->and($request->attributes->get('propelauth_org_id'))->toBe('org123');
    });

    test('rejects a non-member', function () {
        $response = orgMiddleware()->handle(orgRequest(memberOf('org999'), ['org_id' => 'org123']), fn () => response('OK'));

        expect($response->getStatusCode())->toBe(403);
    });

    test('rejects a user with no memberships', function () {
        $response = orgMiddleware()->handle(orgRequest(memberOf(), ['org_id' => 'org123']), fn () => response('OK'));

        expect($response->getStatusCode())->toBe(403);
    });

    test('rejects an unauthenticated request', function () {
        $response = orgMiddleware()->handle(orgRequest(null, ['org_id' => 'org123']), fn () => response('OK'));

        expect($response->getStatusCode())->toBe(401);
    });

    test('rejects a request without the org parameter', function () {
        $response = orgMiddleware()->handle(orgRequest(memberOf('org123')), fn () => response('OK'));

        expect($response->getStatusCode())->toBe(400);
    });

    test('reads a custom parameter name', function () {
        $response = orgMiddleware()->handle(orgRequest(memberOf('org123'), ['organisation' => 'org123']), fn () => response('OK'), 'organisation');

        expect($response->getStatusCode())->toBe(200);
    });

    test('rejects an object without membership helpers', function () {
        $response = orgMiddleware()->handle(orgRequest((object) ['orgs' => ['org123']], ['org_id' => 'org123']), fn () => response('OK'));

        expect($response->getStatusCode())->toBe(403);
    });
});

test('does not swallow exceptions from the app', function () {
    expect(fn () => orgMiddleware()->handle(orgRequest(memberOf('org123'), ['org_id' => 'org123']), fn () => throw new \RuntimeException('app bug')))
        ->toThrow(\RuntimeException::class, 'app bug');
});
