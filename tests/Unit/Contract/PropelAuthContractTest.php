<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Contract;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LittleGreenMan\Earhart\Exceptions\FeatureNotEnabledException;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Services\OrganisationService;
use LittleGreenMan\Earhart\Services\UserService;
use LittleGreenMan\Earhart\Tests\TestCase;

/*
 * Checks the exact requests Earhart sends against PropelAuth's official Node SDK
 * (github.com/PropelAuth/node-apis) and docs, and that it parses the example
 * responses in PropelAuth's Postman collection (tests/Fixtures/propelauth).
 * See docs/PROPELAUTH_API_AUDIT.md.
 */

uses(TestCase::class);

function fixture(string $name): array
{
    return json_decode(file_get_contents(__DIR__."/../../Fixtures/propelauth/{$name}.json"), true);
}

function contractUsers(): UserService
{
    return new UserService('key', 'https://auth.example.com', new CacheService(false));
}

function contractOrgs(): OrganisationService
{
    return new OrganisationService('key', 'https://auth.example.com', new CacheService(false));
}

/**
 * Run a call against a fake that records the one request it makes.
 *
 * @return array{method: string, path: string, query: array<string, string>, body: mixed, result: mixed}
 */
function capture(\Closure $call, array $response = []): array
{
    $sent = null;

    Http::fake(function (Request $request) use (&$sent, $response) {
        $sent = $request;

        return Http::response($response);
    });

    $result = $call();
    parse_str((string) parse_url($sent->url(), PHP_URL_QUERY), $query);

    return [
        'method' => $sent->method(),
        'path' => parse_url($sent->url(), PHP_URL_PATH),
        'query' => $query,
        'body' => $sent->body() === '' ? null : json_decode($sent->body(), true),
        'result' => $result,
    ];
}

