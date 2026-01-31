# Using the PropelAuth API

This guide provides comprehensive examples for interacting with the PropelAuth API from your Laravel application using Earhart. For installation and initial setup, see the [README](../README.md).

## Table of Contents

- [Getting Started](#getting-started)
- [User Management](#user-management)
  - [Fetching Users](#fetching-users)
  - [Creating Users](#creating-users)
  - [Updating Users](#updating-users)
  - [User Authentication](#user-authentication)
  - [User State Management](#user-state-management)
- [Organization Management](#organization-management)
  - [Fetching Organizations](#fetching-organizations)
  - [Creating & Updating Organizations](#creating--updating-organizations)
  - [Managing Organization Members](#managing-organization-members)
  - [Organization Roles](#organization-roles)
  - [SAML Configuration](#saml-configuration)
- [Pagination & Data Handling](#pagination--data-handling)
- [Caching](#caching)
- [Error Handling](#error-handling)
- [Advanced Usage](#advanced-usage)
- [Missing Features & Limitations](#missing-features--limitations)

## Getting Started

The PropelAuth API is accessible through the `app('earhart')` helper or by injecting the `Earhart` class. All API methods require your PropelAuth API key to be configured in your environment.

```php
use LittleGreenMan\Earhart\Earhart;

// Using the helper
$earhart = app('earhart');

// Or via dependency injection
public function __construct(protected Earhart $earhart) {}
```

## User Management

### Fetching Users

#### Get a Single User by ID

```php
use LittleGreenMan\Earhart\Exceptions\InvalidUserException;

try {
    $user = app('earhart')->getUser('user_id_here');
    
    echo $user->email;              // user@example.com
    echo $user->first_name;         // John
    echo $user->last_name;          // Doe
    echo $user->username;           // johndoe
    echo $user->picture_url;        // https://...
    echo $user->email_confirmed;    // true/false
    echo $user->enabled;            // true/false
    echo $user->locked;             // true/false
    echo $user->has_password;       // true/false
    echo $user->mfa_enabled;        // true/false
    echo $user->created_at;         // Carbon instance
    echo $user->last_active_at;     // Carbon instance
    
    // Access custom properties
    $properties = $user->properties;
    
    // Access user's organizations
    foreach ($user->orgs as $org) {
        echo $org['orgId'];
        echo $org['orgName'];
        echo $org['userAssignedRole'];
    }
} catch (InvalidUserException $e) {
    // User not found
    Log::error('User not found: ' . $e->getMessage());
}
```

#### Fetch User by Email

```php
try {
    $user = app('earhart')->getUserByEmail('user@example.com');
    echo "Found user: {$user->first_name} {$user->last_name}";
} catch (InvalidUserException $e) {
    echo "No user found with that email";
}
```

#### Fetch User by Username

```php
try {
    $user = app('earhart')->getUserByUsername('johndoe', includeOrgs: true);
    echo "User ID: {$user->user_id}";
} catch (InvalidUserException $e) {
    echo "No user found with that username";
}
```

#### Query Users with Filters

```php
// Search users by email or username
$result = app('earhart')->queryUsers(
    emailOrUsername: 'john',
    orderBy: 'CREATED_AT_DESC',
    pageNumber: 0,
    pageSize: 20
);

echo "Total users: {$result->totalItems}";
echo "Current page: {$result->currentPage}";

foreach ($result->items as $userData) {
    $user = \LittleGreenMan\Earhart\PropelAuth\UserData::fromArray($userData);
    echo "{$user->email} - {$user->first_name} {$user->last_name}\n";
}

// Fetch next page if available
if ($result->hasNextPage()) {
    $nextPage = $result->nextPage();
}

// Or get all pages as a collection
$allUsers = $result->allPages();
```

**Available `orderBy` values:**
- `CREATED_AT_ASC`
- `CREATED_AT_DESC`
- `LAST_ACTIVE_AT_ASC`
- `LAST_ACTIVE_AT_DESC`
- `EMAIL`
- `USERNAME`

### Creating Users

#### Create a Basic User

```php
$userId = app('earhart')->createUser(
    email: 'newuser@example.com',
    password: 'SecurePassword123!',
    firstName: 'Jane',
    lastName: 'Smith',
    username: 'janesmith',
    sendConfirmationEmail: true
);

echo "Created user with ID: {$userId}";
```

#### Create User with Custom Properties

```php
$userId = app('earhart')->createUser(
    email: 'newuser@example.com',
    firstName: 'Jane',
    lastName: 'Smith',
    properties: [
        'department' => 'Engineering',
        'role' => 'Senior Developer',
        'startDate' => '2024-01-15',
        'customField' => 'custom value'
    ],
    sendConfirmationEmail: false
);
```

#### Create User Without Password (Passwordless Auth)

```php
// User will use magic links or social login
$userId = app('earhart')->createUser(
    email: 'newuser@example.com',
    firstName: 'Jane',
    lastName: 'Smith'
);
```

### Updating Users

#### Update User Profile

```php
app('earhart')->updateUser(
    userId: 'user_id_here',
    firstName: 'John',
    lastName: 'Updated',
    username: 'john_updated',
    pictureUrl: 'https://example.com/avatar.jpg',
    properties: [
        'department' => 'Product',
        'title' => 'Product Manager'
    ]
);
```

#### Update User Email

```php
// Email update with confirmation required
app('earhart')->updateUserEmail(
    userId: 'user_id_here',
    newEmail: 'newemail@example.com',
    requireConfirmation: true
);

// Email update without confirmation
app('earhart')->updateUserEmail(
    userId: 'user_id_here',
    newEmail: 'newemail@example.com',
    requireConfirmation: false
);
```

#### Update User Password

```php
// Set new password
app('earhart')->updateUserPassword(
    userId: 'user_id_here',
    password: 'NewSecurePassword123!',
    askForUpdateOnLogin: false
);

// Force password update on next login
app('earhart')->updateUserPassword(
    userId: 'user_id_here',
    password: 'TempPassword123!',
    askForUpdateOnLogin: true
);
```

#### Clear User Password

```php
// Remove password (user must use magic links or social login)
app('earhart')->clearUserPassword('user_id_here');
```

### User Authentication

#### Create Magic Link

```php
// Create magic link for passwordless login
$magicLink = app('earhart')->createMagicLink(
    email: 'user@example.com',
    redirectUrl: 'https://yourapp.com/dashboard',
    expiresInHours: 24,
    createIfNotExists: false
);

// Send the magic link to the user
Mail::to('user@example.com')->send(new MagicLinkEmail($magicLink));
```

#### Create Access Token

```php
// Create a 24-hour access token
$accessToken = app('earhart')->createAccessToken(
    userId: 'user_id_here',
    durationInMinutes: 1440,
    activeOrgId: 'org_id_here' // optional
);

// Use the token for API authentication
$response = Http::withToken($accessToken)->get('...');
```

### User State Management

#### Enable/Disable User

```php
// Disable user (blocks login)
app('earhart')->disableUser('user_id_here');

// Enable user (allows login)
app('earhart')->enableUser('user_id_here');
```

#### Delete User

```php
app('earhart')->deleteUser('user_id_here');
```

#### Disable Two-Factor Authentication

```php
// Remove 2FA from a user's account
app('earhart')->disable2FA('user_id_here');
```

#### Resend Email Confirmation

```php
app('earhart')->resendEmailConfirmation('user_id_here');
```

#### Logout All Sessions

```php
// Force logout from all devices
app('earhart')->logoutAllSessions('user_id_here');
```

#### Get User Signup Parameters

```php
// Retrieve the query parameters from when user signed up
$params = app('earhart')->getUserSignupParams('user_id_here');

// Example: ['utm_source' => 'google', 'utm_campaign' => 'summer2024']
```

## Organization Management

### Fetching Organizations

#### Get Single Organization

```php
use LittleGreenMan\Earhart\Exceptions\InvalidOrgException;

try {
    $org = app('earhart')->getOrganisation('org_id_here');
    
    echo $org->orgId;                   // org_123456
    echo $org->displayName;             // Acme Corp
    echo $org->urlSafeOrgSlug;          // acme-corp
    echo $org->isSamlConfigured;        // true/false
    echo $org->canSetupSaml;            // true/false
    echo $org->customRoleMappingName;   // default
    echo $org->createdAt;               // Carbon instance
    
    // Access metadata
    if ($org->metadata) {
        $metadata = $org->metadata;
    }
} catch (InvalidOrgException $e) {
    echo "Organization not found";
}
```

#### Query All Organizations

```php
// Fetch organizations with pagination
$result = app('earhart')->organisations()->queryOrganisations(
    orderBy: 'CREATED_AT_DESC',
    pageNumber: 0,
    pageSize: 100
);

foreach ($result->items as $orgData) {
    $org = \LittleGreenMan\Earhart\PropelAuth\OrganisationData::fromArray($orgData);
    echo "{$org->displayName}\n";
}
```

#### Get Users in Organization

```php
$result = app('earhart')->organisations()->getOrganisationUsers(
    orgId: 'org_id_here',
    pageSize: 100
);

echo "Total users in org: {$result->totalItems}";

foreach ($result->items as $userData) {
    $user = \LittleGreenMan\Earhart\PropelAuth\UserData::fromArray($userData);
    echo "{$user->email} - {$user->first_name} {$user->last_name}\n";
}

// Legacy method (still supported)
$usersData = app('earhart')->getUsersInOrganisation('org_id_here');
```

### Creating & Updating Organizations

#### Create Organization

```php
$orgId = app('earhart')->organisations()->createOrganisation(
    name: 'New Company Inc',
    slug: 'new-company',
    metadata: [
        'industry' => 'Technology',
        'size' => 'Enterprise',
        'country' => 'US'
    ]
);

echo "Created organization with ID: {$orgId}";
```

#### Update Organization

```php
app('earhart')->organisations()->updateOrganisation(
    orgId: 'org_id_here',
    name: 'Updated Company Name',
    metadata: [
        'industry' => 'Software',
        'updated_at' => now()->toIso8601String()
    ]
);
```

#### Delete Organization

```php
try {
    app('earhart')->organisations()->deleteOrganisation('org_id_here');
    echo "Organization deleted successfully";
} catch (InvalidOrgException $e) {
    echo "Failed to delete organization";
}
```

### Managing Organization Members

#### Add User to Organization

```php
// Add existing user to organization
app('earhart')->organisations()->addUserToOrganisation(
    orgId: 'org_id_here',
    userId: 'user_id_here',
    role: 'Member'
);
```

#### Invite User to Organization

```php
// Send invitation email
app('earhart')->organisations()->inviteUserToOrganisation(
    orgId: 'org_id_here',
    email: 'newuser@example.com',
    role: 'Admin'
);
```

#### Remove User from Organization

```php
app('earhart')->organisations()->removeUserFromOrganisation(
    orgId: 'org_id_here',
    userId: 'user_id_here'
);
```

#### Change User Role

```php
app('earhart')->organisations()->changeUserRole(
    orgId: 'org_id_here',
    userId: 'user_id_here',
    role: 'Admin'
);
```

### Organization Roles

#### Get Role Mappings

```php
$roleMappings = app('earhart')->organisations()->getRoleMappings();

foreach ($roleMappings as $mapping) {
    echo "Role mapping ID: {$mapping['customRoleMappingId']}\n";
    echo "Name: {$mapping['name']}\n";
    // Access role definitions
}
```

#### Subscribe Organization to Role Mapping

```php
app('earhart')->organisations()->subscribeOrgToRoleMapping(
    orgId: 'org_id_here',
    mappingId: 'mapping_id_here'
);
```

### Organization Invites

#### Get Pending Invites

```php
// Get all pending invites
$result = app('earhart')->organisations()->getPendingInvites();

foreach ($result->items as $invite) {
    echo "Email: {$invite['email']}\n";
    echo "Org: {$invite['orgName']}\n";
    echo "Role: {$invite['role']}\n";
}

// Get pending invites for specific org
$result = app('earhart')->organisations()->getPendingInvites(orgId: 'org_id_here');
```

#### Revoke Pending Invite

```php
app('earhart')->organisations()->revokePendingInvite(
    orgId: 'org_id_here',
    inviteeEmail: 'user@example.com'
);
```

### SAML Configuration

#### Allow Organization to Setup SAML

```php
app('earhart')->organisations()->allowOrgToSetupSAML('org_id_here');
```

#### Create SAML Connection Link

```php
// Generate a link for org admins to set up SAML
$url = app('earhart')->organisations()->createSAMLConnectionLink('org_id_here');

// Redirect org admin to this URL
return redirect($url);
```

#### Fetch SAML SP Metadata

```php
$metadata = app('earhart')->organisations()->fetchSAMLMetadata('org_id_here');

// Return as XML response
return response($metadata)->header('Content-Type', 'application/xml');
```

#### Set SAML IdP Metadata

```php
$metadataXml = '<?xml version="1.0"?>...'; // IdP metadata XML

app('earhart')->organisations()->setSAMLIdPMetadata(
    orgId: 'org_id_here',
    metadataXml: $metadataXml
);
```

#### Enable SAML Connection

```php
// Take SAML connection live
app('earhart')->organisations()->enableSAMLConnection('org_id_here');
```

#### Delete SAML Connection

```php
app('earhart')->organisations()->deleteSAMLConnection('org_id_here');
```

#### Disallow SAML Setup

```php
app('earhart')->organisations()->disallowOrgToSetupSAML('org_id_here');
```

### Organization Isolation

#### Migrate Organization to Isolated

Isolated organizations are completely separate tenants with their own user base. Users in isolated orgs cannot interact with users in other orgs.

```php
// Convert an organization to isolated mode
app('earhart')->organisations()->migrateOrgToIsolated('org_id_here');

// Note: This is a one-way operation and cannot be reversed
```

**Use Cases for Isolated Organizations:**
- B2B SaaS with complete data isolation requirements
- Enterprise customers requiring dedicated tenancy
- Regulatory compliance scenarios (HIPAA, SOC2, etc.)

## Pagination & Data Handling

### Working with Paginated Results

```php
$result = app('earhart')->queryUsers(pageSize: 50);

// Check pagination status
echo "Page {$result->currentPage} of {$result->lastPage()}";
echo "Showing {$result->count()} of {$result->totalItems} total items";

// Navigation checks
if ($result->isFirstPage()) {
    echo "This is the first page";
}

if ($result->hasNextPage()) {
    $nextPage = $result->nextPage();
}

if ($result->isLastPage()) {
    echo "This is the last page";
}

// Get items as Laravel collection
$collection = $result->collection();
$filtered = $collection->filter(fn($user) => $user['enabled'] === true);

// Get all pages at once (use carefully with large datasets)
$allItems = $result->allPages();
```

### Converting to Collections

```php
$result = app('earhart')->queryUsers();

// As Laravel collection
$users = $result->collection();

// Use collection methods
$activeUsers = $users->filter(fn($u) => $u['enabled'] === true);
$emails = $users->pluck('email');
$grouped = $users->groupBy('created_at');
```

## Caching

Earhart includes built-in caching to reduce API calls and improve performance.

### Cache Configuration

In your `.env`:

```env
PROPELAUTH_CACHE_ENABLED=true
PROPELAUTH_CACHE_TTL=60  # minutes
```

### Using Cache

```php
// Cached request (default)
$user = app('earhart')->getUser('user_id_here');

// Bypass cache and fetch fresh data
$user = app('earhart')->getUser('user_id_here', fresh: true);

// Organizations also support caching
$org = app('earhart')->organisations()->getOrganisation('org_id_here');
$org = app('earhart')->organisations()->getOrganisation('org_id_here', fresh: true);
```

### Manual Cache Management

```php
// Invalidate specific user cache
app('earhart')->invalidateUserCache('user_id_here');

// Invalidate specific org cache
app('earhart')->invalidateOrgCache('org_id_here');

// Flush all PropelAuth cache
app('earhart')->flushCache();

// Check if caching is enabled
if (app('earhart')->isCacheEnabled()) {
    echo "Caching is active";
}
```

### Cache in Event Listeners

```php
namespace App\Listeners;

use LittleGreenMan\Earhart\Events\PropelAuth\UserUpdated;

class InvalidateUserCacheListener
{
    public function handle(UserUpdated $event): void
    {
        // Automatically invalidate cache when user is updated
        app('earhart')->invalidateUserCache($event->user_id);
        
        // Refresh user data
        $user = app('earhart')->getUser($event->user_id, fresh: true);
        
        // Update your local database
        \App\Models\User::where('propel_id', $event->user_id)->update([
            'name' => "{$user->first_name} {$user->last_name}",
            'email' => $user->email,
        ]);
    }
}
```

## Error Handling

### Common Exceptions

```php
use LittleGreenMan\Earhart\Exceptions\InvalidUserException;
use LittleGreenMan\Earhart\Exceptions\InvalidOrgException;
use LittleGreenMan\Earhart\Exceptions\RateLimitException;

// User not found
try {
    $user = app('earhart')->getUser('invalid_id');
} catch (InvalidUserException $e) {
    Log::warning('User not found', ['error' => $e->getMessage()]);
    return response()->json(['error' => 'User not found'], 404);
}

// Organization not found
try {
    $org = app('earhart')->organisations()->getOrganisation('invalid_id');
} catch (InvalidOrgException $e) {
    return response()->json(['error' => 'Organization not found'], 404);
}

// Rate limiting
try {
    $user = app('earhart')->getUser('user_id');
} catch (RateLimitException $e) {
    Log::error('PropelAuth rate limit exceeded');
    return response()->json(['error' => 'Too many requests'], 429);
}
```

### Graceful Error Handling

```php
use Illuminate\Support\Facades\Cache;

public function getUserSafely(string $userId)
{
    try {
        return app('earhart')->getUser($userId);
    } catch (InvalidUserException $e) {
        // User doesn't exist in PropelAuth
        return null;
    } catch (RateLimitException $e) {
        // Rate limited - return cached data if available
        return Cache::get("user.{$userId}.fallback");
    } catch (\Exception $e) {
        // Log unexpected errors
        Log::error('PropelAuth API error', [
            'user_id' => $userId,
            'error' => $e->getMessage()
        ]);
        return null;
    }
}
```

## Advanced Usage

### Using Service Classes Directly

For more control, access the service classes directly:

```php
// User service
$userService = app('earhart')->users();
$user = $userService->getUser('user_id_here');
$result = $userService->queryUsers();

// Organization service
$orgService = app('earhart')->organisations();
$org = $orgService->getOrganisation('org_id_here');
$users = $orgService->getOrganisationUsers('org_id_here');

// Cache service
$cacheService = app('earhart')->cache();
$cacheService->invalidateUser('user_id_here');
$cacheService->flush();
```

### Migrating Users from External Systems

```php
// Migrate user with existing password hash
$userId = app('earhart')->migrateUserFromExternal(
    email: 'user@example.com',
    emailConfirmed: true,
    existingUserId: 'old_system_id_123',
    existingPasswordHash: 'bcrypt_hash_here',
    existingMfaSecret: 'TOTP_SECRET',
    firstName: 'John',
    lastName: 'Doe',
    username: 'johndoe',
    properties: [
        'legacy_id' => 'old_system_id_123',
        'migrated_at' => now()->toIso8601String()
    ]
);

echo "Migrated user with new ID: {$userId}";
```

### Batch Operations

```php
// Process all users in batches
$pageNumber = 0;
$pageSize = 100;

do {
    $result = app('earhart')->queryUsers(
        pageNumber: $pageNumber,
        pageSize: $pageSize
    );
    
    foreach ($result->items as $userData) {
        $user = \LittleGreenMan\Earhart\PropelAuth\UserData::fromArray($userData);
        
        // Process each user
        \App\Models\User::updateOrCreate(
            ['propel_id' => $user->user_id],
            [
                'email' => $user->email,
                'name' => "{$user->first_name} {$user->last_name}",
            ]
        );
    }
    
    $pageNumber++;
} while ($result->hasNextPage());
```

### Handling Organization Webhooks

```php
namespace App\Listeners;

use App\Models\Organisation;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgCreated;

class SyncOrganisationListener
{
    public function handle(OrgCreated $event): void
    {
        // Fetch full org details from API
        $orgData = app('earhart')->organisations()->getOrganisation($event->org_id);
        
        // Create or update local organization
        Organisation::updateOrCreate(
            ['propel_id' => $orgData->orgId],
            [
                'name' => $orgData->displayName,
                'slug' => $orgData->urlSafeOrgSlug,
                'metadata' => $orgData->metadata,
                'created_at' => $orgData->createdAt,
            ]
        );
        
        // Fetch and sync organization members
        $members = app('earhart')->organisations()->getOrganisationUsers($event->org_id);
        
        foreach ($members->items as $userData) {
            // Sync members to your local database
        }
    }
}
```

### Custom Property Management

```php
// Define a helper for managing custom properties
class PropelAuthHelper
{
    public static function setUserProperty(string $userId, string $key, mixed $value): void
    {
        $user = app('earhart')->getUser($userId);
        $properties = $user->properties;
        $properties[$key] = $value;
        
        app('earhart')->updateUser($userId, properties: $properties);
    }
    
    public static function getUserProperty(string $userId, string $key, mixed $default = null): mixed
    {
        $user = app('earhart')->getUser($userId);
        return $user->properties[$key] ?? $default;
    }
}

// Usage
PropelAuthHelper::setUserProperty('user_id', 'subscription_tier', 'premium');
$tier = PropelAuthHelper::getUserProperty('user_id', 'subscription_tier', 'free');
```

### Scheduled Tasks

```php
// In App\Console\Kernel
protected function schedule(Schedule $schedule)
{
    // Sync PropelAuth users daily
    $schedule->call(function () {
        $result = app('earhart')->queryUsers(pageSize: 1000);
        
        foreach ($result->allPages() as $userData) {
            $user = \LittleGreenMan\Earhart\PropelAuth\UserData::fromArray($userData);
            
            \App\Models\User::updateOrCreate(
                ['propel_id' => $user->user_id],
                [
                    'email' => $user->email,
                    'name' => "{$user->first_name} {$user->last_name}",
                    'email_verified_at' => $user->email_confirmed ? now() : null,
                ]
            );
        }
    })->daily();
}
```

## Best Practices

1. **Use Caching**: Enable caching to reduce API calls and improve performance.

2. **Handle Errors Gracefully**: Always wrap API calls in try-catch blocks.

3. **Leverage Webhooks**: Use webhooks for real-time updates instead of polling the API.

4. **Batch Operations**: When processing many items, use pagination efficiently.

5. **Cache Invalidation**: Invalidate cache when data changes via webhooks.

6. **Fresh Data When Critical**: Use `fresh: true` parameter for critical operations where stale data could cause issues.

7. **Rate Limiting**: Implement exponential backoff when handling `RateLimitException`.

8. **Security**: Never expose your API key in client-side code or logs.

## Missing Features & Limitations

While Earhart provides a solid PropelAuth integration, some API features are not yet implemented:

### User API Limitations

1. **Isolated Org Support**: The `isolatedOrgId` parameter is not supported in:
   - `getUserByEmail()`
   - `getUserByUsername()`
   - `queryUsers()`

2. **Legacy User ID Filtering**: `queryUsers()` doesn't support filtering by `legacyUserId`

3. **Create User Options**: Missing parameters:
   - `ignoreDomainRestrictions`
   - `emailConfirmed`
   - `sendEmailToConfirmEmailAddress`

4. **Employee API**: Cannot fetch employee information (used for impersonation tracking)
   - Missing: `fetchEmployeeById()`

5. **OAuth Tokens**: Cannot fetch OAuth tokens from social login providers
   - Missing: Fetch user OAuth tokens endpoint

### Organization API Limitations

1. **Create Organization**: Missing parameters:
   - `domain`
   - `enableAutoJoiningByDomain` (domain auto-join)
   - `membersMustHaveMatchingDomain` (domain restrictions)
   - `maxUsers`
   - `customRoleMappingName`
   - `legacyOrgId`

2. **Update Organization**: Missing parameters:
   - `domain`
   - `extraDomains`
   - `enableAutoJoiningByDomain`
   - `membersMustHaveMatchingDomain`
   - `maxUsers`
   - `canSetupSaml`
   - `legacyOrgId`
   - `ssoTrustLevel`

3. **Query Organizations**: Missing filter parameters:
   - `legacyOrgId`
   - `name` (search by name)
   - `domain` (search by domain)

4. **Get Organization Users**: Missing parameters:
   - `role` (filter by role)
   - `pageNumber` (only pageSize is supported)

5. **Invite User by User ID**: Not implemented
   - Missing: `inviteUserToOrgByUserId()`

6. **Change User Role**: Missing `additionalRoles` parameter

### API Key Management (Not Implemented)

The entire API Key management system for end-user/M2M keys is not yet implemented:

**Missing Endpoints:**
- `validateApiKey()` - Validate user/org API keys
- `validatePersonalApiKey()` - Validate personal API keys
- `validateOrgApiKey()` - Validate organization API keys
- `createApiKey()` - Create API keys for users/orgs
- `updateApiKey()` - Update API key metadata/expiration
- `deleteApiKey()` - Delete/archive API keys
- `fetchApiKey()` - Fetch API key by ID
- `fetchCurrentApiKeys()` - List active API keys
- `fetchArchivedApiKeys()` - List expired/archived API keys
- `fetchApiKeyUsage()` - Get API key usage statistics
- `importApiKey()` - Import API keys from external systems
- `validateImportedApiKey()` - Validate imported API keys

**Note**: These are separate from your PropelAuth API Key used for backend integration. These endpoints manage API keys that your end users create.

### Social Login & OAuth

Not implemented:
- Social login redirect URL generation
- Social account linking
- Fetching OAuth tokens from providers (Google, GitHub, etc.)

### Workarounds

For missing features, you can make direct HTTP requests:

```php
use Illuminate\Support\Facades\Http;

// Example: Create org with domain restrictions
$response = Http::withToken(config('services.propelauth.api_key'))
    ->post(config('services.propelauth.auth_url') . '/api/backend/v1/org/', [
        'name' => 'Acme Inc',
        'domain' => 'acme.com',
        'enableAutoJoiningByDomain' => true,
        'membersMustHaveMatchingDomain' => true,
        'maxUsers' => 100,
    ]);

$orgId = $response->json()['orgId'];
```

### Feature Requests

If you need any of these features, please:
1. Open an issue on the [GitHub repository](https://github.com/little-green-man/earhart)
2. Submit a pull request implementing the feature
3. Contact the maintainers for prioritization

For more information, see:
- [README.md](../README.md) - Installation and setup
- [PropelAuth API Documentation](https://docs.propelauth.com/reference/api/getting-started)
- [REFRESHING_USER_TOKENS.md](./REFRESHING_USER_TOKENS.md) - Token refresh guide
