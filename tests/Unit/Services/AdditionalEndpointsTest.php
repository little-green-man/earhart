<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Services;

use Illuminate\Support\Facades\Http;
use LittleGreenMan\Earhart\Exceptions\FeatureNotEnabledException;
use LittleGreenMan\Earhart\Exceptions\InvalidUserException;
use LittleGreenMan\Earhart\Exceptions\StepUpMfaException;
use LittleGreenMan\Earhart\PropelAuth\Insights\OrgReportType;
use LittleGreenMan\Earhart\PropelAuth\Insights\UserReportType;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Services\InsightsService;
use LittleGreenMan\Earhart\Services\MfaService;
use LittleGreenMan\Earhart\Services\OrganisationService;
use LittleGreenMan\Earhart\Services\UserService;
use LittleGreenMan\Earhart\Tests\TestCase;

uses(TestCase::class);

function endpointUsers(): UserService
{
    return new UserService('key', 'https://auth.example.com', new CacheService(false));
}

function endpointOrgs(): OrganisationService
{
    return new OrganisationService('key', 'https://auth.example.com', new CacheService(false));
}

function endpointMfa(): MfaService
{
    return new MfaService('key', 'https://auth.example.com', new CacheService(false));
}

beforeEach(function () {
    Http::preventStrayRequests();
});

describe('batch user fetch', function () {
    test('keys users by ID, email or username', function () {
        $user = json_decode(file_get_contents(__DIR__.'/../../Fixtures/propelauth/fetch_user.json'), true);
        Http::fake(['*' => Http::response([$user])]);

        expect(array_keys(endpointUsers()->getUsersByIds(['a04d69d7-9347-48a3-aa01-8e7ce9aeee04'])))->toBe(['a04d69d7-9347-48a3-aa01-8e7ce9aeee04'])
            ->and(array_keys(endpointUsers()->getUsersByEmails(['test@propelauth.com'])))->toBe(['test@propelauth.com'])
            ->and(array_keys(endpointUsers()->getUsersByUsernames(['ant'])))->toBe(['ant']);
    });

    test('makes no request for an empty list', function () {
        expect(endpointUsers()->getUsersByIds([]))->toBe([]);

        Http::assertNothingSent();
    });
});

describe('social login tokens', function () {
    test('maps tokens by provider', function () {
        Http::fake(['*' => Http::response([
            'google' => [
                'access_token' => 'ya29',
                'refresh_token' => 'r1',
                'token_provider' => 'google',
                'token_expiration' => 1893456000,
                'authorized_scopes' => ['email'],
            ],
        ])]);

        $tokens = endpointUsers()->getOAuthTokens('u1');

        expect($tokens['google']->accessToken)->toBe('ya29')
            ->and($tokens['google']->refreshToken)->toBe('r1')
            ->and($tokens['google']->authorizedScopes)->toBe(['email'])
            ->and($tokens['google']->expiresAt->getTimestamp())->toBe(1893456000)
            ->and($tokens['google']->isExpired())->toBeFalse();
    });

    test('a missing user throws', function () {
        Http::fake(['*' => Http::response([], 404)]);

        expect(fn () => endpointUsers()->getOAuthTokens('gone'))->toThrow(InvalidUserException::class);
    });
});

describe('SCIM groups', function () {
    test('pages groups and reads members', function () {
        Http::fake([
            '*/groups?*' => Http::response([
                'total_groups' => 3,
                'page_size' => 2,
                'page_number' => 0,
                'groups' => [
                    ['group_id' => 'g1', 'display_name' => 'Engineering', 'external_id_from_idp' => 'abc123'],
                    ['group_id' => 'g2', 'display_name' => 'Sales', 'external_id_from_idp' => 'abc1234'],
                ],
            ]),
            '*/groups/g1*' => Http::response([
                'group_id' => 'g1',
                'display_name' => 'Engineering',
                'external_id_from_idp' => 'abc123',
                'members' => [['user_id' => 'u1'], ['user_id' => 'u2']],
            ]),
        ]);

        $page = endpointOrgs()->getScimGroups('o1', pageSize: 2);
        $group = endpointOrgs()->getScimGroup('o1', 'g1');

        expect($page->totalItems)->toBe(3)
            ->and($page->hasNextPage())->toBeTrue()
            ->and($page->items[0]->displayName)->toBe('Engineering')
            ->and($page->items[1]->externalIdFromIdp)->toBe('abc1234')
            ->and($group->memberUserIds)->toBe(['u1', 'u2']);
    });
});

