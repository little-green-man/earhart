<?php

namespace LittleGreenMan\Earhart\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use LittleGreenMan\Earhart\Exceptions\InvalidTokenException;
use LittleGreenMan\Earhart\Exceptions\InvalidUserException;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\PropelAuth\AccessToken;
use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;
use LittleGreenMan\Earhart\PropelAuth\UserData;

class UserService extends BaseApiService
{
    /**
     * Fetch user by ID with optional caching.
     *
     * Memberships are included unless $includeOrgs is false. Only the
     * with-memberships form is cached.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getUser(string $userId, bool $fresh = false, bool $includeOrgs = true): UserData
    {
        if (! $fresh && $includeOrgs && $this->cache->isEnabled()) {
            return $this->cache->get("user.{$userId}", fn () => $this->fetchUserFromAPI($userId, true));
        }

        return $this->fetchUserFromAPI($userId, $includeOrgs);
    }

    /**
     * Verify an access token and return the full user.
     *
     * The token is verified locally (see verifyAccessToken()), then the user
     * is fetched with getUser() so the result is current: a user disabled
     * since the token was issued comes back with `enabled` false.
     *
     * @throws InvalidTokenException If the token is invalid or expired
     * @throws InvalidUserException If the user no longer exists
     * @throws PropelAuthException On any other API failure
     */
    public function validateToken(string $token): UserData
    {
        $accessToken = $this->verifyAccessToken($token);

        try {
            return $this->getUser($accessToken->userId);
        } catch (InvalidUserException $e) {
            throw InvalidTokenException::because('the user no longer exists', $e);
        }
    }

    /**
     * Verify an access token locally and return its claims, with no API call per token.
     *
     * Checks the RS256 signature against your environment's verifier key,
     * plus the expiry, issue time and issuer, allowing 60 seconds of clock
     * skew. The key is fetched once from PropelAuth and cached, or set with
     * `earhart.token_verification.verifier_key`.
     *
     * @throws InvalidTokenException If the token is invalid or expired
     * @throws PropelAuthException If the verifier key can't be fetched
     */
    public function verifyAccessToken(string $token): AccessToken
    {
        $token = preg_replace('/^Bearer\s+/i', '', trim($token));

        $previousLeeway = JWT::$leeway;
        JWT::$leeway = 60;

        try {
            $claims = (array) json_decode(
                (string) json_encode(JWT::decode($token, new Key($this->verifierKey(), 'RS256'))),
                true,
            );
        } catch (\Throwable $e) {
            // php-jwt throws TypeError, not an Exception, for some malformed tokens
            throw InvalidTokenException::because($e->getMessage(), $e instanceof \Exception ? $e : null);
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        foreach (['exp', 'iat', 'iss', 'user_id'] as $claim) {
            if (! isset($claims[$claim])) {
                throw InvalidTokenException::because("missing the {$claim} claim");
            }
        }

        if (rtrim($claims['iss'], '/') !== $this->issuer()) {
            throw InvalidTokenException::because('issued by a different PropelAuth environment');
        }

        return AccessToken::fromClaims($claims);
    }

    /**
     * Forget the cached verifier key, e.g. after rotating it in PropelAuth.
     */
    public function forgetVerifierKey(): void
    {
        Cache::forget($this->verifierKeyCacheKey());
    }

    /**
     * Fetch user by email address.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getUserByEmail(string $email, bool $includeOrgs = true, ?string $isolatedOrgId = null): UserData
    {
        $response = $this->makeRequest('GET', '/api/backend/v1/user/email', array_filter([
            'email' => $email,
            'includeOrgs' => $includeOrgs,
            'isolatedOrgId' => $isolatedOrgId,
        ], fn ($v) => $v !== null), fn () => InvalidUserException::byEmail($email));

        return UserData::fromArray($response);
    }

    /**
     * Fetch user by username.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getUserByUsername(string $username, bool $includeOrgs = true, ?string $isolatedOrgId = null): UserData
    {
        $response = $this->makeRequest('GET', '/api/backend/v1/user/username', array_filter([
            'username' => $username,
            'includeOrgs' => $includeOrgs,
            'isolatedOrgId' => $isolatedOrgId,
        ], fn ($v) => $v !== null), fn () => InvalidUserException::byUsername($username));

        return UserData::fromArray($response);
    }

    /**
     * Query users with pagination and filtering.
     *
     * @param  ?string  $orderBy  CREATED_AT_ASC, CREATED_AT_DESC, LAST_ACTIVE_AT_ASC, LAST_ACTIVE_AT_DESC, EMAIL or USERNAME
     *
     * @throws PropelAuthException On any API failure
     */
    public function queryUsers(
        ?string $emailOrUsername = null,
        ?string $orderBy = 'CREATED_AT_DESC',
        int $pageNumber = 0,
        int $pageSize = 10,
        ?string $legacyUserId = null,
        bool $includeOrgs = false,
        ?string $isolatedOrgId = null,
    ): PaginatedResult {
        $params = array_filter(
            [
                'emailOrUsername' => $emailOrUsername,
                'orderBy' => $orderBy,
                'pageNumber' => $pageNumber,
                'pageSize' => $pageSize,
                'legacyUserId' => $legacyUserId,
                'includeOrgs' => $includeOrgs,
                'isolatedOrgId' => $isolatedOrgId,
            ],
            fn ($v) => $v !== null,
        );

        $response = $this->makeRequest('GET', '/api/backend/v1/user/query', $params);

        // Convert arrays to UserData objects for consistency
        $users = array_map(
            fn ($user) => UserData::fromArray($user),
            $response['users'] ?? []
        );
        $response['items'] = $users;

        return PaginatedResult::from($response, fn (int $nextPage) => $this->queryUsers(
            $emailOrUsername,
            $orderBy,
            $nextPage,
            $pageSize,
            $legacyUserId,
            $includeOrgs,
            $isolatedOrgId,
        ));
    }

    /**
     * Create a new user.
     *
     * @throws PropelAuthException On any API failure
     */
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
        $payload = array_filter(
            [
                'email' => $email,
                'password' => $password,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'username' => $username,
                'properties' => $properties,
                'sendEmailToConfirmEmailAddress' => $sendConfirmationEmail,
                'emailConfirmed' => $emailConfirmed,
                'ignoreDomainRestrictions' => $ignoreDomainRestrictions,
                'askUserToUpdatePasswordOnLogin' => $askUserToUpdatePasswordOnLogin,
            ],
            fn ($v) => $v !== null,
        );

        $response = $this->makeRequest('POST', '/api/backend/v1/user/', $payload);

        return $response['userId'];
    }

