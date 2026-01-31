<?php

namespace LittleGreenMan\Earhart\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \LittleGreenMan\Earhart\PropelAuth\UserData getUser(string $userId, bool $fresh = false)
 * @method static \LittleGreenMan\Earhart\PropelAuth\UserData getUserByEmail(string $email, bool $includeOrgs = true)
 * @method static \LittleGreenMan\Earhart\PropelAuth\UserData getUserByUsername(string $username, bool $includeOrgs = true)
 * @method static \LittleGreenMan\Earhart\PropelAuth\PaginatedResult queryUsers(?string $emailOrUsername = null, ?string $orderBy = 'CREATED_AT_DESC', int $pageNumber = 0, int $pageSize = 10)
 * @method static string createUser(string $email, ?string $password = null, ?string $firstName = null, ?string $lastName = null, ?string $username = null, ?array $properties = null, bool $sendConfirmationEmail = false)
 * @method static bool updateUser(string $userId, ?string $firstName = null, ?string $lastName = null, ?string $username = null, ?string $pictureUrl = null, ?array $properties = null, ?bool $updatePasswordRequired = null, ?string $legacyUserId = null)
 * @method static bool updateUserEmail(string $userId, string $newEmail, bool $requireConfirmation = true)
 * @method static bool updateUserPassword(string $userId, string $password, bool $askForUpdateOnLogin = false)
 * @method static bool clearUserPassword(string $userId)
 * @method static string createMagicLink(string $email, ?string $redirectUrl = null, ?int $expiresInHours = 24, bool $createIfNotExists = false)
 * @method static string createAccessToken(string $userId, int $durationInMinutes = 1440, ?string $activeOrgId = null)
 * @method static bool disableUser(string $userId)
 * @method static bool enableUser(string $userId)
 * @method static bool deleteUser(string $userId)
 * @method static bool disable2FA(string $userId)
 * @method static bool resendEmailConfirmation(string $userId)
 * @method static bool logoutAllSessions(string $userId)
 * @method static array getUserSignupParams(string $userId)
 * @method static string migrateUserFromExternal(string $email, bool $emailConfirmed = false, ?string $existingUserId = null, ?string $existingPasswordHash = null, ?string $existingMfaSecret = null, ?string $firstName = null, ?string $lastName = null, ?string $username = null, ?array $properties = null)
 * @method static bool migrateUserPassword(string $userId, string $passwordHash)
 * @method static \LittleGreenMan\Earhart\PropelAuth\OrganisationData getOrganisation(string $id)
 * @method static \LittleGreenMan\Earhart\PropelAuth\OrganisationsData getOrganisations(int $pageSize = 1000)
 * @method static array<\LittleGreenMan\Earhart\PropelAuth\UserData> getUsersInOrganisation(string $organisationId)
 * @method static void invalidateUserCache(string $userId)
 * @method static void invalidateOrgCache(string $orgId)
 * @method static void flushCache()
 * @method static bool isCacheEnabled()
 * @method static \LittleGreenMan\Earhart\Services\UserService users()
 * @method static \LittleGreenMan\Earhart\Services\OrganisationService organisations()
 * @method static \LittleGreenMan\Earhart\Services\CacheService cache()
 */
class PropelAuth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'earhart';
    }
}