describe('requests match the Node SDK', function () {
    test('sends the documented method, path and fields', function (\Closure $call, string $method, string $path, array $query, ?array $body, array $response = []) {
        $sent = capture($call, $response);

        expect($sent['method'])->toBe($method)
            ->and($sent['path'])->toBe($path)
            ->and($sent['query'])->toEqual($query)
            ->and($method === 'GET' ? null : ($sent['body'] ?: null))->toEqual($body);
    })->with([
        'fetch user by ID' => [
            fn () => contractUsers()->getUser('u1'),
            'GET', '/api/backend/v1/user/u1', ['include_orgs' => 'true'], null, fixture('fetch_user'),
        ],
        'fetch user by email' => [
            fn () => contractUsers()->getUserByEmail('a@b.com', isolatedOrgId: 'o1'),
            'GET', '/api/backend/v1/user/email', ['email' => 'a@b.com', 'include_orgs' => 'true', 'isolated_org_id' => 'o1'], null, fixture('fetch_user'),
        ],
        'query users' => [
            fn () => contractUsers()->queryUsers('te', 'EMAIL', 1, 5, legacyUserId: 'L1', includeOrgs: true, isolatedOrgId: 'o1'),
            'GET', '/api/backend/v1/user/query',
            ['email_or_username' => 'te', 'order_by' => 'EMAIL', 'page_number' => '1', 'page_size' => '5', 'legacy_user_id' => 'L1', 'include_orgs' => 'true', 'isolated_org_id' => 'o1'],
            null, fixture('query_users'),
        ],
        'create user' => [
            fn () => contractUsers()->createUser('a@b.com', 'pw', 'A', 'B', 'ab', ['k' => 'v'], false, true, false, true),
            'POST', '/api/backend/v1/user/', [],
            ['email' => 'a@b.com', 'password' => 'pw', 'first_name' => 'A', 'last_name' => 'B', 'username' => 'ab', 'properties' => ['k' => 'v'],
                'send_email_to_confirm_email_address' => false, 'email_confirmed' => true, 'ignore_domain_restrictions' => false, 'ask_user_to_update_password_on_login' => true],
            ['user_id' => 'u1'],
        ],
        'create magic link' => [
            fn () => contractUsers()->createMagicLink('a@b.com', 'https://r', 1, true, true, false, ['utm_source' => 'x']),
            'POST', '/api/backend/v1/magic_link', [],
            ['email' => 'a@b.com', 'redirect_to_url' => 'https://r', 'expires_in_hours' => 1, 'create_new_user_if_one_doesnt_exist' => true,
                'expire_after_first_use' => true, 'requires_interstitial' => false, 'user_signup_query_parameters' => ['utm_source' => 'x']],
            ['url' => 'https://x'],
        ],
        'migrate user' => [
            fn () => contractUsers()->migrateUserFromExternal('a@b.com', true, '1234', 'hash', 'SECRET', updatePasswordRequired: false, enabled: true, pictureUrl: 'https://p'),
            'POST', '/api/backend/v1/migrate_user/', [],
            ['email' => 'a@b.com', 'email_confirmed' => true, 'existing_user_id' => '1234', 'existing_password_hash' => 'hash',
                'existing_mfa_base32_encoded_secret' => 'SECRET', 'update_password_required' => false, 'enabled' => true, 'picture_url' => 'https://p'],
            ['user_id' => 'u1'],
        ],
        'create access token' => [
            fn () => contractUsers()->createAccessToken('u1', 60, 'o1'),
            'POST', '/api/backend/v1/access_token', [], ['user_id' => 'u1', 'duration_in_minutes' => 60, 'active_org_id' => 'o1'], ['access_token' => 't'],
        ],
        'query orgs' => [
            fn () => contractOrgs()->queryOrganisations('NAME', 0, 10, 'acme', '1234', 'acme.com'),
            'GET', '/api/backend/v1/org/query',
            ['order_by' => 'NAME', 'page_number' => '0', 'page_size' => '10', 'name' => 'acme', 'legacy_org_id' => '1234', 'domain' => 'acme.com'],
            null, fixture('query_orgs'),
        ],
        'users in org' => [
            fn () => contractOrgs()->getOrganisationUsers('o1', 10, 0, 'Admin'),
            'GET', '/api/backend/v1/user/org/o1', ['page_size' => '10', 'page_number' => '0', 'role' => 'Admin', 'include_orgs' => 'false'], null, fixture('users_in_org'),
        ],
        'create org' => [
            fn () => contractOrgs()->createOrganisation('Acme', 'acme.com', true, true, 100, '1234', 'Business Plan'),
            'POST', '/api/backend/v1/org/', [],
            ['name' => 'Acme', 'domain' => 'acme.com', 'enable_auto_joining_by_domain' => true, 'members_must_have_matching_domain' => true,
                'max_users' => 100, 'legacy_org_id' => '1234', 'custom_role_mapping_name' => 'Business Plan'],
            ['org_id' => 'o1', 'name' => 'Acme'],
        ],
        'update org' => [
            fn () => contractOrgs()->updateOrganisation('o1', 'Acme', ['customKey' => 'v'], 'acme.com', ['b.com'], true, false, 100, true, '1234', 'NeverTrust',
                new \DateTimeImmutable('2026-01-20 12:34:56', new \DateTimeZone('Europe/London')), true, 10, 2592000),
            'PUT', '/api/backend/v1/org/o1', [],
            ['name' => 'Acme', 'metadata' => ['customKey' => 'v'], 'domain' => 'acme.com', 'extra_domains' => ['b.com'], 'autojoin_by_domain' => true,
                'restrict_to_domain' => false, 'max_users' => 100, 'can_setup_saml' => true, 'legacy_org_id' => '1234', 'sso_trust_level' => 'NeverTrust',
                'require_2fa_by' => '2026-01-20 12:34:56 UTC', 'password_rotation_enabled' => true, 'password_rotation_history_size' => 10,
                'password_rotation_period' => 2592000],
        ],
        'add user to org' => [
            fn () => contractOrgs()->addUserToOrganisation('o1', 'u1', 'Admin', ['Member']),
            'POST', '/api/backend/v1/org/add_user', [], ['org_id' => 'o1', 'user_id' => 'u1', 'role' => 'Admin', 'additional_roles' => ['Member']],
        ],
        'invite user' => [
            fn () => contractOrgs()->inviteUserToOrganisation('o1', 'a@b.com', 'Member'),
            'POST', '/api/backend/v1/invite_user', [], ['org_id' => 'o1', 'email' => 'a@b.com', 'role' => 'Member'],
        ],
        'change role' => [
            fn () => contractOrgs()->changeUserRole('o1', 'u1', 'Admin', ['Member']),
            'POST', '/api/backend/v1/org/change_role', [], ['org_id' => 'o1', 'user_id' => 'u1', 'role' => 'Admin', 'additional_roles' => ['Member']],
        ],
        'subscribe to role mapping' => [
            fn () => contractOrgs()->subscribeOrgToRoleMapping('o1', 'Paid Plan'),
            'PUT', '/api/backend/v1/org/o1', [], ['custom_role_mapping_name' => 'Paid Plan'],
        ],
        'pending invites' => [
            fn () => contractOrgs()->getPendingInvites('o1', 10, 2),
            'GET', '/api/backend/v1/pending_org_invites', ['org_id' => 'o1', 'page_size' => '10', 'page_number' => '2'], null, fixture('pending_invites'),
        ],
        'revoke invite' => [
            fn () => contractOrgs()->revokePendingInvite('o1', 'a@b.com'),
            'DELETE', '/api/backend/v1/pending_org_invites', [], ['org_id' => 'o1', 'invitee_email' => 'a@b.com'],
        ],
        'SAML connection link' => [
            fn () => contractOrgs()->createSAMLConnectionLink('o1', 86400),
            'POST', '/api/backend/v1/org/o1/create_saml_connection_link', [], ['expires_in_seconds' => 86400], ['url' => 'https://x'],
        ],
        'set SAML IdP metadata' => [
            fn () => contractOrgs()->setSAMLIdPMetadata('o1', 'https://idp/e', 'https://idp/sso', 'CERT', 'Okta'),
            'POST', '/api/backend/v1/saml_idp_metadata', [],
            ['org_id' => 'o1', 'idp_entity_id' => 'https://idp/e', 'idp_sso_url' => 'https://idp/sso', 'idp_certificate' => 'CERT', 'provider' => 'Okta'],
        ],
        'fetch SAML SP metadata' => [
            fn () => contractOrgs()->fetchSAMLMetadata('o1'),
            'GET', '/api/backend/v1/saml_sp_metadata/o1', [], null, fixture('saml_sp_metadata'),
        ],
    ]);
});

