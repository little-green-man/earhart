<?php

namespace LittleGreenMan\Earhart;

use Illuminate\Support\Facades\Http;
use LittleGreenMan\Earhart\PropelAuth\OrganisationData;
use LittleGreenMan\Earhart\PropelAuth\OrganisationsData;
use LittleGreenMan\Earhart\PropelAuth\UserData;
use LittleGreenMan\Earhart\PropelAuth\UsersData;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Services\OrganisationService;
use LittleGreenMan\Earhart\Services\UserService;

class Earhart
{
    protected UserService $userService;

    protected OrganisationService $organisationService;

    protected CacheService $cacheService;

    public function __construct(
        protected string $clientId,
        protected string $clientSecret,
        protected string $callbackUrl,
        protected string $authUrl,
        protected string $svixSecret,
        protected string $apiKey,
        bool $enableCache = false,
        int $cacheTtlMinutes = 60,
    ) {
        $this->cacheService = new CacheService($enableCache, $cacheTtlMinutes);
        $this->userService = new UserService($apiKey, $authUrl, $this->cacheService);
        $this->organisationService = new OrganisationService($apiKey, $authUrl, $this->cacheService);
    }

    // ============================================================
    // User Management Methods (New in v1.4.0)
    // ============================================================

    /**
     * Fetch user by ID.
     */
    public function getUser(string $userId, bool $fresh = false): UserData
    {
        return $this->userService->getUser($userId, $fresh);
    }

    /**
     * Fetch user by email address.
     */
    public function getUserByEmail(string $email, bool $includeOrgs = true): UserData
    {
        return $this->userService->getUserByEmail($email, $includeOrgs);
    }

    /**
     * Fetch user by username.
     */
    public function getUserByUsername(string $username, bool $includeOrgs = true): UserData
    {
        return $this->userService->getUserByUsername($username, $includeOrgs);
    }

    /**
     * Query users with pagination and filtering.
     */
    public function queryUsers(
        ?string $emailOrUsername = null,
        ?string $orderBy = 'CREATED_AT_DESC',
        int $pageNumber = 0,
        int $pageSize = 10,
    ) {
        return $this->userService->queryUsers($emailOrUsername, $orderBy, $pageNumber, $pageSize);
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
        return $this->userService->createUser(
            $email,
            $password,
            $firstName,
            $lastName,
            $username,
            $properties,
            $sendConfirmationEmail,
        );
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
        return $this->userService->updateUser(
            $userId,
            $firstName,
            $lastName,
            $username,
            $pictureUrl,
            $properties,
            $updatePasswordRequired,
            $legacyUserId,
        );
    }

    /**
     * Update user email address.
     */
    public function updateUserEmail(string $userId, string $newEmail, bool $requireConfirmation = true): bool
    {
        return $this->userService->updateUserEmail($userId, $newEmail, $requireConfirmation);
    }

    /**
     * Update user password.
     */
    public function updateUserPassword(string $userId, string $password, bool $askForUpdateOnLogin = false): bool
    {
        return $this->userService->updateUserPassword($userId, $password, $askForUpdateOnLogin);
    }

    /**
     * Clear user password.
     */
    public function clearUserPassword(string $userId): bool
    {
        return $this->userService->clearUserPassword($userId);
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
        return $this->userService->createMagicLink($email, $redirectUrl, $expiresInHours, $createIfNotExists);
    }

    /**
     * Create an access token for a user.
     */
    public function createAccessToken(
        string $userId,
        int $durationInMinutes = 1440,
        ?string $activeOrgId = null,
    ): string {
        return $this->userService->createAccessToken($userId, $durationInMinutes, $activeOrgId);
    }

    /**
     * Disable a user (block from login).
     */
    public function disableUser(string $userId): bool
    {
        return $this->userService->disableUser($userId);
    }

    /**
     * Enable a user (unblock).
     */
    public function enableUser(string $userId): bool
    {
        return $this->userService->enableUser($userId);
    }

    /**
     * Delete a user.
     */
    public function deleteUser(string $userId): bool
    {
        return $this->userService->deleteUser($userId);
    }

    /**
     * Disable 2FA for a user.
     */
    public function disable2FA(string $userId): bool
    {
        return $this->userService->disable2FA($userId);
    }

    /**
     * Resend email confirmation to a user.
     */
    public function resendEmailConfirmation(string $userId): bool
    {
        return $this->userService->resendEmailConfirmation($userId);
    }

    /**
     * Logout user from all sessions.
     */
    public function logoutAllSessions(string $userId): bool
    {
        return $this->userService->logoutAllSessions($userId);
    }

    /**
     * Fetch user signup query parameters.
     */
    public function getUserSignupParams(string $userId): array
    {
        return $this->userService->getUserSignupParams($userId);
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
        return $this->userService->migrateUserFromExternal(
            $email,
            $emailConfirmed,
            $existingUserId,
            $existingPasswordHash,
            $existingMfaSecret,
            $firstName,
            $lastName,
            $username,
            $properties,
        );
    }

    /**
     * Migrate user password from external source.
     */
    public function migrateUserPassword(string $userId, string $passwordHash): bool
    {
        return $this->userService->migrateUserPassword($userId, $passwordHash);
    }

    // ============================================================
    // Organization Management Methods (Enhanced in v1.4.0)
    // ============================================================

    /**
     * Fetch organization by ID.
     */
    public function getOrganisation(string $id): OrganisationData
    {
        return OrganisationData::from(Http::withToken($this->apiKey)->get($this->authUrl
        .'/api/backend/v1/org/'
        .$id)->json());
    }

    /**
     * Fetch all organisations with pagination.
     */
    public function getOrganisations(int $pageSize = 1000)
    {
        return OrganisationsData::from(Http::withToken($this->apiKey)->get(
            $this->authUrl.'/api/backend/v1/org/query',
            [
                'pageSize' => $pageSize,
            ],
        )->json());
    }

    /**
     * Fetch users in organisation.
     */
    public function getUsersInOrganisation(string $organisationId)
    {
        return UsersData::from(Http::withToken($this->apiKey)->get(
            $this->authUrl.'/api/backend/v1/user/org/'.$organisationId,
            [
                'pageSize' => 1000,
                'includeOrgs' => false,
            ],
        )->json());
    }

    // ============================================================
    // Cache Management Methods (New in v1.4.0)
    // ============================================================

    /**
     * Invalidate user cache.
     */
    public function invalidateUserCache(string $userId): void
    {
        $this->cacheService->invalidateUser($userId);
    }

    /**
     * Invalidate organisation cache.
     */
    public function invalidateOrgCache(string $orgId): void
    {
        $this->cacheService->invalidateOrganisation($orgId);
    }

    /**
     * Flush all PropelAuth cache.
     */
    public function flushCache(): void
    {
        $this->cacheService->flush();
    }

    /**
     * Check if caching is enabled.
     */
    public function isCacheEnabled(): bool
    {
        return $this->cacheService->isEnabled();
    }

    // ============================================================
    // Service Accessors (For advanced usage)
    // ============================================================

    /**
     * Get the user service instance.
     */
    public function users(): UserService
    {
        return $this->userService;
    }

    /**
     * Get the organisation service instance.
     */
    public function organisations(): OrganisationService
    {
        return $this->organisationService;
    }

    /**
     * Get the cache service instance.
     */
    public function cache(): CacheService
    {
        return $this->cacheService;
    }
}
