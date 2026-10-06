<?php

namespace LittleGreenMan\Earhart\Testing;

use Illuminate\Support\Str;
use LittleGreenMan\Earhart\Exceptions\InvalidTokenException;
use LittleGreenMan\Earhart\Exceptions\InvalidUserException;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\PropelAuth\AccessToken;
use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;
use LittleGreenMan\Earhart\PropelAuth\UserData;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Services\UserService;

/**
 * In-memory UserService used by Earhart::fake().
 */
class FakeUserService extends UserService
{
    use RecordsCalls;

    public function __construct(FakeState $state)
    {
        parent::__construct('fake-api-key', 'https://auth.example.test', new CacheService(false));
        $this->state = $state;
    }

    /**
     * Store a user without recording a call. Returns its attributes.
     *
     * @param  array<string, mixed>  $attributes  UserData fields, in camelCase
     * @return array<string, mixed>
     */
    public function store(array $attributes = []): array
    {
        $userId = $attributes['userId'] ?? (string) Str::uuid();
        $now = now()->getTimestamp();

        return $this->state->users[$userId] = array_merge([
            'userId' => $userId,
            'email' => "user-{$userId}@example.com",
            'emailConfirmed' => true,
            'firstName' => null,
            'lastName' => null,
            'username' => null,
            'pictureUrl' => 'https://img.propelauth.com/default.png',
            'properties' => [],
            'locked' => false,
            'enabled' => true,
            'hasPassword' => false,
            'updatePasswordRequired' => false,
            'mfaEnabled' => false,
            'canCreateOrgs' => false,
            'createdAt' => $now,
            'lastActiveAt' => $now,
        ], $attributes, ['userId' => $userId]);
    }