describe('step-up MFA', function () {
    test('reads TOTP and SMS setups', function () {
        Http::fakeSequence()
            ->push(['mfa_setup' => ['type' => 'Totp']])
            ->push(['mfa_setup' => ['type' => 'Phone', 'phone_numbers' => [['mfa_phone_number_suffix' => '1234', 'mfa_phone_id' => 'p1']]]])
            ->push(['mfa_setup' => null]);

        $totp = endpointMfa()->getUserMfaMethods('u1');
        $sms = endpointMfa()->getUserMfaMethods('u1');

        expect($totp->usesTotp())->toBeTrue()
            ->and($sms->usesSms())->toBeTrue()
            ->and($sms->phoneNumbers)->toBe(['p1' => '1234'])
            ->and(endpointMfa()->getUserMfaMethods('u1'))->toBeNull();
    });

    test('returns the grant and challenge IDs', function () {
        Http::fakeSequence()
            ->push(['step_up_grant' => 'grant-1'])
            ->push(['challenge_id' => 'c1']);

        expect(endpointMfa()->verifyTotp('u1', '123456', 'DELETE_ACCOUNT'))->toBe('grant-1')
            ->and(endpointMfa()->sendSmsCode('u1', 'p1', 'DELETE_ACCOUNT'))->toBe('c1');
    });

    test('maps error codes', function (int $status, string $errorCode, string $exception) {
        Http::fake(['*' => Http::response(['error_code' => $errorCode], $status)]);

        expect(fn () => endpointMfa()->verifyTotp('u1', '000000', 'DELETE_ACCOUNT'))->toThrow($exception);
    })->with([
        'incorrect code' => [400, 'incorrect_mfa_code', StepUpMfaException::class],
        'not enabled' => [400, 'mfa_not_enabled', StepUpMfaException::class],
        'unknown user' => [404, 'user_not_found', InvalidUserException::class],
        'not on plan' => [403, 'feature_gated', FeatureNotEnabledException::class],
    ]);

    test('the exception says why', function () {
        Http::fake(['*' => Http::response(['error_code' => 'incorrect_mfa_code'], 400)]);

        try {
            endpointMfa()->verifyTotp('u1', '000000', 'DELETE_ACCOUNT');
            throw new \LogicException('Expected exception');
        } catch (StepUpMfaException $e) {
            expect($e->isIncorrectCode())->toBeTrue()
                ->and($e->getErrorCode())->toBe('incorrect_mfa_code');
        }
    });

    test('an unknown grant is false, not an exception', function () {
        Http::fakeSequence()
            ->push([], 200)
            ->push(['error_code' => 'invalid_request_fields', 'field_errors' => ['grant' => 'grant_not_found']], 400);

        expect(endpointMfa()->verifyGrant('u1', 'DELETE_ACCOUNT', 'good'))->toBeTrue()
            ->and(endpointMfa()->verifyGrant('u1', 'DELETE_ACCOUNT', 'bad'))->toBeFalse();
    });
});

describe('insights', function () {
    test('rejects an interval the report does not offer', function () {
        $insights = new InsightsService('key', 'https://auth.example.com', new CacheService(false));

        expect(fn () => $insights->getUserReport(UserReportType::Churn, 90))
            ->toThrow(\InvalidArgumentException::class, 'use one of: 7, 14, 30');

        Http::assertNothingSent();
    });

    test('pages org reports', function () {
        Http::fakeSequence()
            ->push(['org_reports' => [['id' => 'r1', 'report_id' => 'x', 'org_id' => 'o1', 'name' => 'Acme', 'num_users' => 3, 'org_created_at' => 1700000000, 'extra_properties' => []]],
                'current_page' => 0, 'total_count' => 2, 'page_size' => 1, 'has_more_results' => true])
            ->push(['org_reports' => [['id' => 'r2', 'report_id' => 'x', 'org_id' => 'o2', 'name' => 'Beta', 'num_users' => 1, 'org_created_at' => 1700000000, 'extra_properties' => []]],
                'current_page' => 1, 'total_count' => 2, 'page_size' => 1, 'has_more_results' => false]);

        $insights = new InsightsService('key', 'https://auth.example.com', new CacheService(false));
        $all = $insights->getOrgReport(OrgReportType::Growth, pageSize: 1)->allPages();

        expect($all->map(fn ($org) => $org->name)->all())->toBe(['Acme', 'Beta'])
            ->and($all->first()->numUsers)->toBe(3);
    });
});
