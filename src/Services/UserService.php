<?php

namespace LittleGreenMan\Earhart\Services;

use LittleGreenMan\Earhart\Exceptions\InvalidUserException;
use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;
use LittleGreenMan\Earhart\PropelAuth\UserData;

class UserService extends BaseApiService
{
    /**
     * Fetch user by ID with optional caching.
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
     */
    public function validateToken(string $token): UserData
    {
        $response = $this->makeRequest('GET', '/api/backend/v1/user/me', [
            'token' => $token,
        ]);

        if (($response['status'] ?? 200) === 404) {
            throw InvalidUserException::notFound('current');
        }

        return UserData::fromArray($response);
    }

    /**
     * Fetch user by email address.
     */
    public function getUserByEmail(string $email, bool $includeOrgs = true): UserData
    {
        $response = $this->makeRequest('GET', '/api/backend/v1/user/email', [
            'email' => $email,
            'includeOrgs' => $includeOrgs,
        ]);

        if (($response['status'] ?? 200) === 404) {
            throw InvalidUserException::byEmail($email);
        }

        return UserData::fromArray($response);
    }

    /**
     * Fetch user by username.
     */
    public function getUserByUsername(string $username, bool $includeOrgs = true): UserData
    {
        $response = $this->makeRequest('GET', '/api/backend/v1/user/username', [
            'username' => $username,
            'includeOrgs' => $includeOrgs,
        ]);

        if (($response['status'] ?? 200) === 404) {
            throw InvalidUserException::byUsername($username);
        }

        return UserData::fromArray($response);
    }

    /**
     * Query users with pagination and filtering.
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

        $this->makeRequest('PUT', "/api/backend/v1/user/{$userId}", $payload);
        $this->cache->invalidateUser($userId);

        return true;
    }

    /**
     * Update user email address.
     */
    public function updateUserEmail(string $userId, string $newEmail, bool $requireConfirmation = true): bool
    {
        $this->makeRequest('PUT', "/api/backend/v1/user/{$userId}/email", [
            'newEmail' => $newEmail,
            'requireEmailConfirmation' => $requireConfirmation,
        ]);

        $this->cache->invalidateUser($userId);

        return true;
    }

    /**
     * Update user password.
     */
    public function updateUserPassword(string $userId, string $password, bool $askForUpdateOnLogin = false): bool
    {
        $this->makeRequest('PUT', "/api/backend/v1/user/{$userId}/password", [
            'password' => $password,
            'askUserToUpdatePasswordOnLogin' => $askForUpdateOnLogin,
        ]);

        $this->cache->invalidateUser($userId);

        return true;
    }

    /**
     * Clear user password.
     */
    public function clearUserPassword(string $userId): bool
    {
        $this->makeRequest('PUT', "/api/backend/v1/user/{$userId}/clear_password");
        $this->cache->invalidateUser($userId);

        return true;
    }

    /**
     * Create a magic link for passwordless login.
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

        $response = $this->makeRequest('POST', '/api/backend/v1/access_token', $payload);

        return $response['accessToken'];
    }

    /**
     * Disable a user (block from login).
     */
    public function disableUser(string $userId): bool
    {
        $this->makeRequest('POST', "/api/backend/v1/user/{$userId}/disable");
        $this->cache->invalidateUser($userId);

        return true;
    }

    /**
     * Enable a user (unblock).
     */
    public function enableUser(string $userId): bool
    {
        $this->makeRequest('POST', "/api/backend/v1/user/{$userId}/enable");
        $this->cache->invalidateUser($userId);

        return true;
    }

    /**
     * Delete a user.
     */
    public function deleteUser(string $userId): bool
    {
        $this->makeRequest('DELETE', "/api/backend/v1/user/{$userId}");
        $this->cache->invalidateUser($userId);

        return true;
    }

    /**
     * Disable 2FA for a user.
     */
    public function disable2FA(string $userId): bool
    {
        $this->makeRequest('POST', "/api/backend/v1/user/{$userId}/disable_2fa");
        $this->cache->invalidateUser($userId);

        return true;
    }

    /**
     * Resend email confirmation to a user.
     */
    public function resendEmailConfirmation(string $userId): bool
    {
        $this->makeRequest('POST', '/api/backend/v1/resend_email_confirmation', [
            'userId' => $userId,
        ]);

        return true;
    }

    /**
     * Logout user from all sessions.
     */
    public function logoutAllSessions(string $userId): bool
    {
        $this->makeRequest('POST', "/api/backend/v1/user/{$userId}/logout_all_sessions");
        $this->cache->invalidateUser($userId);

        return true;
    }

    /**
     * Fetch user signup query parameters.
     */
    public function getUserSignupParams(string $userId): array
    {
        $response = $this->makeRequest('GET', "/api/backend/v1/user/{$userId}/signup_query_parameters");

        return $response['userSignupQueryParameters'] ?? [];
    }

    /**
     * Migrate user from external source.
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
     */
    public function migrateUserPassword(string $userId, string $passwordHash): bool
    {
        $this->makeRequest('POST', '/api/backend/v1/migrate_user/password', [
            'userId' => $userId,
            'passwordHash' => $passwordHash,
        ]);

        return true;
    }

    // Protected helper methods

    /**
     * Fetch user from API (bypasses cache).
     */
    protected function fetchUserFromAPI(string $userId): UserData
    {
        $response = $this->makeRequest('GET', "/api/backend/v1/user/{$userId}");

        if (($response['status'] ?? 200) === 404) {
            throw InvalidUserException::notFound($userId);
        }

        return UserData::fromArray($response);
    }
}