    public function getUser(string $userId, bool $fresh = false, bool $includeOrgs = true): UserData
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($userId, $includeOrgs) {
            $this->requireUser($userId);

            return $this->state->userData($userId, $includeOrgs);
        });
    }

    public function validateToken(string $token, bool $fresh = false): UserData
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->state->userData($this->tokenUserId($token)));
    }

    public function verifyAccessToken(string $token): AccessToken
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($token) {
            $userId = $this->tokenUserId($token);
            $user = $this->state->users[$userId];

            return AccessToken::fromClaims([
                'user_id' => $userId,
                'email' => $user['email'],
                'first_name' => $user['firstName'],
                'last_name' => $user['lastName'],
                'username' => $user['username'],
                'legacy_user_id' => $user['legacyUserId'] ?? null,
                'properties' => $user['properties'],
                'org_id_to_org_member_info' => $this->state->orgInfo($userId),
                'iss' => 'https://auth.example.test',
                'iat' => now()->getTimestamp(),
                'exp' => now()->addHour()->getTimestamp(),
            ]);
        });
    }

    public function forgetVerifierKey(): void
    {
        //
    }

    public function getUserByEmail(string $email, bool $includeOrgs = true, ?string $isolatedOrgId = null): UserData
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($email, $includeOrgs) {
            $userId = $this->findBy('email', $email) ?? throw InvalidUserException::byEmail($email);

            return $this->state->userData($userId, $includeOrgs);
        });
    }

    public function getUserByUsername(string $username, bool $includeOrgs = true, ?string $isolatedOrgId = null): UserData
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($username, $includeOrgs) {
            $userId = $this->findBy('username', $username) ?? throw InvalidUserException::byUsername($username);

            return $this->state->userData($userId, $includeOrgs);
        });
    }

    public function queryUsers(
        ?string $emailOrUsername = null,
        ?string $orderBy = 'CREATED_AT_DESC',
        int $pageNumber = 0,
        int $pageSize = 10,
        ?string $legacyUserId = null,
        bool $includeOrgs = false,
        ?string $isolatedOrgId = null,
    ): PaginatedResult {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($emailOrUsername, $orderBy, $pageNumber, $pageSize, $legacyUserId, $includeOrgs, $isolatedOrgId) {
            $users = array_filter($this->state->users, fn (array $user) => ($emailOrUsername === null
                || str_contains(strtolower($user['email']), strtolower($emailOrUsername))
                || str_contains(strtolower((string) $user['username']), strtolower($emailOrUsername)))
                && ($legacyUserId === null || ($user['legacyUserId'] ?? null) === $legacyUserId));

            $items = array_map(fn (string $id) => $this->state->userData($id, $includeOrgs), array_keys($users));

            return PaginatedResult::from(
                $this->state->page($items, $pageNumber, $pageSize),
                fn (int $nextPage) => $this->queryUsers($emailOrUsername, $orderBy, $nextPage, $pageSize, $legacyUserId, $includeOrgs, $isolatedOrgId),
            );
        });
    }

    public function createUser(
        string $email,
        ?string $password = null,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $username = null,
        ?array $properties = null,
        bool $sendConfirmationEmail = false,
        ?bool $emailConfirmed = null,
        ?bool $ignoreDomainRestrictions = null,
        ?bool $askUserToUpdatePasswordOnLogin = null,
    ): string {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($email, $password, $firstName, $lastName, $username, $properties, $emailConfirmed, $askUserToUpdatePasswordOnLogin) {
            $this->ensureEmailIsFree($email);

            return $this->store([
                'email' => $email,
                'emailConfirmed' => $emailConfirmed ?? false,
                'updatePasswordRequired' => $askUserToUpdatePasswordOnLogin ?? false,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'username' => $username,
                'properties' => $properties ?? [],
                'hasPassword' => $password !== null,
            ])['userId'];
        });
    }

    public function updateUser(
        string $userId,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $username = null,
        ?string $pictureUrl = null,
        ?array $properties = null,
        ?bool $updatePasswordRequired = null,
        ?string $legacyUserId = null,
    ): bool {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($userId, $firstName, $lastName, $username, $pictureUrl, $properties, $updatePasswordRequired, $legacyUserId) {
            $this->requireUser($userId);

            $changes = array_filter(compact('firstName', 'lastName', 'username', 'pictureUrl', 'updatePasswordRequired', 'legacyUserId'), fn ($v) => $v !== null);

            if ($properties !== null) {
                $changes['properties'] = array_merge($this->state->users[$userId]['properties'], $properties);
            }

            $this->state->users[$userId] = array_merge($this->state->users[$userId], $changes);

            return true;
        });
    }

    public function updateUserEmail(string $userId, string $newEmail, bool $requireConfirmation = true): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($userId, $newEmail, $requireConfirmation) {
            $this->requireUser($userId);
            $this->ensureEmailIsFree($newEmail, $userId);

            return $this->set($userId, ['email' => $newEmail, 'emailConfirmed' => ! $requireConfirmation]);
        });
    }

    public function updateUserPassword(string $userId, string $password, bool $askForUpdateOnLogin = false): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($userId, [
            'hasPassword' => true,
            'updatePasswordRequired' => $askForUpdateOnLogin,
        ]));
    }

    public function clearUserPassword(string $userId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($userId, ['hasPassword' => false]));
    }

    public function createMagicLink(
        string $email,
        ?string $redirectUrl = null,
        ?int $expiresInHours = 24,
        bool $createIfNotExists = false,
        ?bool $expireAfterFirstUse = null,
        ?bool $requiresInterstitial = null,
        ?array $userSignupQueryParameters = null,
    ): string {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($email, $createIfNotExists) {
            if ($this->findBy('email', $email) === null) {
                if (! $createIfNotExists) {
                    throw InvalidUserException::byEmail($email);
                }

                $this->store(['email' => $email]);
            }

            return 'https://auth.example.test/magic-link/'.Str::random(32);
        });
    }

    public function createAccessToken(
        string $userId,
        int $durationInMinutes = 1440,
        ?string $activeOrgId = null,
    ): string {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($userId) {
            $this->requireUser($userId);

            return $this->issueToken($userId);
        });
    }

    /**
     * Issue an access token for a user without recording a call.
     */
    public function issueToken(string $userId, ?string $token = null): string
    {
        $token ??= Str::random(40);
        $this->state->tokens[$token] = $userId;

        return $token;
    }

    public function disableUser(string $userId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($userId, ['enabled' => false]));
    }

    public function enableUser(string $userId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($userId, ['enabled' => true]));
    }

    public function deleteUser(string $userId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($userId) {
            $this->requireUser($userId);

            unset($this->state->users[$userId]);

            foreach (array_keys($this->state->members) as $orgId) {
                unset($this->state->members[$orgId][$userId]);
            }

            $this->state->tokens = array_filter($this->state->tokens, fn (string $id) => $id !== $userId);

            return true;
        });
    }

    public function disable2FA(string $userId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($userId, ['mfaEnabled' => false]));
    }

    public function resendEmailConfirmation(string $userId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($userId, []));
    }

    public function logoutAllSessions(string $userId): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($userId) {
            $this->requireUser($userId);

            $this->state->tokens = array_filter($this->state->tokens, fn (string $id) => $id !== $userId);

            return true;
        });
    }

    public function getUserSignupParams(string $userId): array
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($userId) {
            $this->requireUser($userId);

            return [];
        });
    }

    public function migrateUserFromExternal(
        string $email,
        bool $emailConfirmed = false,
        ?string $existingUserId = null,
        ?string $existingPasswordHash = null,
        ?string $existingMfaSecret = null,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $username = null,
        ?array $properties = null,
        ?bool $updatePasswordRequired = null,
        ?bool $enabled = null,
        ?string $pictureUrl = null,
    ): string {
        return $this->fake(__FUNCTION__, get_defined_vars(), function () use ($email, $emailConfirmed, $existingUserId, $existingPasswordHash, $existingMfaSecret, $firstName, $lastName, $username, $properties, $updatePasswordRequired, $enabled, $pictureUrl) {
            $this->ensureEmailIsFree($email);

            return $this->store(array_filter([
                'legacyUserId' => $existingUserId,
                'updatePasswordRequired' => $updatePasswordRequired,
                'enabled' => $enabled,
                'pictureUrl' => $pictureUrl,
            ], fn ($v) => $v !== null) + [
                'email' => $email,
                'emailConfirmed' => $emailConfirmed,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'username' => $username,
                'properties' => $properties ?? [],
                'hasPassword' => $existingPasswordHash !== null,
                'mfaEnabled' => $existingMfaSecret !== null,
            ])['userId'];
        });
    }

    public function migrateUserPassword(string $userId, string $passwordHash): bool
    {
        return $this->fake(__FUNCTION__, get_defined_vars(), fn () => $this->set($userId, ['hasPassword' => true]));
    }

    protected function tokenUserId(string $token): string
    {
        $userId = $this->state->tokens[preg_replace('/^Bearer\s+/i', '', trim($token))] ?? null;

        if ($userId === null || ! isset($this->state->users[$userId])) {
            throw InvalidTokenException::because('not issued by the fake');
        }

        return $userId;
    }

    protected function requireUser(string $userId): void
    {
        if (! isset($this->state->users[$userId])) {
            throw InvalidUserException::notFound($userId);
        }
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    protected function set(string $userId, array $changes): bool
    {
        $this->requireUser($userId);
        $this->state->users[$userId] = array_merge($this->state->users[$userId], $changes);

        return true;
    }

    protected function findBy(string $field, string $value): ?string
    {
        foreach ($this->state->users as $userId => $user) {
            if (strcasecmp((string) $user[$field], $value) === 0) {
                return $userId;
            }
        }

        return null;
    }

    protected function ensureEmailIsFree(string $email, ?string $exceptUserId = null): void
    {
        $existing = $this->findBy('email', $email);

        if ($existing !== null && $existing !== $exceptUserId) {
            throw PropelAuthException::forStatus(400, 'PropelAuth API error: 400 (fake)', null, ['email' => ['Email already exists']]);
        }
    }
}
