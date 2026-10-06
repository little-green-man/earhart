<?php

namespace LittleGreenMan\Earhart\Services;

use LittleGreenMan\Earhart\Exceptions\InvalidUserException;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;
use LittleGreenMan\Earhart\PropelAuth\UserData;

class UserService extends BaseApiService
{
    /**
     * Fetch user by ID with optional caching.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getUser(string $userId, bool $fresh = false): UserData
    {
        if (! $fresh && $this->cache->isEnabled()) {
            return $this->cache->get("user.{$userId}", fn () => $this->fetchUserFromAPI($userId));
        }

        return $this->fetchUserFromAPI($userId);
    }

    /**
     * Validate a PropelAuth session token and return the authenticated user.
     *
     * This method is typically called by authentication middleware to verify
     * that a session token is valid and retrieve the associated user data.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function validateToken(string $token): UserData
    {
        $response = $this->makeRequest('GET', '/api/backend/v1/user/me', [
            'token' => $token,
        ], fn () => InvalidUserException::notFound('current'));

        return UserData::fromArray($response);
    }

    /**
     * Fetch user by email address.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getUserByEmail(string $email, bool $includeOrgs = true): UserData
    {
        $response = $this->makeRequest('GET', '/api/backend/v1/user/email', [
            'email' => $email,
            'includeOrgs' => $includeOrgs,
        ], fn () => InvalidUserException::byEmail($email));

        return UserData::fromArray($response);
    }

    /**
     * Fetch user by username.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getUserByUsername(string $username, bool $includeOrgs = true): UserData
    {
        $response = $this->makeRequest('GET', '/api/backend/v1/user/username', [
            'username' => $username,
            'includeOrgs' => $includeOrgs,
        ], fn () => InvalidUserException::byUsername($username));

        return UserData::fromArray($response);
    }

    /**
     * Query users with pagination and filtering.
     *
     * @throws PropelAuthException On any API failure
     */
    public function queryUsers(
        ?string $emailOrUsername = null,
        ?string $orderBy = 'CREATED_AT_DESC',
        int $pageNumber = 0,
        int $pageSize = 10,
    ): PaginatedResult {
        $params = array_filter(
            [
                'emailOrUsername' => $emailOrUsername,
                'orderBy' => $orderBy,
                'pageNumber' => $pageNumber,
                'pageSize' => $pageSize,
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
    ): string {
        $payload = array_filter(
            [
                'email' => $email,
                'redirectToUrl' => $redirectUrl,
                'expiresInHours' => $expiresInHours,
                'createNewUserIfOneDoesntExist' => $createIfNotExists,
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
    ): string {
        $payload = array_filter(
            [
                'email' => $email,
                'emailConfirmed' => $emailConfirmed,
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
    protected function fetchUserFromAPI(string $userId): UserData
    {
        $response = $this->makeRequest('GET', "/api/backend/v1/user/{$userId}", notFound: fn () => InvalidUserException::notFound($userId));

        return UserData::fromArray($response);
    }
}