    /**
     * Update user metadata.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
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
        $payload = array_filter(
            [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'username' => $username,
                'pictureUrl' => $pictureUrl,
                'properties' => $properties,
                'updatePasswordRequired' => $updatePasswordRequired,
                'legacyUserId' => $legacyUserId,
            ],
            fn ($v) => $v !== null,
        );

        $this->makeRequest('PUT', "/api/backend/v1/user/{$userId}", $payload, fn () => InvalidUserException::notFound($userId));
        $this->cache->invalidateUser($userId);

        return true;
    }

    /**
     * Update user email address.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function updateUserEmail(string $userId, string $newEmail, bool $requireConfirmation = true): bool
    {
        $this->makeRequest('PUT', "/api/backend/v1/user/{$userId}/email", [
            'newEmail' => $newEmail,
            'requireEmailConfirmation' => $requireConfirmation,
        ], fn () => InvalidUserException::notFound($userId));

        $this->cache->invalidateUser($userId);

        return true;
    }

    /**
     * Update user password.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function updateUserPassword(string $userId, string $password, bool $askForUpdateOnLogin = false): bool
    {
        $this->makeRequest('PUT', "/api/backend/v1/user/{$userId}/password", [
            'password' => $password,
            'askUserToUpdatePasswordOnLogin' => $askForUpdateOnLogin,
        ], fn () => InvalidUserException::notFound($userId));

        $this->cache->invalidateUser($userId);

        return true;
    }

    /**
     * Clear user password.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function clearUserPassword(string $userId): bool
    {
        $this->makeRequest('PUT', "/api/backend/v1/user/{$userId}/clear_password", notFound: fn () => InvalidUserException::notFound($userId));
        $this->cache->invalidateUser($userId);

        return true;
    }

    /**
     * Create a magic link for passwordless login.
     *
     * @throws PropelAuthException On any API failure
     */
    public function createMagicLink(
        string $email,
        ?string $redirectUrl = null,
        ?int $expiresInHours = 24,
        bool $createIfNotExists = false,
        ?bool $expireAfterFirstUse = null,
        ?bool $requiresInterstitial = null,
        ?array $userSignupQueryParameters = null,
    ): string {
        $payload = array_filter(
            [
                'email' => $email,
                'redirectToUrl' => $redirectUrl,
                'expiresInHours' => $expiresInHours,
                'createNewUserIfOneDoesntExist' => $createIfNotExists,
                'expireAfterFirstUse' => $expireAfterFirstUse,
                'requiresInterstitial' => $requiresInterstitial,
                'userSignupQueryParameters' => $userSignupQueryParameters,
            ],
            fn ($v) => $v !== null,
        );

        $response = $this->makeRequest('POST', '/api/backend/v1/magic_link', $payload);

        return $response['url'];
    }

