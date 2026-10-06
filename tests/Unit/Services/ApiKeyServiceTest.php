<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use LittleGreenMan\Earhart\Exceptions\ApiKeyRateLimitException;
use LittleGreenMan\Earhart\Exceptions\InvalidApiKeyException;
use LittleGreenMan\Earhart\Middleware\VerifyPropelAuthApiKey;
use LittleGreenMan\Earhart\Services\ApiKeyService;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Tests\TestCase;

uses(TestCase::class);

function apiKeys(): ApiKeyService
{
    return new ApiKeyService('key', 'https://auth.example.com', new CacheService(false));
}

function keyFixture(string $name): array
{
    return json_decode(file_get_contents(__DIR__."/../../Fixtures/propelauth/{$name}.json"), true);
}

/**
 * An org key validation: the Postman example plus an org and the user's membership.
 */
function orgKeyValidation(): array
{
    $body = keyFixture('validate_api_key');
    $body['org'] = ['org_id' => 'bdfbbf84-bcee-4dfe-baa5-1e2dc092991d', 'name' => 'Timberwolves', 'is_saml_configured' => false, 'metadata' => []];
    $body['user_in_org'] = $body['user']['org_id_to_org_info']['bdfbbf84-bcee-4dfe-baa5-1e2dc092991d'];

    return $body;
}

beforeEach(function () {
    Http::preventStrayRequests();
});

describe('validation', function () {
    test('a personal key returns its user', function () {
        Http::fake(['*' => Http::response(keyFixture('validate_api_key'))]);

        $validation = apiKeys()->validatePersonalApiKey('secret');

        expect($validation->isPersonal())->toBeTrue()
            ->and($validation->user->email)->toBe('test@propelauth.com')
            ->and($validation->user->isMemberOf('bdfbbf84-bcee-4dfe-baa5-1e2dc092991d'))->toBeTrue()
            ->and(fn () => apiKeys()->validateOrgApiKey('secret'))->toThrow(InvalidApiKeyException::class);
    });

    test('an org key returns the org and the user\'s membership', function () {
        Http::fake(['*' => Http::response(orgKeyValidation())]);

        $validation = apiKeys()->validateOrgApiKey('secret');

        expect($validation->isOrg())->toBeTrue()
            ->and($validation->org->displayName)->toBe('Timberwolves')
            ->and($validation->userInOrg->userRole)->toBe('Admin')
            ->and($validation->userInOrg->isAtLeastRole('Member'))->toBeTrue()
            ->and(fn () => apiKeys()->validatePersonalApiKey('secret'))->toThrow(InvalidApiKeyException::class);
    });

    test('an invalid key throws InvalidApiKeyException with the field errors', function () {
        Http::fake(['*' => Http::response(['api_key_token' => ['Invalid API Key']], 400)]);

        try {
            apiKeys()->validateApiKey('bad');
            throw new \LogicException('Expected exception');
        } catch (InvalidApiKeyException $e) {
            expect($e->getErrors())->toBe(['api_key_token' => ['Invalid API Key']]);
        }
    });

    test('a key over its own rate limit is not retried', function () {
        Http::fake(['*' => Http::response(['wait_seconds' => 4.2, 'user_facing_error' => 'Slow down', 'error_code' => 'rate_limited'], 429)]);

        try {
            apiKeys()->validateApiKey('secret');
            throw new \LogicException('Expected exception');
        } catch (ApiKeyRateLimitException $e) {
            expect($e->waitSeconds)->toBe(5)
                ->and($e->userFacingError)->toBe('Slow down');
        }

        Http::assertSentCount(1);
    });
});

describe('management', function () {
    test('parses keys and pages', function () {
        Http::fake([
            '*/end_user_api_keys/85b90f38*' => Http::response(keyFixture('fetch_api_key')),
            '*/end_user_api_keys?*' => Http::response(keyFixture('active_api_keys')),
        ]);

        $key = apiKeys()->getApiKey('85b90f38');
        $page = apiKeys()->getActiveApiKeys(userId: 'a04d69d7-9347-48a3-aa01-8e7ce9aeee04');

        expect($key->apiKeyId)->toBe('85b90f382257db0ef057982725997fe1')
            ->and($key->isPersonal())->toBeTrue()
            ->and($key->expiresAt)->toBeNull()
            ->and($page->totalItems)->toBe(1)
            ->and($page->items[0]->userId)->toBe('a04d69d7-9347-48a3-aa01-8e7ce9aeee04');
    });

    test('returns the new key\'s token', function () {
        Http::fake(['*' => Http::response(keyFixture('create_api_key'))]);

        $new = apiKeys()->createApiKey(userId: 'u1', expiresAt: now()->addDay());

        expect($new->apiKeyId)->toBe('6ba56a96c76f86141435c8211309f4c8')
            ->and($new->apiKeyToken)->toStartWith('6ba56a96');
    });

    test('a missing key throws on update and delete', function () {
        Http::fake(['*' => Http::response([], 404)]);

        expect(fn () => apiKeys()->deleteApiKey('gone'))->toThrow(InvalidApiKeyException::class)
            ->and(fn () => apiKeys()->updateApiKey('gone', neverExpire: true))->toThrow(InvalidApiKeyException::class);
    });
});

describe('VerifyPropelAuthApiKey', function () {
    function runKeyMiddleware(?string $kind, ?string $token = 'secret'): array
    {
        $request = Request::create('/');

        if ($token !== null) {
            $request->headers->set('Authorization', "Bearer {$token}");
        }

        $response = (new VerifyPropelAuthApiKey(apiKeys()))->handle($request, fn () => response('OK'), $kind);

        return [$response, $request];
    }

    test('sets the user and org on the request', function () {
        Http::fake(['*' => Http::response(orgKeyValidation())]);

        [$response, $request] = runKeyMiddleware('org');

        expect($response->getStatusCode())->toBe(200)
            ->and($request->attributes->get('propelauth_org_id'))->toBe('bdfbbf84-bcee-4dfe-baa5-1e2dc092991d')
            ->and($request->attributes->get('propelauth_user')->email)->toBe('test@propelauth.com')
            ->and($request->user()->email)->toBe('test@propelauth.com');
    });

    test('rejects the wrong kind of key, a bad key and a missing key', function () {
        Http::fakeSequence()
            ->push(keyFixture('validate_api_key'))
            ->push(['api_key_token' => ['Invalid API Key']], 400);

        expect(runKeyMiddleware('org')[0]->getStatusCode())->toBe(401)
            ->and(runKeyMiddleware(null)[0]->getStatusCode())->toBe(401)
            ->and(runKeyMiddleware(null, null)[0]->getStatusCode())->toBe(401);
    });

    test('passes on a key\'s rate limit', function () {
        Http::fake(['*' => Http::response(['wait_seconds' => 3, 'user_facing_error' => 'Slow down'], 429)]);

        [$response] = runKeyMiddleware(null);

        expect($response->getStatusCode())->toBe(429)
            ->and($response->headers->get('Retry-After'))->toBe('3')
            ->and(json_decode($response->getContent(), true)['message'])->toBe('Slow down');
    });
});
