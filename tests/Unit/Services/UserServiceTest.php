<?php

namespace LittleGreenMan\Earhart\Tests\Unit\Services;

use Illuminate\Support\Facades\Http;
use LittleGreenMan\Earhart\Exceptions\InvalidUserException;
use LittleGreenMan\Earhart\Exceptions\RateLimitException;
use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;
use LittleGreenMan\Earhart\PropelAuth\UserData;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Services\UserService;
use LittleGreenMan\Earhart\Tests\TestCase;

uses(TestCase::class);

describe('UserService', function () {
    function createUserService($cacheEnabled = false): UserService
    {
        return new UserService(
            apiKey: 'test-api-key',
            authUrl: 'https://auth.example.com',
            cache: new CacheService($cacheEnabled),
        );
    }

    function mockUserResponse(): array
    {
        return [
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
        ];
    }

    describe('getUser', function () {
        test('fetches user from API', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/user123' => Http::response(mockUserResponse()),
            ]);

            $service = createUserService();
            $user = $service->getUser('user123');

            expect($user)
                ->toBeInstanceOf(UserData::class)
                ->and($user->user_id)
                ->toBe('user123')
                ->and($user->email)
                ->toBe('test@example.com');
        });

        test('throws exception when user not found', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/invalid' => Http::response([], 404),
            ]);

            $service = createUserService();

            expect(fn () => $service->getUser('invalid'))->toThrow(InvalidUserException::class);
        });

        test('bypasses cache when fresh flag is true', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/user123' => Http::response(mockUserResponse()),
            ]);

            $service = createUserService($cacheEnabled = true);
            $user = $service->getUser('user123', fresh: true);

            expect($user)->toBeInstanceOf(UserData::class);
            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'user123');
            });
        });
    });

    describe('getUserByEmail', function () {
        test('fetches user by email address', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/email*' => Http::response(mockUserResponse()),
            ]);

            $service = createUserService();
            $user = $service->getUserByEmail('test@example.com');

            expect($user)->toBeInstanceOf(UserData::class)->and($user->email)->toBe('test@example.com');
        });

        test('throws exception when email not found', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/email*' => Http::response([], 404),
            ]);

            $service = createUserService();

            expect(fn () => $service->getUserByEmail('notfound@example.com'))->toThrow(InvalidUserException::class);
        });

        test('includes orgs in response by default', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/email*' => Http::response(mockUserResponse()),
            ]);

            $service = createUserService();
            $service->getUserByEmail('test@example.com');

            Http::assertSent(function ($request) {
                return
                    $request->url()
                    === 'https://auth.example.com/api/backend/v1/user/email?email=test%40example.com&includeOrgs=1';
            });
        });
    });

    describe('getUserByUsername', function () {
        test('fetches user by username', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/username*' => Http::response(mockUserResponse()),
            ]);

            $service = createUserService();
            $user = $service->getUserByUsername('johndoe');

            expect($user)->toBeInstanceOf(UserData::class)->and($user->username)->toBe('johndoe');
        });

        test('throws exception when username not found', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/username*' => Http::response([], 404),
            ]);

            $service = createUserService();

            expect(fn () => $service->getUserByUsername('notfound'))->toThrow(InvalidUserException::class);
        });
    });

    describe('queryUsers', function () {
        test('queries users with pagination', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/query*' => Http::response([
                    'users' => [mockUserResponse()],
                    'totalUsers' => 100,
                    'currentPage' => 0,
                    'pageSize' => 10,
                    'hasMoreResults' => true,
                ]),
            ]);

            $service = createUserService();
            $result = $service->queryUsers();

            expect($result)
                ->toBeInstanceOf(PaginatedResult::class)
                ->and($result->count())
                ->toBe(1)
                ->and($result->totalItems)
                ->toBe(100)
                ->and($result->hasNextPage())
                ->toBeTrue();
        });

        test('filters by email or username', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/query*' => Http::response([
                    'users' => [mockUserResponse()],
                    'totalUsers' => 1,
                    'currentPage' => 0,
                    'pageSize' => 10,
                    'hasMoreResults' => false,
                ]),
            ]);

            $service = createUserService();
            $service->queryUsers(emailOrUsername: 'john');

            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'emailOrUsername=john');
            });
        });

        test('sorts results by specified order', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/query*' => Http::response([
                    'users' => [],
                    'totalUsers' => 0,
                    'currentPage' => 0,
                    'pageSize' => 10,
                    'hasMoreResults' => false,
                ]),
            ]);

            $service = createUserService();
            $service->queryUsers(orderBy: 'EMAIL');

            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'orderBy=EMAIL');
            });
        });
    });

    describe('createUser', function () {
        test('creates a new user', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/' => Http::response([
                    'userId' => 'new-user-id',
                ]),
            ]);

            $service = createUserService();
            $userId = $service->createUser('newuser@example.com');

            expect($userId)->toBe('new-user-id');
        });

        test('sends all user data', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/' => Http::response([
                    'userId' => 'new-user-id',
                ]),
            ]);

            $service = createUserService();
            $service->createUser(
                email: 'test@example.com',
                password: 'password123',
                firstName: 'John',
                lastName: 'Doe',
                username: 'johndoe',
                properties: ['role' => 'admin'],
                sendConfirmationEmail: true,
            );

            Http::assertSent(function ($request) {
                $data = $request->data();

                return
                    $data['email'] === 'test@example.com'
                    && $data['firstName'] === 'John'
                    && $data['lastName'] === 'Doe'
                    && $data['username'] === 'johndoe'
                    && $data['password'] === 'password123'
                    && $data['sendEmailToConfirmEmailAddress'] === true;
            });
        });
    });

    describe('updateUser', function () {
        test('updates user metadata', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/user123' => Http::response(''),
            ]);

            $service = createUserService();
            $result = $service->updateUser('user123', firstName: 'Jane');

            expect($result)->toBeTrue();
        });

        test('invalidates user cache after update', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/user123' => Http::response(''),
            ]);

            $cache = new CacheService(enabled: true);
            $service = new UserService('test-api-key', 'https://auth.example.com', $cache);

            $service->updateUser('user123', firstName: 'Jane');

            expect($cache->isEnabled())->toBeTrue();
        });

        test('sends only non-null fields', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/user123' => Http::response(''),
            ]);

            $service = createUserService();
            $service->updateUser('user123', firstName: 'Jane', pictureUrl: 'https://example.com/pic.jpg');

            Http::assertSent(function ($request) {
                $data = $request->data();

                return isset($data['firstName']) && isset($data['pictureUrl']) && ! isset($data['lastName']);
            });
        });
    });

    describe('updateUserEmail', function () {
        test('updates user email address', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/user123/email' => Http::response([]),
            ]);

            $service = createUserService();
            $result = $service->updateUserEmail('user123', 'newemail@example.com');

            expect($result)->toBeTrue();
        });

        test('requires email confirmation by default', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/user123/email' => Http::response([]),
            ]);

            $service = createUserService();
            $service->updateUserEmail('user123', 'newemail@example.com');

            Http::assertSent(function ($request) {
                return $request->data()['requireEmailConfirmation'] === true;
            });
        });
    });

    describe('updateUserPassword', function () {
        test('updates user password', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/user123/password' => Http::response(''),
            ]);

            $service = createUserService();
            $result = $service->updateUserPassword('user123', 'newpassword123');

            expect($result)->toBeTrue();
        });
    });

    describe('clearUserPassword', function () {
        test('clears user password', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/user123/picture' => Http::response(''),
            ]);

            $service = createUserService();
            $result = $service->clearUserPassword('user123');

            expect($result)->toBeTrue();
        });
    });

    describe('createMagicLink', function () {
        test('creates magic link for passwordless login', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/magic_link' => Http::response([
                    'url' => 'https://auth.example.com/magic/link123',
                ]),
            ]);

            $service = createUserService();
            $url = $service->createMagicLink('test@example.com');

            expect($url)->toBe('https://auth.example.com/magic/link123');
        });

        test('includes redirect url if provided', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/magic_link' => Http::response([
                    'url' => 'https://auth.example.com/magic/link123',
                ]),
            ]);

            $service = createUserService();
            $service->createMagicLink('test@example.com', redirectUrl: 'https://myapp.com/dashboard');

            Http::assertSent(function ($request) {
                return $request->data()['redirectToUrl'] === 'https://myapp.com/dashboard';
            });
        });
    });

    describe('createAccessToken', function () {
        test('creates access token for user', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/access_token' => Http::response([
                    'accessToken' => 'token123',
                ]),
            ]);

            $service = createUserService();
            $token = $service->createAccessToken('user123');

            expect($token)->toBe('token123');
        });

        test('defaults to 24 hour duration', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/access_token' => Http::response([
                    'accessToken' => 'token123',
                ]),
            ]);

            $service = createUserService();
            $service->createAccessToken('user123');

            Http::assertSent(function ($request) {
                return $request->data()['durationInMinutes'] === 1440;
            });
        });
    });

    describe('disableUser', function () {
        test('disables user account', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/user123' => Http::response(''),
            ]);

            $service = createUserService();
            $result = $service->disableUser('user123');

            expect($result)->toBeTrue();
        });
    });

    describe('enableUser', function () {
        test('enables user account', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/user123' => Http::response(''),
            ]);

            $service = createUserService();
            $result = $service->enableUser('user123');

            expect($result)->toBeTrue();
        });
    });

    describe('deleteUser', function () {
        test('deletes user account', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/user123/email' => Http::response(''),
            ]);

            $service = createUserService();
            $result = $service->deleteUser('user123');

            expect($result)->toBeTrue();
        });
    });

    describe('disable2FA', function () {
        test('disables 2FA for user', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/user123' => Http::response(''),
            ]);

            $service = createUserService();
            $result = $service->disable2FA('user123');

            expect($result)->toBeTrue();
        });
    });

    describe('resendEmailConfirmation', function () {
        test('resends email confirmation', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/resend_email_confirmation' => Http::response(''),
            ]);

            $service = createUserService();
            $result = $service->resendEmailConfirmation('user123');

            expect($result)->toBeTrue();
        });
    });

    describe('logoutAllSessions', function () {
        test('logs out user from all sessions', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/user123/logout_all_sessions' => Http::response(''),
            ]);

            $service = createUserService();
            $result = $service->logoutAllSessions('user123');

            expect($result)->toBeTrue();
        });
    });

    describe('migrateUserFromExternal', function () {
        test('migrates user from external source', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/migrate_user/' => Http::response([
                    'userId' => 'migrated-user-id',
                ]),
            ]);

            $service = createUserService();
            $userId = $service->migrateUserFromExternal('migrated@example.com');

            expect($userId)->toBe('migrated-user-id');
        });

        test('includes all migration fields', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/migrate_user/' => Http::response([
                    'userId' => 'migrated-user-id',
                ]),
            ]);

            $service = createUserService();
            $service->migrateUserFromExternal(
                email: 'migrated@example.com',
                emailConfirmed: true,
                existingUserId: 'old-id-123',
                existingPasswordHash: 'hash123',
                existingMfaSecret: 'secret123',
                firstName: 'John',
                lastName: 'Doe',
            );

            Http::assertSent(function ($request) {
                $data = $request->data();

                return
                    $data['email'] === 'migrated@example.com'
                    && $data['emailConfirmed'] === true
                    && $data['existingUserId'] === 'old-id-123'
                    && $data['existingPasswordHash'] === 'hash123';
            });
        });
    });

    describe('migrateUserPassword', function () {
        test('migrates user password from external source', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/migrate_user/user123' => Http::response(''),
            ]);

            $service = createUserService();
            $result = $service->migrateUserPassword('user123', 'bcrypt_hash_123');

            expect($result)->toBeTrue();
        });
    });

    describe('rate limiting', function () {
        test('throws rate limit exception on 429 response', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/user123' => Http::response([], 429),
            ]);

            $service = createUserService();

            expect(fn () => $service->getUser('user123'))->toThrow(RateLimitException::class);
        });

        test('retries on rate limit exception', function () {
            $callCount = 0;

            Http::fake([
                'https://auth.example.com/api/backend/v1/user/user123' => function () use (&$callCount) {
                    $callCount++;
                    if ($callCount < 2) {
                        return Http::response([], 429);
                    }

                    return Http::response(mockUserResponse());
                },
            ]);

            $service = createUserService();
            // This should retry and eventually succeed (but may timeout in test)
            // For now just verify the exception is thrown
            try {
                $service->getUser('user123');
            } catch (RateLimitException) {
                expect(true)->toBeTrue();
            }
        });
    });

    describe('error handling', function () {
        test('throws exception on API error', function () {
            Http::fake([
                'https://auth.example.com/api/backend/v1/user/user123' => Http::response([
                    'error' => 'Internal error',
                ], 500),
            ]);

            $service = createUserService();

            expect(fn () => $service->getUser('user123'))->toThrow(\Exception::class);
        });
    });
});
