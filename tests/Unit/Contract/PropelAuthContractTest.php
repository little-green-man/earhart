<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Contract;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LittleGreenMan\Earhart\Exceptions\FeatureNotEnabledException;
use LittleGreenMan\Earhart\PropelAuth\Insights\ChartCadence;
use LittleGreenMan\Earhart\PropelAuth\Insights\ChartMetric;
use LittleGreenMan\Earhart\PropelAuth\Insights\OrgReportType;
use LittleGreenMan\Earhart\PropelAuth\Insights\UserReportType;
use LittleGreenMan\Earhart\PropelAuth\StepUpGrantType;
use LittleGreenMan\Earhart\Services\ApiKeyService;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Services\InsightsService;
use LittleGreenMan\Earhart\Services\MfaService;
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

function contractKeys(): ApiKeyService
{
    return new ApiKeyService('key', 'https://auth.example.com', new CacheService(false));
}

function contractInsights(): InsightsService
{
    return new InsightsService('key', 'https://auth.example.com', new CacheService(false));
}

function contractMfa(): MfaService
{
    return new MfaService('key', 'https://auth.example.com', new CacheService(false));
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
        'batch fetch by IDs' => [
            fn () => contractUsers()->getUsersByIds(['u1', 'u2'], includeOrgs: true),
            'POST', '/api/backend/v1/user/user_ids', ['include_orgs' => 'true'], ['user_ids' => ['u1', 'u2']], [fixture('fetch_user')],
        ],
        'batch fetch by emails' => [
            fn () => contractUsers()->getUsersByEmails(['a@b.com']),
            'POST', '/api/backend/v1/user/emails', [], ['emails' => ['a@b.com']], [],
        ],
        'batch fetch by usernames' => [
            fn () => contractUsers()->getUsersByUsernames(['ant']),
            'POST', '/api/backend/v1/user/usernames', [], ['usernames' => ['ant']], [],
        ],
        'enable can create orgs' => [
            fn () => contractUsers()->enableCanCreateOrgs('u1'),
            'PUT', '/api/backend/v1/user/u1/can_create_orgs/enable', [], null,
        ],
        'disable can create orgs' => [
            fn () => contractUsers()->disableCanCreateOrgs('u1'),
            'PUT', '/api/backend/v1/user/u1/can_create_orgs/disable', [], null,
        ],
        'OAuth tokens' => [
            fn () => contractUsers()->getOAuthTokens('u1'),
            'GET', '/api/backend/v1/user/u1/oauth_token', [], null,
        ],
        'fresh OAuth token' => [
            fn () => contractUsers()->getFreshOAuthToken('u1', 'google'),
            'GET', '/api/backend/v1/user/u1/google/fresh_token', [], null, ['access_token' => 't', 'token_provider' => 'google'],
        ],
        'employee' => [
            fn () => contractUsers()->getEmployeeEmail('e1'),
            'GET', '/api/backend/v1/employee/e1', [], null, ['email' => 'staff@propelauth.com'],
        ],
        'invite by user ID' => [
            fn () => contractOrgs()->inviteUserToOrganisationById('o1', 'u1', 'Admin', ['Member']),
            'POST', '/api/backend/v1/invite_user_by_id', [], ['org_id' => 'o1', 'user_id' => 'u1', 'role' => 'Admin', 'additional_roles' => ['Member']],
        ],
        'set OIDC IdP metadata (Okta)' => [
            fn () => contractOrgs()->setOIDCIdPMetadata('o1', 'cid', 'secret', 'Okta', true, oktaSsoDomain: 'example.okta.com'),
            'POST', '/api/backend/v1/oidc_idp_metadata', [],
            ['org_id' => 'o1', 'client_id' => 'cid', 'client_secret' => 'secret', 'uses_pkce' => true, 'idp_type' => 'Okta', 'okta_sso_domain' => 'example.okta.com'],
        ],
        'set OIDC IdP metadata (Generic)' => [
            fn () => contractOrgs()->setOIDCIdPMetadata('o1', 'cid', 'secret', 'Generic', false, authUrl: 'https://idp/auth', tokenUrl: 'https://idp/token', userinfoUrl: 'https://idp/userinfo'),
            'POST', '/api/backend/v1/oidc_idp_metadata', [],
            ['org_id' => 'o1', 'client_id' => 'cid', 'client_secret' => 'secret', 'uses_pkce' => false, 'idp_type' => 'Generic',
                'auth_url' => 'https://idp/auth', 'token_url' => 'https://idp/token', 'userinfo_url' => 'https://idp/userinfo'],
        ],
        'SCIM groups' => [
            fn () => contractOrgs()->getScimGroups('o1', 'u1', 10, 0),
            'GET', '/api/backend/v1/scim/o1/groups', ['user_id' => 'u1', 'page_size' => '10', 'page_number' => '0'], null,
            ['total_groups' => 0, 'page_size' => 10, 'page_number' => 0, 'groups' => []],
        ],
        'SCIM group' => [
            fn () => contractOrgs()->getScimGroup('o1', 'g1', 10, 0),
            'GET', '/api/backend/v1/scim/o1/groups/g1', ['members_page_size' => '10', 'members_page_number' => '0'], null,
            ['group_id' => 'g1', 'display_name' => 'Engineering', 'members' => []],
        ],
        'user MFA methods' => [
            fn () => contractMfa()->getUserMfaMethods('u1'),
            'GET', '/api/backend/v1/user/u1/mfa', [], null, ['mfa_setup' => null],
        ],
        'verify TOTP' => [
            fn () => contractMfa()->verifyTotp('u1', '123456', 'DELETE_ACCOUNT', StepUpGrantType::TimeBased, 60),
            'POST', '/api/backend/v1/mfa/step-up/verify-totp', [],
            ['action_type' => 'DELETE_ACCOUNT', 'user_id' => 'u1', 'code' => '123456', 'grant_type' => 'TIME_BASED', 'valid_for_seconds' => 60],
            ['step_up_grant' => 'g'],
        ],
        'send SMS code' => [
            fn () => contractMfa()->sendSmsCode('u1', 'p1', 'DELETE_ACCOUNT'),
            'POST', '/api/backend/v1/mfa/step-up/phone/send', [],
            ['action_type' => 'DELETE_ACCOUNT', 'user_id' => 'u1', 'mfa_phone_id' => 'p1', 'grant_type' => 'ONE_TIME_USE', 'valid_for_seconds' => 300],
            ['challenge_id' => 'c1'],
        ],
        'verify SMS code' => [
            fn () => contractMfa()->verifySmsCode('u1', 'c1', '123456'),
            'POST', '/api/backend/v1/mfa/step-up/phone/verify', [], ['challenge_id' => 'c1', 'user_id' => 'u1', 'code' => '123456'], ['step_up_grant' => 'g'],
        ],
        'verify grant' => [
            fn () => contractMfa()->verifyGrant('u1', 'DELETE_ACCOUNT', 'g'),
            'POST', '/api/backend/v1/mfa/step-up/verify-grant', [], ['action_type' => 'DELETE_ACCOUNT', 'user_id' => 'u1', 'grant' => 'g'],
        ],
        'create API key' => [
            fn () => contractKeys()->createApiKey('u1', 'o1', 1712880246, ['customKey' => 'v'], 'CI'),
            'POST', '/api/backend/v1/end_user_api_keys', [],
            ['user_id' => 'u1', 'org_id' => 'o1', 'expires_at_seconds' => 1712880246, 'metadata' => ['customKey' => 'v'], 'display_name' => 'CI'],
            fixture('create_api_key'),
        ],
        'import API key' => [
            fn () => contractKeys()->importApiKey('legacy-secret', userId: 'u1'),
            'POST', '/api/backend/v1/end_user_api_keys/import', [], ['imported_api_key' => 'legacy-secret', 'user_id' => 'u1'], ['api_key_id' => 'k1'],
        ],
        'fetch API key' => [
            fn () => contractKeys()->getApiKey('85b90f38'),
            'GET', '/api/backend/v1/end_user_api_keys/85b90f38', [], null, fixture('fetch_api_key'),
        ],
        'active API keys' => [
            fn () => contractKeys()->getActiveApiKeys('u1', 'o1', 'a@b.com', 10, 0),
            'GET', '/api/backend/v1/end_user_api_keys', ['user_id' => 'u1', 'org_id' => 'o1', 'user_email' => 'a@b.com', 'page_size' => '10', 'page_number' => '0'], null,
            fixture('active_api_keys'),
        ],
        'archived API keys' => [
            fn () => contractKeys()->getArchivedApiKeys(orgId: 'o1'),
            'GET', '/api/backend/v1/end_user_api_keys/archived', ['org_id' => 'o1', 'page_size' => '10', 'page_number' => '0'], null, fixture('active_api_keys'),
        ],
        'update API key' => [
            fn () => contractKeys()->updateApiKey('k1', 1712848015, ['customKey' => 'v']),
            'PATCH', '/api/backend/v1/end_user_api_keys/k1', [], ['expires_at_seconds' => 1712848015, 'metadata' => ['customKey' => 'v']],
        ],
        'update API key to never expire' => [
            fn () => contractKeys()->updateApiKey('k1', neverExpire: true),
            'PATCH', '/api/backend/v1/end_user_api_keys/k1', [], ['set_to_never_expire' => true],
        ],
        'delete API key' => [
            fn () => contractKeys()->deleteApiKey('k1'),
            'DELETE', '/api/backend/v1/end_user_api_keys/k1', [], null,
        ],
        'validate API key' => [
            fn () => contractKeys()->validateApiKey('Bearer secret'),
            'POST', '/api/backend/v1/end_user_api_keys/validate', [], ['api_key_token' => 'secret'], fixture('validate_api_key'),
        ],
        'validate imported API key' => [
            fn () => contractKeys()->validateImportedApiKey('secret'),
            'POST', '/api/backend/v1/end_user_api_keys/validate_imported', [], ['api_key_token' => 'secret'], fixture('validate_api_key'),
        ],
        'API key usage' => [
            fn () => contractKeys()->getApiKeyUsage(new \DateTimeImmutable('2026-10-06'), 'k1', 'u1', 'o1'),
            'GET', '/api/backend/v1/end_user_api_keys/usage', ['date' => '2026-10-06', 'api_key_id' => 'k1', 'user_id' => 'u1', 'org_id' => 'o1'], null, ['count' => 3],
        ],
        'user report' => [
            fn () => contractInsights()->getUserReport(UserReportType::TopInviter, 30, 10, 0),
            'GET', '/api/backend/v1/user_report/top_inviter', ['report_interval' => '30', 'page_size' => '10', 'page_number' => '0'], null, fixture('top_inviter_report'),
        ],
        'org report' => [
            fn () => contractInsights()->getOrgReport(OrgReportType::Reengagement, 'weekly', 5, 1),
            'GET', '/api/backend/v1/org_report/reengagement', ['report_interval' => 'Weekly', 'page_size' => '5', 'page_number' => '1'], null,
            ['org_reports' => [], 'current_page' => 1, 'total_count' => 0, 'page_size' => 5, 'has_more_results' => false, 'report_time' => 1773330458],
        ],
        'chart metrics' => [
            fn () => contractInsights()->getChartMetrics(ChartMetric::ActiveOrgs, ChartCadence::Daily, '2026-01-01', new \DateTimeImmutable('2026-01-31')),
            'GET', '/api/backend/v1/chart_metrics/active_orgs', ['cadence' => 'Daily', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31'], null, fixture('chart_metrics'),
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

    test('user report from the docs example', function () {
        $report = capture(fn () => contractInsights()->getUserReport(UserReportType::TopInviter), fixture('top_inviter_report'))['result'];
        $record = $report->items[0];

        expect($report->totalItems)->toBe(2)
            ->and($report->reportTime->getTimestamp())->toBe(1773330458)
            ->and($record->email)->toBe('john@acmeinc.com')
            ->and($record->orgs)->toBe([['orgId' => '4f18c3', 'displayName' => 'Acme Inc', 'userRole' => 'Owner']])
            ->and($record->extraProperties)->toBe(['num_invites' => 4]);
    });

    test('chart metrics from the docs example', function () {
        $chart = capture(fn () => contractInsights()->getChartMetrics(ChartMetric::ActiveOrgs), fixture('chart_metrics'))['result'];

        expect($chart->cadence)->toBe(ChartCadence::Daily)
            ->and($chart->toArray())->toBe(['2026-01-01' => 10, '2026-01-02' => 14])
            ->and($chart->points[0]['cadenceCompleted'])->toBeFalse();
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