describe('responses from the Postman examples parse', function () {
    test('user with memberships', function () {
        $user = capture(fn () => contractUsers()->getUser('u1'), fixture('fetch_user_with_orgs'))['result'];
        $org = $user->org('bdfbbf84-bcee-4dfe-baa5-1e2dc092991d');

        expect($org->orgName)->toBe('Timberwolves')
            ->and($org->userRole)->toBe('Admin')
            ->and($org->inheritedRoles)->toBe(['Admin', 'Owner', 'Member'])
            ->and($org->hasPermission('propelauth::can_invite'))->toBeTrue()
            ->and($user->isAtLeastRoleIn('bdfbbf84-bcee-4dfe-baa5-1e2dc092991d', 'Member'))->toBeTrue()
            ->and($user->properties)->toBe(['tos' => true, 'favoriteSport' => 'value']);
    });

    test('users in org carry their role', function () {
        $page = capture(fn () => contractOrgs()->getOrganisationUsers('o1'), fixture('users_in_org'))['result'];

        expect($page->items[0]->roleInOrg)->toBe('Admin')
            ->and($page->items[0]->metadata)->toBe(['test' => 'test'])
            ->and($page->totalItems)->toBe(1);
    });

    test('query users keeps legacy IDs', function () {
        $page = capture(fn () => contractUsers()->queryUsers(), fixture('query_users'))['result'];

        expect($page->items[1]->legacyUserId)->toBe('google-oauth2|118009659705648350113');
    });

    test('organisation fields', function () {
        $org = capture(fn () => contractOrgs()->getOrganisation('o1'), fixture('fetch_org'))['result'];

        expect($org->maxUsers)->toBe(100)
            ->and($org->domain)->toBe('acme.com')
            ->and($org->domainAutojoin)->toBeTrue()
            ->and($org->customRoleMappingName)->toBe('Business Plan');
    });

    test('role mappings', function () {
        expect(capture(fn () => contractOrgs()->getRoleMappings(), fixture('role_mappings'))['result'])
            ->toBe([
                ['customRoleMappingName' => 'Business Plan', 'numOrgsSubscribed' => 2],
                ['customRoleMappingName' => 'Default', 'numOrgsSubscribed' => 1],
            ]);
    });

    test('pending invites', function () {
        $page = capture(fn () => contractOrgs()->getPendingInvites(), fixture('pending_invites'))['result'];

        expect($page->totalItems)->toBe(1)
            ->and($page->items[0]['inviteeEmail'])->toBe('paul@propelauth.com')
            ->and($page->items[0]['roleInOrg'])->toBe('Owner');
    });

    test('SAML SP metadata', function () {
        $metadata = capture(fn () => contractOrgs()->fetchSAMLMetadata('o1'), fixture('saml_sp_metadata'))['result'];

        expect($metadata->acsUrl)->toBe('https://example.propelauthtest.com/saml/6983/acs');
    });

    test('user-defined keys keep their case', function () {
        $params = capture(fn () => contractUsers()->getUserSignupParams('u1'), ['user_signup_query_parameters' => ['query_param_example' => 'x']])['result'];

        expect($params)->toBe(['query_param_example' => 'x']);
    });

    test('empty and plain-text success bodies', function () {
        Http::fake([
            '*/resend_email_confirmation' => Http::response('', 204),
            '*/invite_user' => Http::response('Invitation email sent. It may take a few minutes to reach their inbox.'),
        ]);

        expect(contractUsers()->resendEmailConfirmation('u1'))->toBeTrue()
            ->and(contractOrgs()->inviteUserToOrganisation('o1', 'a@b.com', 'Member'))->toBeTrue();
    });

    test('426 means B2B is not enabled', function () {
        Http::fake(['*' => Http::response('', 426)]);

        expect(fn () => contractOrgs()->getOrganisation('o1'))->toThrow(FeatureNotEnabledException::class);
    });
});
