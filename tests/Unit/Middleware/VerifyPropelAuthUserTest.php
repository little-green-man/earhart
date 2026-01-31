<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LittleGreenMan\Earhart\Exceptions\InvalidUserException;
use LittleGreenMan\Earhart\Middleware\VerifyPropelAuthUser;
use LittleGreenMan\Earhart\PropelAuth\UserData;
use LittleGreenMan\Earhart\Services\UserService;
use LittleGreenMan\Earhart\Tests\TestCase;
use Mockery\MockInterface;

uses(TestCase::class);

describe('VerifyPropelAuthUser', function () {
    function mockUserData(): array
    {
        return [
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
        ];
    }

    /**
     * @param  UserService|MockInterface|null  $userService
     */
    function createUserMiddleware($userService = null): VerifyPropelAuthUser
    {
        if (! $userService) {
            $userService = mock(UserService::class);
        }

        return new VerifyPropelAuthUser($userService);
    }

    test('allows requests with valid bearer token', function () {
        $userData = mockUserData();
        /** @var UserService&MockInterface $userService */
        $userService = mock(UserService::class);
        $userService->shouldReceive('validateToken')->with('valid-token')->andReturn(UserData::fromArray($userData));

        $middleware = createUserMiddleware($userService);
        $request = Request::create(
            '/',
            'GET',
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer valid-token',
            ],
        );

        $response = $middleware->handle($request, fn ($req) => response('OK'));

        expect($response->getStatusCode())->toBe(200);
        expect($request->attributes->get('propelauth_user'))->toBeInstanceOf(UserData::class);
    });

    test('allows requests with session cookie', function () {
        $userData = mockUserData();
        /** @var UserService&MockInterface $userService */
        $userService = mock(UserService::class);
        $userService->shouldReceive('validateToken')->with('session-token')->andReturn(UserData::fromArray($userData));

        $middleware = createUserMiddleware($userService);
        $request = Request::create('/', 'GET', [], ['propelauth_session' => 'session-token']);

        $response = $middleware->handle($request, fn ($req) => response('OK'));

        expect($response->getStatusCode())->toBe(200);
    });

    test('rejects requests without token', function () {
        $userService = mock(UserService::class);
        $middleware = createUserMiddleware($userService);
        $request = Request::create('/', 'GET');

        $response = $middleware->handle($request, fn ($req) => response('OK'));

        expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
        $data = json_decode($response->getContent(), true);
        expect($data['error'])->toBe('Unauthorized');
    });

    test('rejects requests with invalid token', function () {
        /** @var UserService&MockInterface $userService */
        $userService = mock(UserService::class);
        $userService
            ->shouldReceive('validateToken')
            ->with('invalid-token')
            ->andThrow(InvalidUserException::notFound('user'));

        $middleware = createUserMiddleware($userService);
        $request = Request::create(
            '/',
            'GET',
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer invalid-token',
            ],
        );

        $response = $middleware->handle($request, fn ($req) => response('OK'));

        expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
        $data = json_decode($response->getContent(), true);
        expect($data['error'])->toBe('Unauthorized');
    });

    test('rejects requests with disabled user', function () {
        $userData = array_merge(mockUserData(), ['enabled' => false]);
        /** @var UserService&MockInterface $userService */
        $userService = mock(UserService::class);
        $userService->shouldReceive('validateToken')->with('valid-token')->andReturn(UserData::fromArray($userData));

        $middleware = createUserMiddleware($userService);
        $request = Request::create(
            '/',
            'GET',
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer valid-token',
            ],
        );

        $response = $middleware->handle($request, fn ($req) => response('OK'));

        expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
        $data = json_decode($response->getContent(), true);
        expect($data['message'])->toContain('disabled');
    });

    test('injects user into request', function () {
        $userData = mockUserData();
        /** @var UserService&MockInterface $userService */
        $userService = mock(UserService::class);
        $userService->shouldReceive('validateToken')->andReturn(UserData::fromArray($userData));

        $middleware = createUserMiddleware($userService);
        $request = Request::create(
            '/',
            'GET',
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer valid-token',
            ],
        );

        $passedRequest = null;
        $middleware->handle($request, function ($req) use (&$passedRequest) {
            $passedRequest = $req;

            return response('OK');
        });

        expect($passedRequest->attributes->get('propelauth_user'))->toBeInstanceOf(UserData::class);
        expect($passedRequest->user())->toBeInstanceOf(UserData::class);
    });

    test('extracts token from query parameter', function () {
        $userData = mockUserData();
        /** @var UserService&MockInterface $userService */
        $userService = mock(UserService::class);
        $userService->shouldReceive('validateToken')->with('query-token')->andReturn(UserData::fromArray($userData));

        $middleware = createUserMiddleware($userService);
        $request = Request::create('/', 'GET', ['token' => 'query-token']);

        $response = $middleware->handle($request, fn ($req) => response('OK'));

        expect($response->getStatusCode())->toBe(200);
    });

    test('prioritizes bearer token over cookie', function () {
        $userData = mockUserData();
        /** @var UserService&MockInterface $userService */
        $userService = mock(UserService::class);
        $userService->shouldReceive('validateToken')->with('bearer-token')->andReturn(UserData::fromArray($userData));

        $middleware = createUserMiddleware($userService);
        $request = Request::create(
            '/',
            'GET',
            [],
            ['propelauth_session' => 'cookie-token'],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer bearer-token',
            ],
        );

        $response = $middleware->handle($request, fn ($req) => response('OK'));

        expect($response->getStatusCode())->toBe(200);
    });

    test('handles api exceptions gracefully', function () {
        /** @var UserService&MockInterface $userService */
        $userService = mock(UserService::class);
        $userService->shouldReceive('validateToken')->andThrow(new \Exception('API Error'));

        $middleware = createUserMiddleware($userService);
        $request = Request::create(
            '/',
            'GET',
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer valid-token',
            ],
        );

        $response = $middleware->handle($request, fn ($req) => response('OK'));

        expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
        $data = json_decode($response->getContent(), true);
        expect($data['message'])->toContain('API Error');
    });

    test('requires bearer prefix in authorization header', function () {
        $userService = mock(UserService::class);
        $middleware = createUserMiddleware($userService);
        $request = Request::create(
            '/',
            'GET',
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Basic invalid-token',
            ],
        );

        $response = $middleware->handle($request, fn ($req) => response('OK'));

        expect($response->getStatusCode())->toBe(Response::HTTP_UNAUTHORIZED);
    });
});
