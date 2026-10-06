<?php

namespace LittleGreenMan\Earhart;

use LittleGreenMan\Earhart\Exceptions\InvalidOrgException;
use LittleGreenMan\Earhart\Exceptions\InvalidTokenException;
use LittleGreenMan\Earhart\Exceptions\InvalidUserException;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\PropelAuth\AccessToken;
use LittleGreenMan\Earhart\PropelAuth\OrganisationData;
use LittleGreenMan\Earhart\PropelAuth\OrganisationsData;
use LittleGreenMan\Earhart\PropelAuth\PaginatedResult;
use LittleGreenMan\Earhart\PropelAuth\UserData;
use LittleGreenMan\Earhart\Services\ApiKeyService;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Services\InsightsService;
use LittleGreenMan\Earhart\Services\MfaService;
use LittleGreenMan\Earhart\Services\OrganisationService;
use LittleGreenMan\Earhart\Services\UserService;
use LittleGreenMan\Earhart\Testing\EarhartFake;

class Earhart
{
    protected UserService $userService;

    protected OrganisationService $organisationService;

    protected MfaService $mfaService;

    protected ApiKeyService $apiKeyService;

    protected InsightsService $insightsService;

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
        $this->mfaService = new MfaService($apiKey, $authUrl, $this->cacheService);
        $this->apiKeyService = new ApiKeyService($apiKey, $authUrl, $this->cacheService);
        $this->insightsService = new InsightsService($apiKey, $authUrl, $this->cacheService);
    }

    /**
     * Replace Earhart in the container with an in-memory fake for testing.
     *
     * Covers the facade, injected Earhart, UserService and OrganisationService.
     */
    public static function fake(): EarhartFake
    {
        return (new EarhartFake)->swap();
    }

    // ============================================================
    // User Management Methods (New in v1.4.0)
    // ============================================================

    /**
     * Fetch user by ID.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getUser(string $userId, bool $fresh = false, bool $includeOrgs = true): UserData
    {
        return $this->userService->getUser($userId, $fresh, $includeOrgs);
    }

    /**
     * Verify an access token locally, then fetch the current user.
     *
     * @throws InvalidTokenException If the token is invalid or expired
     * @throws PropelAuthException On any other API failure
     */
    public function validateToken(string $token, bool $fresh = false): UserData
    {
        return $this->userService->validateToken($token, $fresh);
    }

    /**
     * Verify an access token locally and return its claims, with no API call per token.
     *
     * @throws InvalidTokenException If the token is invalid or expired
     */
    public function verifyAccessToken(string $token): AccessToken
    {
        return $this->userService->verifyAccessToken($token);
    }

    /**
     * Fetch user by email address.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getUserByEmail(string $email, bool $includeOrgs = true, ?string $isolatedOrgId = null): UserData
    {
        return $this->userService->getUserByEmail($email, $includeOrgs, $isolatedOrgId);
    }

    /**
     * Fetch user by username.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getUserByUsername(string $username, bool $includeOrgs = true, ?string $isolatedOrgId = null): UserData
    {
        return $this->userService->getUserByUsername($username, $includeOrgs, $isolatedOrgId);
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
        ?string $legacyUserId = null,
        bool $includeOrgs = false,
        ?string $isolatedOrgId = null,
    ): PaginatedResult {
        return $this->userService->queryUsers($emailOrUsername, $orderBy, $pageNumber, $pageSize, $legacyUserId, $includeOrgs, $isolatedOrgId);
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
        return $this->userService->createUser(
            $email,
            $password,
            $firstName,
            $lastName,
            $username,
            $properties,
            $sendConfirmationEmail,
            $emailConfirmed,
            $ignoreDomainRestrictions,
            $askUserToUpdatePasswordOnLogin,
        );
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
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function updateUserEmail(string $userId, string $newEmail, bool $requireConfirmation = true): bool
    {
        return $this->userService->updateUserEmail($userId, $newEmail, $requireConfirmation);
    }

    /**
     * Update user password.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function updateUserPassword(string $userId, string $password, bool $askForUpdateOnLogin = false): bool
    {
        return $this->userService->updateUserPassword($userId, $password, $askForUpdateOnLogin);
    }

    /**
     * Clear user password.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function clearUserPassword(string $userId): bool
    {
        return $this->userService->clearUserPassword($userId);
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
        return $this->userService->createMagicLink(
            $email,
            $redirectUrl,
            $expiresInHours,
            $createIfNotExists,
            $expireAfterFirstUse,
            $requiresInterstitial,
            $userSignupQueryParameters,
        );
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
        return $this->userService->createAccessToken($userId, $durationInMinutes, $activeOrgId);
    }

    /**
     * Disable a user (block from login).
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function disableUser(string $userId): bool
    {
        return $this->userService->disableUser($userId);
    }

    /**
     * Enable a user (unblock).
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function enableUser(string $userId): bool
    {
        return $this->userService->enableUser($userId);
    }

    /**
     * Delete a user.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function deleteUser(string $userId): bool
    {
        return $this->userService->deleteUser($userId);
    }

    /**
     * Disable 2FA for a user.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function disable2FA(string $userId): bool
    {
        return $this->userService->disable2FA($userId);
    }

    /**
     * Resend email confirmation to a user.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function resendEmailConfirmation(string $userId): bool
    {
        return $this->userService->resendEmailConfirmation($userId);
    }

    /**
     * Logout user from all sessions.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function logoutAllSessions(string $userId): bool
    {
        return $this->userService->logoutAllSessions($userId);
    }

    /**
     * Fetch user signup query parameters.
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getUserSignupParams(string $userId): array
    {
        return $this->userService->getUserSignupParams($userId);
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
            $updatePasswordRequired,
            $enabled,
            $pictureUrl,
        );
    }

    /**
     * Migrate user password from external source.
     *
     * @param  string  $userId  The user ID
     * @param  string  $passwordHash  The password hash (must be pre-hashed using bcrypt, scrypt, or argon2)
     *
     * @throws InvalidUserException If the user does not exist
     * @throws PropelAuthException On any other API failure
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
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getOrganisation(string $id): OrganisationData
    {
        return $this->organisations()->getOrganisation($id);
    }

    /**
     * Fetch all organisations with pagination.
     *
     * @throws PropelAuthException On any API failure
     */
    public function getOrganisations(int $pageSize = 1000)
    {
        $result = $this->organisations()->queryOrganisations(pageSize: $pageSize);

        // Items are already OrganisationData objects from queryOrganisations()
        // Convert PaginatedResult back to OrganisationsData for backward compatibility
        return new OrganisationsData(
            orgs: collect($result->items),
            total_orgs: $result->totalItems,
            current_page: $result->currentPage,
            page_size: $result->pageSize,
            has_more_results: $result->hasMoreResults,
        );
    }

    /**
     * Fetch users in organisation.
     *
     * @return array<UserData>
     *
     * @throws InvalidOrgException If the organisation does not exist
     * @throws PropelAuthException On any other API failure
     */
    public function getUsersInOrganisation(string $organisationId): array
    {
        $result = $this->organisations()->getOrganisationUsers($organisationId, pageSize: 100);

        // Items are already UserData objects from getOrganisationUsers()
        return $result->items;
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
     * Get the step-up MFA service instance.
     */
    public function mfa(): MfaService
    {
        return $this->mfaService;
    }

    /**
     * Get the end-user API key service instance.
     */
    public function apiKeys(): ApiKeyService
    {
        return $this->apiKeyService;
    }

    /**
     * Get the insights (reports and chart metrics) service instance.
     */
    public function insights(): InsightsService
    {
        return $this->insightsService;
    }

    /**
     * Get the cache service instance.
     */
    public function cache(): CacheService
    {
        return $this->cacheService;
    }
}