    /**
     * Create an access token for a user.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function createAccessToken(
        string $userId,
        int $durationInMinutes = 1440,
        ?string $activeOrgId = null,
    ): string {
        $payload = array_filter(
            [
                'userId' => $userId,
                'durationInMinutes' => $durationInMinutes,
                'activeOrgId' => $activeOrgId,
            ],
            fn ($v) => $v !== null,
        );

        $response = $this->makeRequest('POST', '/api/backend/v1/access_token', $payload, fn () => InvalidUserException::notFound($userId));

        return $response['accessToken'];
    }

    /**
     * Disable a user (block from login).
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function disableUser(string $userId): bool
    {
        $this->makeRequest('POST', "/api/backend/v1/user/{$userId}/disable", notFound: fn () => InvalidUserException::notFound($userId));
        $this->cache->invalidateUser($userId);

        return true;
    }

    /**
     * Enable a user (unblock).
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function enableUser(string $userId): bool
    {
        $this->makeRequest('POST', "/api/backend/v1/user/{$userId}/enable", notFound: fn () => InvalidUserException::notFound($userId));
        $this->cache->invalidateUser($userId);

        return true;
    }

    /**
     * Delete a user.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function deleteUser(string $userId): bool
    {
        $this->makeRequest('DELETE', "/api/backend/v1/user/{$userId}", notFound: fn () => InvalidUserException::notFound($userId));
        $this->cache->invalidateUser($userId);

        return true;
    }

    /**
     * Disable 2FA for a user.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function disable2FA(string $userId): bool
    {
        $this->makeRequest('POST', "/api/backend/v1/user/{$userId}/disable_2fa", notFound: fn () => InvalidUserException::notFound($userId));
        $this->cache->invalidateUser($userId);

        return true;
    }

    /**
     * Resend email confirmation to a user.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function resendEmailConfirmation(string $userId): bool
    {
        $this->makeRequest('POST', '/api/backend/v1/resend_email_confirmation', [
            'userId' => $userId,
        ], fn () => InvalidUserException::notFound($userId));

        return true;
    }

    /**
     * Logout user from all sessions.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function logoutAllSessions(string $userId): bool
    {
        $this->makeRequest('POST', "/api/backend/v1/user/{$userId}/logout_all_sessions", notFound: fn () => InvalidUserException::notFound($userId));
        $this->cache->invalidateUser($userId);

        return true;
    }

    /**
     * Fetch user signup query parameters.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getUserSignupParams(string $userId): array
    {
        $response = $this->makeRequest('GET', "/api/backend/v1/user/{$userId}/signup_query_parameters", notFound: fn () => InvalidUserException::notFound($userId));

        return $response['userSignupQueryParameters'] ?? [];
    }

    /**
     * Migrate user from external source.
     *
     * @throws PropelAuthException On any API failure
     */
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
        $payload = array_filter(
            [
                'email' => $email,
                'emailConfirmed' => $emailConfirmed,
                'updatePasswordRequired' => $updatePasswordRequired,
                'enabled' => $enabled,
                'pictureUrl' => $pictureUrl,
                'existingUserId' => $existingUserId,
                'existingPasswordHash' => $existingPasswordHash,
                'existingMfaBase32EncodedSecret' => $existingMfaSecret,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'username' => $username,
                'properties' => $properties,
            ],
            fn ($v) => $v !== null,
        );

        $response = $this->makeRequest('POST', '/api/backend/v1/migrate_user/', $payload);

        return $response['userId'];
    }

    /**
     * Migrate user password from external source.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function migrateUserPassword(string $userId, string $passwordHash): bool
    {
        $this->makeRequest('POST', '/api/backend/v1/migrate_user/password', [
            'userId' => $userId,
            'passwordHash' => $passwordHash,
        ], fn () => InvalidUserException::notFound($userId));

        return true;
    }

    // Protected helper methods

    /**
     * Fetch user from API (bypasses cache).
     */
    protected function fetchUserFromAPI(string $userId, bool $includeOrgs = true): UserData
    {
        $response = $this->makeRequest('GET', "/api/backend/v1/user/{$userId}", [
            'includeOrgs' => $includeOrgs,
        ], fn () => InvalidUserException::notFound($userId));

        return UserData::fromArray($response);
    }

    /**
     * The PEM public key that signs this environment's access tokens.
     */
    protected function verifierKey(): string
    {
        $configured = config('earhart.token_verification.verifier_key');

        if (is_string($configured) && $configured !== '') {
            return str_replace('\\n', "\n", $configured);
        }

        return Cache::remember(
            $this->verifierKeyCacheKey(),
            now()->addMinutes((int) config('earhart.token_verification.cache_minutes', 1440)),
            fn () => $this->makeRequest('GET', '/api/v1/token_verification_metadata')['verifierKeyPem']
                ?? throw new PropelAuthException('PropelAuth token verification metadata has no verifier_key_pem'),
        );
    }

    protected function verifierKeyCacheKey(): string
    {
        return 'propelauth.verifier_key.'.md5($this->authUrl);
    }

    /**
     * The expected `iss` claim: the Auth URL, without a trailing slash.
     */
    protected function issuer(): string
    {
        return rtrim((string) (config('earhart.token_verification.issuer') ?: $this->authUrl), '/');
    }
}
