<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Middleware;

use Illuminate\Http\Request;
use LittleGreenMan\Earhart\Middleware\VerifyPropelAuthPermission;
use LittleGreenMan\Earhart\PropelAuth\AccessToken;
use LittleGreenMan\Earhart\PropelAuth\UserData;
use LittleGreenMan\Earhart\Tests\TestCase;

uses(TestCase::class);

/**
 * A user as PropelAuth returns it, with org_id_to_org_info left in snake_case.
 *
 * @param  list<string>  $inherited
 * @param  list<string>  $permissions
 */
function userWithMembership(string $orgId, string $role, array $inherited, array $permissions = []): UserData
{
    return UserData::fromArray([
        'userId' => 'user123',
        'email' => 'test@example.com',
        'emailConfirmed' => true,
        'pictureUrl' => 'https://example.com/pic.jpg',
        'createdAt' => 1609459200,
        'lastActiveAt' => 1609459200,
        'orgIdToOrgInfo' => [
            $orgId => [
                'org_id' => $orgId,
                'org_name' => 'Timberwolves',
                'org_metadata' => [],
                'url_safe_org_name' => 'timberwolves',
                'user_role' => $role,
                'inherited_user_roles_plus_current_role' => $inherited,
                'user_permissions' => $permissions,
            ],
        ],
    ]);
}

function runPermission(mixed $user, ?string $orgId, string $required): int
{
    $request = Request::create('/');
    $request->attributes->set('propelauth_user', $user);

    if ($orgId !== null) {
        $request->attributes->set('propelauth_org_id', $orgId);
    }

    return (new VerifyPropelAuthPermission)->handle($request, fn () => response('OK'), $required)->getStatusCode();
}

describe('VerifyPropelAuthPermission', function () {
    test('uses PropelAuth inherited roles', function (string $role, array $inherited, string $required, int $status) {
        expect(runPermission(userWithMembership('org1', $role, $inherited), 'org1', $required))->toBe($status);
    })->with([
        'owner passes member' => ['Owner', ['Owner', 'Admin', 'Member'], 'Member', 200],
        'owner passes admin' => ['Owner', ['Owner', 'Admin', 'Member'], 'Admin', 200],
        'admin passes member' => ['Admin', ['Admin', 'Member'], 'Member', 200],
        'admin fails owner' => ['Admin', ['Admin', 'Member'], 'Owner', 403],
        'member fails admin' => ['Member', ['Member'], 'Admin', 403],
        'case insensitive' => ['Admin', ['Admin', 'Member'], 'admin', 200],
        'custom role exact' => ['Billing', ['Billing'], 'Billing', 200],
        'custom role mismatch' => ['Billing', ['Billing'], 'Support', 403],
    ]);

    test('checks permissions with the permission: prefix', function () {
        $user = userWithMembership('org1', 'Admin', ['Admin', 'Member'], ['propelauth::can_invite']);

        expect(runPermission($user, 'org1', 'permission:propelauth::can_invite'))->toBe(200)
            ->and(runPermission($user, 'org1', 'permission:propelauth::can_setup_saml'))->toBe(403);
    });

    test('rejects a user not in the organisation', function () {
        expect(runPermission(userWithMembership('org1', 'Owner', ['Owner']), 'org2', 'Member'))->toBe(403);
    });

    test('rejects a request without a user', function () {
        expect(runPermission(null, 'org1', 'Member'))->toBe(401);
    });

    test('rejects a request without org context', function () {
        expect(runPermission(userWithMembership('org1', 'Owner', ['Owner']), null, 'Member'))->toBe(400);
    });

    test('works with a verified access token', function () {
        $token = AccessToken::fromClaims([
            'user_id' => 'user123',
            'exp' => time() + 60,
            'org_id_to_org_member_info' => [
                'org1' => [
                    'org_id' => 'org1',
                    'org_name' => 'Timberwolves',
                    'user_role' => 'Admin',
                    'inherited_user_roles_plus_current_role' => ['Admin', 'Member'],
                    'user_permissions' => [],
                ],
            ],
        ]);

        expect(runPermission($token, 'org1', 'Member'))->toBe(200)
            ->and(runPermission($token, 'org1', 'Owner'))->toBe(403);
    });
});
