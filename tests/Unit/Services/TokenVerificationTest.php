<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Services;

use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LittleGreenMan\Earhart\Exceptions\InvalidTokenException;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\Middleware\VerifyPropelAuthUser;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Services\UserService;
use LittleGreenMan\Earhart\Tests\TestCase;

uses(TestCase::class);

/**
 * @return array{0: string, 1: string} Private and public PEM keys
 */
function rsaKeyPair(): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $private);

    return [$private, openssl_pkey_get_details($key)['key']];
}

/**
 * One key pair for the whole file; generating RSA keys is slow.
 *
 * @return array{0: string, 1: string}
 */
function testKeys(): array
{
    static $keys;

    return $keys ??= rsaKeyPair();
}

function signToken(string $privateKey, array $overrides = []): string
{
    $claims = array_merge(
        json_decode(file_get_contents(__DIR__.'/../../Fixtures/propelauth/access_token_claims.json'), true),
        ['iss' => 'https://auth.example.com', 'iat' => time(), 'exp' => time() + 3600],
        $overrides,
    );

    return JWT::encode(array_filter($claims, fn ($v) => $v !== null), $privateKey, 'RS256');
}

function tokenService(): UserService
{
    return new UserService('test-api-key', 'https://auth.example.com', new CacheService(false));
}

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();
    Http::fake([
        'https://auth.example.com/api/v1/token_verification_metadata' => Http::response(['verifier_key_pem' => testKeys()[1]]),
    ]);
});

describe('verifyAccessToken', function () {
    test('verifies locally and maps the claims', function () {
        $token = tokenService()->verifyAccessToken(signToken(testKeys()[0]));

        expect($token->userId)->toBe('a04d69d7-9347-48a3-aa01-8e7ce9aeee04')
            ->and($token->email)->toBe('test@propelauth.com')
            ->and($token->properties)->toBe(['favoriteSport' => 'value'])
            ->and($token->roleIn('bdfbbf84-bcee-4dfe-baa5-1e2dc092991d'))->toBe('Admin')
            ->and($token->hasPermissionIn('bdfbbf84-bcee-4dfe-baa5-1e2dc092991d', 'propelauth::can_invite'))->toBeTrue()
            ->and($token->isImpersonated())->toBeFalse();
    });

    test('accepts a Bearer prefix', function () {
        expect(tokenService()->verifyAccessToken('Bearer '.signToken(testKeys()[0]))->userId)
            ->toBe('a04d69d7-9347-48a3-aa01-8e7ce9aeee04');
    });

    test('fetches the verifier key once and caches it', function () {
        tokenService()->verifyAccessToken(signToken(testKeys()[0]));
        tokenService()->verifyAccessToken(signToken(testKeys()[0]));

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Bearer test-api-key'));
    });

    test('uses a configured verifier key without a request', function () {
        config(['earhart.token_verification.verifier_key' => str_replace("\n", '\n', testKeys()[1])]);

        tokenService()->verifyAccessToken(signToken(testKeys()[0]));

        Http::assertNothingSent();
    });

    test('rejects invalid tokens', function (\Closure $token) {
        $jwt = $token();
        expect(fn () => tokenService()->verifyAccessToken($jwt))->toThrow(InvalidTokenException::class);
    })->with([
        'expired' => [fn () => signToken(testKeys()[0], ['exp' => time() - 120])],
        'issued in the future' => [fn () => signToken(testKeys()[0], ['iat' => time() + 600])],
        'other environment' => [fn () => signToken(testKeys()[0], ['iss' => 'https://auth.other.com'])],
        'wrong key' => [fn () => signToken(rsaKeyPair()[0])],
        'no user_id' => [fn () => signToken(testKeys()[0], ['user_id' => null])],
        'garbage' => [fn () => 'not-a-jwt'],
    ]);

    test('a failure fetching the verifier key is not reported as an invalid token', function () {
        Http::fake(['https://down.example.com/*' => Http::response([], 500)]);
        $service = new UserService('test-api-key', 'https://down.example.com', new CacheService(false));

        try {
            $service->verifyAccessToken(signToken(testKeys()[0]));
            throw new \LogicException('Expected exception');
        } catch (PropelAuthException $e) {
            expect($e)->not->toBeInstanceOf(InvalidTokenException::class)
                ->and($e->getStatusCode())->toBe(500);
        }
    });

    test('allows 60 seconds of clock skew', function () {
        expect(tokenService()->verifyAccessToken(signToken(testKeys()[0], ['exp' => time() - 30]))->userId)->not->toBeEmpty();
    });

    test('reads the active org from org_member_info', function () {
        $token = tokenService()->verifyAccessToken(signToken(testKeys()[0], [
            'org_id_to_org_member_info' => null,
            'org_member_info' => ['org_id' => 'org1', 'org_name' => 'Acme', 'user_role' => 'Owner'],
        ]));

        expect($token->activeOrgId)->toBe('org1')
            ->and(array_keys($token->orgs))->toBe(['org1']);
    });
});

describe('validateToken', function () {
    test('verifies the token then fetches the current user with memberships', function () {
        Http::fake([
            'https://auth.example.com/api/backend/v1/user/*' => Http::response(
                json_decode(file_get_contents(__DIR__.'/../../Fixtures/propelauth/fetch_user_with_orgs.json'), true),
            ),
        ]);

        $user = tokenService()->validateToken(signToken(testKeys()[0]));

        expect($user->userId)->toBe('a04d69d7-9347-48a3-aa01-8e7ce9aeee04')
            ->and($user->isMemberOf('bdfbbf84-bcee-4dfe-baa5-1e2dc092991d'))->toBeTrue();
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/user/a04d69d7-9347-48a3-aa01-8e7ce9aeee04?include_orgs=true'));
    });

    test('treats a deleted user as an invalid token', function () {
        Http::fake(['https://auth.example.com/api/backend/v1/user/*' => Http::response([], 404)]);

        expect(fn () => tokenService()->validateToken(signToken(testKeys()[0])))->toThrow(InvalidTokenException::class);
    });

    test('the middleware rejects an invalid token with 401', function () {
        $request = Request::create('/');
        $request->headers->set('Authorization', 'Bearer '.signToken(testKeys()[0], ['exp' => time() - 600]));

        $response = (new VerifyPropelAuthUser(tokenService()))->handle($request, fn () => response('OK'));

        expect($response->getStatusCode())->toBe(401);
    });
});
