# Using the PropelAuth API

This guide shows you how to use PropelAuth's API from your Laravel application via Earhart. For installation and setup, see the [README](../README.md).

> **PropelAuth API Reference**: [https://docs.propelauth.com/reference/api/getting-started](https://docs.propelauth.com/reference/api/getting-started)

## Automatic Case Conversion

Earhart automatically handles case conversion between PHP's camelCase conventions and PropelAuth's snake_case API:

- **Method parameters**: Use camelCase (e.g., `firstName`, `emailConfirmed`) - automatically converted to snake_case for the API
- **Response data**: API returns snake_case - automatically converted to camelCase in DTOs
- **No manual conversion needed**: Just use standard PHP naming conventions throughout your application

```php
// You write (camelCase):
$user = app('earhart')->createUser([
    'email' => 'user@example.com',
    'firstName' => 'John',
    'lastName' => 'Doe',
    'emailConfirmed' => true,
]);

// Earhart sends to API (snake_case):
// { "email": "...", "first_name": "John", "last_name": "Doe", "email_confirmed": true }

// Access properties (camelCase):
echo $user->firstName;  // "John"
echo $user->emailConfirmed;  // true
```

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

Access the PropelAuth API through the `app('earhart')` helper or dependency injection:

```php
use LittleGreenMan\Earhart\Earhart;

// Using the helper
$earhart = app('earhart');

// Via dependency injection
public function __construct(protected Earhart $earhart) {}
```

Ensure your PropelAuth API key is configured in `.env` as `PROPELAUTH_API_KEY`.

## User Management

> **PropelAuth User API Reference**: [https://docs.propelauth.com/reference/api/user](https://docs.propelauth.com/reference/api/user)

### Fetching Users

#### Get a Single User by ID

> **API Reference**: [Fetch User By User ID](https://docs.propelauth.com/reference/api/user#fetch-user-by-user-id)

```php
use LittleGreenMan\Earhart\Exceptions\InvalidUserException;

try {
    $user = app('earhart')->getUser('user_id_here');
    
    echo $user->email;              // user@example.com
    echo $user->firstName;          // John
    echo $user->lastName;           // Doe
    echo $user->username;           // johndoe
    echo $user->pictureUrl;         // https://...
    echo $user->emailConfirmed;     // true/false
    echo $user->enabled;            // true/false
    echo $user->locked;             // true/false
    echo $user->hasPassword;        // true/false
    echo $user->mfaEnabled;         // true/false
    echo $user->createdAt;          // Carbon instance
    echo $user->lastActiveAt;       // Carbon instance
    
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

> **API Reference**: [Fetch User By Email](https://docs.propelauth.com/reference/api/user#fetch-user-by-email)

```php
try {
    $user = app('earhart')->getUserByEmail('user@example.com');
    echo "Found user: {$user->firstName} {$user->lastName}";
} catch (InvalidUserException $e) {
    echo "No user found with that email";
}
```

#### Fetch User by Username

> **API Reference**: [Fetch User By Username](https://docs.propelauth.com/reference/api/user#fetch-user-by-username)

```php
try {
    $user = app('earhart')->getUserByUsername('johndoe', includeOrgs: true);
    echo "User ID: {$user->userId}";
} catch (InvalidUserException $e) {
    echo "No user found with that username";
}
```

#### Query Users with Filters

> **API Reference**: [Query Users](https://docs.propelauth.com/reference/api/user#query-users)

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
    echo "{$user->email} - {$user->firstName} {$user->lastName}\n";
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

> **API Reference**: [Create User](https://docs.propelauth.com/reference/api/user#create-user)

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
// User must use magic links or social login
$userId = app('earhart')->createUser(
    email: 'newuser@example.com',
    firstName: 'Jane',
    lastName: 'Smith'
);
```

### Updating Users

#### Update User Profile

> **API Reference**: [Update User Metadata](https://docs.propelauth.com/reference/api/user#update-user-metadata)

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

> **API Reference**: [Update User Email](https://docs.propelauth.com/reference/api/user#update-user-email)

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

> **API Reference**: [Update User Password](https://docs.propelauth.com/reference/api/user#update-user-password)

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

> **API Reference**: [Clear User Password](https://docs.propelauth.com/reference/api/user#clear-user-password)

```php
// Remove password (user must use magic links or social login)
app('earhart')->clearUserPassword('user_id_here');
```

### User Authentication

#### Create Magic Link

> **API Reference**: [Create Magic Link](https://docs.propelauth.com/reference/api/user#create-magic-link)

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

> **API Reference**: [Create Access Token](https://docs.propelauth.com/reference/api/user#create-access-token)

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

> **API Reference**: [Enable/Disable User](https://docs.propelauth.com/reference/api/user#enabledisable-user)

```php
// Disable user (blocks login)
app('earhart')->disableUser('user_id_here');

// Enable user (allows login)
app('earhart')->enableUser('user_id_here');
```

#### Delete User

> **API Reference**: [Delete User](https://docs.propelauth.com/reference/api/user#delete-user)

```php
app('earhart')->deleteUser('user_id_here');
```

#### Disable Two-Factor Authentication

> **API Reference**: [Disable 2FA](https://docs.propelauth.com/reference/api/user#disable-2fa)

```php
// Remove 2FA from user's account
app('earhart')->disable2FA('user_id_here');
```

#### Resend Email Confirmation

> **API Reference**: [Resend Email Confirmation](https://docs.propelauth.com/reference/api/user#resend-email-confirmation)

```php
app('earhart')->resendEmailConfirmation('user_id_here');
```

#### Logout All Sessions

> **API Reference**: [Logout User](https://docs.propelauth.com/reference/api/user#logout-user)

```php
// Force logout from all devices
app('earhart')->logoutAllSessions('user_id_here');
```

#### Get User Signup Parameters

> **API Reference**: [Fetch User Signup Query Params](https://docs.propelauth.com/reference/api/user#fetch-user-signup-query-params)

```php
// Retrieve query parameters from when user signed up
$params = app('earhart')->getUserSignupParams('user_id_here');

// Example: ['utm_source' => 'google', 'utm_campaign' => 'summer2024']
```

## Organisation Management

> **PropelAuth Organisation API Reference**: [https://docs.propelauth.com/reference/api/org](https://docs.propelauth.com/reference/api/org)

### Fetching Organisations

#### Get Single Organisation

> **API Reference**: [Fetch Org](https://docs.propelauth.com/reference/api/org#fetch-org)

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

#### Query All Organisations

> **API Reference**: [Fetch Orgs](https://docs.propelauth.com/reference/api/org#fetch-orgs)

```php
$result = app('earhart')->organisations()->queryOrganisations(
    orderBy: 'CREATED_AT_DESC',
    pageNumber: 0,
    pageSize: 100
);

// Items are already OrganisationData objects
foreach ($result->items as $org) {
    echo "{$org->displayName}\n";
}

// Or use the facade method for simpler access:
$orgsData = app('earhart')->getOrganisations(pageSize: 100);
foreach ($orgsData->orgs as $org) {
    echo "{$org->displayName}\n";
}
```

#### Get Users in Organisation

> **API Reference**: [Fetch Users in Org](https://docs.propelauth.com/reference/api/org#fetch-users-in-org)

```php
// Using service method (returns PaginatedResult with metadata):
$result = app('earhart')->organisations()->getOrganisationUsers(
    orgId: 'org_id_here',
    pageSize: 100
);

echo "Total users in org: {$result->totalItems}";
echo "Has more results: " . ($result->hasMoreResults ? 'yes' : 'no');

// Items are already UserData objects
foreach ($result->items as $user) {
    echo "{$user->email} - {$user->firstName} {$user->lastName}\n";
}

// Or use the facade method (returns simple array):
$users = app('earhart')->getUsersInOrganisation('org_id_here');
foreach ($users as $user) {
    echo "{$user->email} - {$user->firstName} {$user->lastName}\n";
}
```

### Creating & Updating Organisations

#### Create Organisation

> **API Reference**: [Create Org](https://docs.propelauth.com/reference/api/org#create-org)

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

#### Update Organisation

> **API Reference**: [Update Org](https://docs.propelauth.com/reference/api/org#update-org)

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

#### Delete Organisation

> **API Reference**: [Delete Org](https://docs.propelauth.com/reference/api/org#delete-org)

```php
try {
    app('earhart')->organisations()->deleteOrganisation('org_id_here');
    echo "Organization deleted successfully";
} catch (InvalidOrgException $e) {
    echo "Failed to delete organization";
}
```

### Managing Organisation Members

#### Add User to Organisation

> **API Reference**: [Add User to Org](https://docs.propelauth.com/reference/api/org#add-user-to-org)

```php
// Add existing user to organization
app('earhart')->organisations()->addUserToOrganisation(
    orgId: 'org_id_here',
    userId: 'user_id_here',
    role: 'Member'
);
```

#### Invite User to Organisation

> **API Reference**: [Invite User to Org](https://docs.propelauth.com/reference/api/org#invite-user-to-org)

```php
app('earhart')->organisations()->inviteUserToOrganisation(
    orgId: 'org_id_here',
    email: 'newuser@example.com',
    role: 'Admin'
);
```

#### Remove User from Organisation

> **API Reference**: [Remove User from Org](https://docs.propelauth.com/reference/api/org#remove-user-from-org)

```php
app('earhart')->organisations()->removeUserFromOrganisation(
    orgId: 'org_id_here',
    userId: 'user_id_here'
);
```

#### Change User Role

> **API Reference**: [Change User Role in Org](https://docs.propelauth.com/reference/api/org#change-user-role-in-org)

```php
app('earhart')->organisations()->changeUserRole(
    orgId: 'org_id_here',
    userId: 'user_id_here',
    role: 'Admin'
);
```

### Organisation Roles

#### Get Role Mappings

> **API Reference**: [Fetch Custom Role Mappings](https://docs.propelauth.com/reference/api/org#fetch-custom-role-mappings)

```php
$roleMappings = app('earhart')->organisations()->getRoleMappings();

foreach ($roleMappings as $mapping) {
    echo "Role mapping ID: {$mapping['customRoleMappingId']}\n";
    echo "Name: {$mapping['name']}\n";
    // Access role definitions
}
```

#### Subscribe Organisation to Role Mapping

> **API Reference**: [Subscribe Org to Role Mapping](https://docs.propelauth.com/reference/api/org#subscribe-org-to-role-mapping)

```php
app('earhart')->organisations()->subscribeOrgToRoleMapping(
    orgId: 'org_id_here',
    mappingId: 'mapping_id_here'
);
```

### Organisation Invites

#### Get Pending Invites

> **API Reference**: [Fetch Pending Invites](https://docs.propelauth.com/reference/api/org#fetch-pending-invites)

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

> **API Reference**: [Revoke Invite](https://docs.propelauth.com/reference/api/org#revoke-invite)

```php
app('earhart')->organisations()->revokePendingInvite(
    orgId: 'org_id_here',
    inviteeEmail: 'user@example.com'
);
```

### SAML Configuration

> **API Reference**: [SAML](https://docs.propelauth.com/reference/api/org#saml)

#### Allow Organisation to Setup SAML

```php
app('earhart')->organisations()->allowOrgToSetupSAML('org_id_here');
```

#### Create SAML Connection Link

```php
$url = app('earhart')->organisations()->createSAMLConnectionLink('org_id_here');
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

### Organisation Isolation

#### Migrate Organisation to Isolated

> **API Reference**: [Migrate Org to Isolated](https://docs.propelauth.com/reference/api/org#migrate-org-to-isolated)

Isolated organisations are completely separate tenants with their own user base.

```php
app('earhart')->organisations()->migrateOrgToIsolated('org_id_here');
```

**Note**: This is a one-way operation and cannot be reversed.

**Use cases**: B2B SaaS with complete data isolation, enterprise customers requiring dedicated tenancy, or regulatory compliance (HIPAA, SOC2).

## Pagination & Data Handling

### Working with Paginated Results

```php
$result = app('earhart')->queryUsers(pageSize: 50);

echo "Page {$result->currentPage} of {$result->lastPage()}";
echo "Showing {$result->count()} of {$result->totalItems} items";

if ($result->hasNextPage()) {
    $nextPage = $result->nextPage();
}

// Get as Laravel collection
$collection = $result->collection();
$filtered = $collection->filter(fn($user) => $user['enabled'] === true);

// Get all pages (use carefully with large datasets)
$allItems = $result->allPages();
```

### Converting to Collections

```php
$users = app('earhart')->queryUsers()->collection();

$activeUsers = $users->filter(fn($u) => $u['enabled'] === true);
$emails = $users->pluck('email');
```

## Caching

Earhart includes built-in caching to reduce API calls.

### Cache Configuration

```env
PROPELAUTH_CACHE_ENABLED=true
PROPELAUTH_CACHE_TTL=60  # minutes
```

### Using Cache

```php
// Cached (default)
$user = app('earhart')->getUser('user_id_here');

// Fresh data
$user = app('earhart')->getUser('user_id_here', fresh: true);

// Organisations also support caching
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
        app('earhart')->invalidateUserCache($event->userId);
        
        // Refresh user data
        $user = app('earhart')->getUser($event->userId, fresh: true);
        
        // Update your local database
        \App\Models\User::where('propel_id', $event->userId)->update([
            'name' => "{$user->firstName} {$user->lastName}",
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

> **API Reference**: [Migrate User](https://docs.propelauth.com/reference/api/user#migrate-user-from-external-source)

```php
$userId = app('earhart')->migrateUserFromExternal(
    email: 'user@example.com',
    emailConfirmed: true,
    existingUserId: 'old_system_id_123',
    existingPasswordHash: 'bcrypt_hash_here',
    existingMfaSecret: 'TOTP_SECRET',
    firstName: 'John',
    lastName: 'Doe',
    username: 'johndoe',
    properties: ['legacy_id' => 'old_system_id_123']
);
```

### Batch Operations

```php
$pageNumber = 0;

do {
    $result = app('earhart')->queryUsers(pageNumber: $pageNumber, pageSize: 100);
    
    foreach ($result->items as $userData) {
        $user = \LittleGreenMan\Earhart\PropelAuth\UserData::fromArray($userData);
        
        \App\Models\User::updateOrCreate(
            ['propel_id' => $user->userId],
            ['email' => $user->email, 'name' => "{$user->firstName} {$user->lastName}"]
        );
    }
    
    $pageNumber++;
} while ($result->hasNextPage());
```

### Handling Organisation Webhooks

```php
namespace App\Listeners;

use App\Models\Organisation;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgCreated;

class SyncOrganisationListener
{
    public function handle(OrgCreated $event): void
    {
        $orgData = app('earhart')->organisations()->getOrganisation($event->org_id);
        
        Organisation::updateOrCreate(
            ['propel_id' => $orgData->orgId],
            [
                'name' => $orgData->displayName,
                'slug' => $orgData->urlSafeOrgSlug,
                'metadata' => $orgData->metadata,
                'created_at' => $orgData->createdAt,
            ]
        );
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
    $schedule->call(function () {
        foreach (app('earhart')->queryUsers(pageSize: 1000)->allPages() as $userData) {
            $user = \LittleGreenMan\Earhart\PropelAuth\UserData::fromArray($userData);
            
            \App\Models\User::updateOrCreate(
                ['propel_id' => $user->userId],
                ['email' => $user->email, 'name' => "{$user->firstName} {$user->lastName}"]
            );
        }
    })->daily();
}
```

## Best Practices

1. **Enable caching** to reduce API calls and improve performance
2. **Always wrap API calls** in try-catch blocks for graceful error handling
3. **Use webhooks** for real-time updates instead of polling the API
4. **Invalidate cache** when data changes via webhooks
5. **Use `fresh: true`** for critical operations where stale data could cause issues
6. **Implement exponential backoff** when handling rate limit exceptions
7. **Never expose your API key** in client-side code or logs

## Missing Features & Limitations

Some PropelAuth API features aren't yet implemented in Earhart:

### User API Limitations

1. **Isolated Org Support**: The `isolatedOrgId` parameter isn't supported in:
   - `getUserByEmail()`
   - `getUserByUsername()`
   - `queryUsers()`

2. **Legacy User ID Filtering**: `queryUsers()` doesn't support `legacyUserId` filtering

3. **Create User Options**: Missing parameters:
   - `ignoreDomainRestrictions`
   - `emailConfirmed`
   - `sendEmailToConfirmEmailAddress`

4. **Employee API**: Cannot fetch employee information (used for impersonation tracking)
   - Missing: `fetchEmployeeById()`

5. **OAuth Tokens**: Cannot fetch OAuth tokens from social login providers
   - Missing: Fetch user OAuth tokens endpoint

### Organisation API Limitations

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

> **API Reference**: [API Key Reference](https://docs.propelauth.com/reference/api/apikey)

The entire API Key management system for end-user/M2M keys isn't yet implemented:
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

**Note**: These are separate from your PropelAuth API key used for backend integration. These endpoints manage API keys created by your end users.

### Social Login & OAuth

Not yet implemented:
- Social login redirect URL generation
- Social account linking
- Fetching OAuth tokens from providers

### Workarounds

For missing features, make direct HTTP requests to the PropelAuth API:

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

If you need any of these features:
1. Open an issue on the [GitHub repository](https://github.com/little-green-man/earhart)
2. Submit a pull request
3. Contact the maintainers

**See also**:
- [README.md](../README.md) - Installation and setup
- [PropelAuth API Documentation](https://docs.propelauth.com/reference/api/getting-started)
- [REFRESHING_USER_TOKENS.md](./REFRESHING_USER_TOKENS.md) - Token refresh guide
