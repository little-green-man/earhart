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
- [Validating Access Tokens](#validating-access-tokens)
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
- [Step-Up MFA](#step-up-mfa)
- [End-User API Keys](#end-user-api-keys)
- [Insights](#insights)
- [Pagination & Data Handling](#pagination--data-handling)
- [Caching](#caching)
- [Error Handling](#error-handling)
- [Testing](#testing)
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

## Validating Access Tokens

Access tokens are verified locally: Earhart fetches your environment's public key once from PropelAuth, caches it, and checks each token's signature, expiry and issuer, with 60 seconds of clock skew allowed.

```php
use LittleGreenMan\Earhart\Exceptions\InvalidTokenException;

try {
    // No API call per token
    $token = app('earhart')->verifyAccessToken($request->bearerToken());
    $token->userId;
    $token->isAtLeastRoleIn($orgId, 'Admin');
    $token->isImpersonated();

    // Or verify, then fetch the current user (picks up a user disabled since the token was issued)
    $user = app('earhart')->validateToken($request->bearerToken());
} catch (InvalidTokenException $e) {
    abort(401);
}
```

`VerifyPropelAuthUser` uses `validateToken()`. With caching enabled, the user it fetches can be up to the cache TTL old unless PropelAuth's webhooks invalidate it; pass `fresh: true` to `validateToken()` to always fetch. If PropelAuth can't be reached or the API key is wrong, the middleware lets the exception through to your error handler rather than returning 401. To skip the key request, set `PROPELAUTH_VERIFIER_KEY` to the public key from the **Backend Integration** page. After rotating the key in PropelAuth, call `app('earhart')->users()->forgetVerifierKey()`.

`VerifyPropelAuthOrg` and `VerifyPropelAuthPermission` read memberships from the user. `VerifyPropelAuthPermission` takes a role and passes any user whose role inherits it (an Owner passes `Admin`), or `permission:<name>` to check a permission.

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
    
    // Memberships (included by default; pass includeOrgs: false to skip)
    foreach ($user->orgs as $orgId => $org) {   // OrgMemberInfo, keyed by org ID
        echo $org->orgName;
        echo $org->userRole;                    // e.g. Admin
        print_r($org->userPermissions);         // e.g. ['propelauth::can_invite']
    }

    $user->isMemberOf($orgId);
    $user->roleIn($orgId);                      // Admin
    $user->isAtLeastRoleIn($orgId, 'Member');   // true: uses your role hierarchy
    $user->hasPermissionIn($orgId, 'propelauth::can_invite');
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

#### Fetch Several Users at Once

```php
// One request; unknown IDs are left out. Keyed by user ID, email or username.
$users = app('earhart')->users()->getUsersByIds(['user_1', 'user_2'], includeOrgs: true);
$users = app('earhart')->users()->getUsersByEmails(['a@example.com', 'b@example.com']);
$users = app('earhart')->users()->getUsersByUsernames(['ant', 'bea']);

$users['user_1']->email;
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

Both throw `InvalidUserException` if the user does not exist.

#### Delete User

> **API Reference**: [Delete User](https://docs.propelauth.com/reference/api/user#delete-user)

```php
app('earhart')->deleteUser('user_id_here'); // Throws InvalidUserException if the user does not exist
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

#### Allow or Stop Creating Organisations

```php
app('earhart')->users()->enableCanCreateOrgs('user_id_here');
app('earhart')->users()->disableCanCreateOrgs('user_id_here');
```

### Social Login Tokens

> **API Reference**: [Social Login APIs](https://docs.propelauth.com/reference/api/social-login)

PropelAuth keeps the OAuth tokens from a user's social logins, so you can call the provider's API on their behalf.

```php
$tokens = app('earhart')->users()->getOAuthTokens('user_id_here'); // Keyed by provider

if (isset($tokens['google'])) {
    $tokens['google']->accessToken;
    $tokens['google']->authorizedScopes;
    $tokens['google']->isExpired();
}

// Have PropelAuth refresh the token first
$token = app('earhart')->users()->getFreshOAuthToken('user_id_here', 'google');
```

### PropelAuth Team Members

When one of your team impersonates a user, the session's `impersonatorUserId` is their employee ID:

```php
$token = app('earhart')->verifyAccessToken($bearer);

if ($token->isImpersonated()) {
    $email = app('earhart')->users()->getEmployeeEmail($token->impersonatorUserId);
}
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
    domain: 'newcompany.com',
    enableAutoJoiningByDomain: true,       // Users with a matching email domain can join without an invite
    membersMustHaveMatchingDomain: false,
    maxUsers: 50,
    customRoleMappingName: 'Business Plan',
);

// Metadata can't be set on create; follow up with an update
app('earhart')->organisations()->updateOrganisation($orgId, metadata: ['industry' => 'Technology']);
```

#### Update Organisation

> **API Reference**: [Update Org](https://docs.propelauth.com/reference/api/org#update-org)

```php
app('earhart')->organisations()->updateOrganisation(
    orgId: 'org_id_here',
    name: 'Updated Company Name',
    metadata: ['industry' => 'Software'],
    autojoinByDomain: true,
    maxUsers: 100,
    require2faBy: now()->addMonth(),   // or a string like "2026-01-20 12:34:56 UTC"
);
```

Only the arguments you pass are changed. Also available: `domain`, `extraDomains`, `restrictToDomain`, `canSetupSaml`, `legacyOrgId`, `ssoTrustLevel` and the `passwordRotation*` settings.

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
    role: 'Member',                 // Required
    additionalRoles: ['Billing'],   // Optional, for multi-role setups
);
```

#### Invite User to Organisation

> **API Reference**: [Invite User to Org](https://docs.propelauth.com/reference/api/org#invite-user-to-org)

```php
app('earhart')->organisations()->inviteUserToOrganisation(
    orgId: 'org_id_here',
    email: 'newuser@example.com',
    role: 'Admin',                  // Required
);
```

#### Invite an Existing User by ID

```php
app('earhart')->organisations()->inviteUserToOrganisationById(
    orgId: 'org_id_here',
    userId: 'user_id_here',
    role: 'Member',
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
    echo "{$mapping['customRoleMappingName']}: {$mapping['numOrgsSubscribed']} organisations\n";
}
```

#### Subscribe Organisation to Role Mapping

> **API Reference**: [Subscribe Org to Role Mapping](https://docs.propelauth.com/reference/api/org#subscribe-org-to-role-mapping)

```php
app('earhart')->organisations()->subscribeOrgToRoleMapping(
    orgId: 'org_id_here',
    mappingName: 'Paid Plan',
);
```

### Organisation Invites

#### Get Pending Invites

> **API Reference**: [Fetch Pending Invites](https://docs.propelauth.com/reference/api/org#fetch-pending-invites)

```php
// Get all pending invites
$result = app('earhart')->organisations()->getPendingInvites();

foreach ($result->allPages() as $invite) {
    echo "Email: {$invite['inviteeEmail']}\n";
    echo "Org: {$invite['orgName']}\n";
    echo "Role: {$invite['roleInOrg']}\n";
}

// For one organisation, paged
$result = app('earhart')->organisations()->getPendingInvites(orgId: 'org_id_here', pageSize: 20, pageNumber: 0);
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
$url = app('earhart')->organisations()->createSAMLConnectionLink('org_id_here', expiresInSeconds: 86400);
return redirect($url);
```

#### Fetch SAML SP Metadata

```php
$metadata = app('earhart')->organisations()->fetchSAMLMetadata('org_id_here');

// Give these to the organisation's IdP
echo $metadata->entityId;
echo $metadata->acsUrl;
echo $metadata->logoutUrl;
```

#### Set SAML IdP Metadata

```php
app('earhart')->organisations()->setSAMLIdPMetadata(
    orgId: 'org_id_here',
    idpEntityId: 'http://www.okta.com/example',
    idpSsoUrl: 'https://dev.okta.com/app/example/sso/saml',
    idpCertificate: '-----BEGIN CERTIFICATE-----...-----END CERTIFICATE-----',
    provider: 'Okta',
);
```

#### Set OIDC IdP Metadata

For SSO through an OIDC identity provider instead of SAML:

```php
// Okta
app('earhart')->organisations()->setOIDCIdPMetadata(
    orgId: 'org_id_here',
    clientId: '0oaulhbkt9YBiT3Pn697',
    clientSecret: $secret,
    idpType: 'Okta',
    oktaSsoDomain: 'example.okta.com',
);

// Microsoft Entra: idpType 'Azure' with entraTenantId
// Any other provider: idpType 'Generic' with authUrl, tokenUrl and userinfoUrl
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

### SCIM Groups

> **API Reference**: [Fetch Org SCIM Groups](https://docs.propelauth.com/reference/api/enterprise-sso)

Groups an organisation's identity provider has provisioned over SCIM:

```php
// All groups, or only those a user belongs to
$groups = app('earhart')->organisations()->getScimGroups('org_id_here', userId: 'user_id_here');

foreach ($groups->allPages() as $group) {
    echo "{$group->displayName} ({$group->externalIdFromIdp})\n";
}

// One group with its members
$group = app('earhart')->organisations()->getScimGroup('org_id_here', 'group_id_here');
$group->memberUserIds;
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

## Step-Up MFA

> **API Reference**: [Step-Up MFA APIs](https://docs.propelauth.com/reference/api/mfa)

Ask a signed-in user for a fresh MFA code before a sensitive action. Verifying a code gives a grant; check the grant where the action happens. The action type is your own label and must match.

```php
use LittleGreenMan\Earhart\Exceptions\StepUpMfaException;
use LittleGreenMan\Earhart\PropelAuth\StepUpGrantType;

$mfa = app('earhart')->mfa();
$setup = $mfa->getUserMfaMethods($userId); // null if the user has no MFA

try {
    if ($setup->usesTotp()) {
        $grant = $mfa->verifyTotp($userId, $request->input('code'), 'DELETE_ACCOUNT');
    } else {
        // Send a code to one of the user's numbers, then verify it
        $challengeId = $mfa->sendSmsCode($userId, array_key_first($setup->phoneNumbers), 'DELETE_ACCOUNT');
        $grant = $mfa->verifySmsCode($userId, $challengeId, $request->input('code'));
    }
} catch (StepUpMfaException $e) {
    if ($e->isIncorrectCode()) {
        return back()->withErrors(['code' => 'That code is not right']);
    }

    throw $e;
}

// Later, where the action happens. A one-time grant is used up here.
if (! $mfa->verifyGrant($userId, 'DELETE_ACCOUNT', $grant)) {
    abort(403);
}
```

Grants are one-time by default and valid for 300 seconds. Pass `grantType: StepUpGrantType::TimeBased` and `validForSeconds` to allow several actions within a window. If step-up MFA isn't on your PropelAuth plan, calls throw `FeatureNotEnabledException`.

## End-User API Keys

> **API Reference**: [API Key APIs](https://docs.propelauth.com/reference/api/apikey)

API keys your users create to call your API. They belong to a user (personal), an organisation, or a user within an organisation. These are separate from the PropelAuth API key Earhart uses.

### Protecting Routes

```php
use LittleGreenMan\Earhart\Middleware\VerifyPropelAuthApiKey;
use LittleGreenMan\Earhart\Middleware\VerifyPropelAuthPermission;

Route::middleware(VerifyPropelAuthApiKey::class)->get('/api/reports', ...);              // Any key
Route::middleware(VerifyPropelAuthApiKey::class.':personal')->get('/api/me', ...);       // Personal keys only
Route::middleware([
    VerifyPropelAuthApiKey::class.':org',
    VerifyPropelAuthPermission::class.':Admin',                                         // The key's user must be an Admin
])->post('/api/settings', ...);
```

The middleware reads `Authorization: Bearer <key>`, and sets `propelauth_api_key`, `propelauth_user` and `propelauth_org_id` on the request. An invalid key gets 401. A key over the rate limit you set for it in PropelAuth gets 429 with a `Retry-After` header.

### Validating Keys Yourself

```php
use LittleGreenMan\Earhart\Exceptions\ApiKeyRateLimitException;
use LittleGreenMan\Earhart\Exceptions\InvalidApiKeyException;

try {
    $key = app('earhart')->apiKeys()->validateApiKey($request->bearerToken());

    $key->isPersonal();   // or isOrg()
    $key->user;           // UserData, with memberships
    $key->org;            // OrganisationData, for org keys
    $key->userInOrg;      // OrgMemberInfo: the user's role in that org
    $key->metadata;       // What you stored with the key
} catch (InvalidApiKeyException $e) {
    abort(401);
} catch (ApiKeyRateLimitException $e) {
    abort(429, $e->userFacingError ?? 'Too many requests');
}
```

`validatePersonalApiKey()` and `validateOrgApiKey()` also check the kind of key. A key-specific rate limit is never retried, unlike Earhart's own `RateLimitException`.

### Managing Keys

```php
$keys = app('earhart')->apiKeys();

// Create: show $new->apiKeyToken to the owner once; it can't be fetched again
$new = $keys->createApiKey(userId: $userId, orgId: $orgId, expiresAt: now()->addYear(), metadata: ['scope' => 'read'], displayName: 'CI');

$key = $keys->getApiKey($new->apiKeyId);                       // ApiKey: owner, expiry, metadata
$active = $keys->getActiveApiKeys(userId: $userId);              // Paginated ApiKey items; also orgId, userEmail
$archived = $keys->getArchivedApiKeys(orgId: $orgId);            // Expired and deleted keys

$keys->updateApiKey($new->apiKeyId, metadata: ['scope' => 'write']);
$keys->updateApiKey($new->apiKeyId, neverExpire: true);
$keys->deleteApiKey($new->apiKeyId);

$count = $keys->getApiKeyUsage(today(), apiKeyId: $new->apiKeyId); // Validations that day

// Bring over keys from another system so they keep working
$apiKeyId = $keys->importApiKey($legacySecret, userId: $userId);
$keys->validateImportedApiKey($legacySecret);
```

## Insights

> **API Reference**: [User and Org Insights](https://docs.propelauth.com/reference/api/insights)

PropelAuth's reports on user and organisation activity, and chart metrics.

```php
use LittleGreenMan\Earhart\PropelAuth\Insights\ChartCadence;
use LittleGreenMan\Earhart\PropelAuth\Insights\ChartMetric;
use LittleGreenMan\Earhart\PropelAuth\Insights\OrgReportType;
use LittleGreenMan\Earhart\PropelAuth\Insights\UserReportType;

$insights = app('earhart')->insights();

// User reports: Reengagement, Churn, TopInviter, Champion
$report = $insights->getUserReport(UserReportType::TopInviter, interval: 30);

$report->reportTime;                       // When PropelAuth generated it
foreach ($report->allPages() as $user) {   // UserReportRecord
    echo "{$user->email}: {$user->extraProperties['num_invites']}\n";
    $user->orgs;                           // [['orgId', 'displayName', 'userRole'], ...]
}

// Organisation reports: Reengagement, Churn, Growth, Attrition
$report = $insights->getOrgReport(OrgReportType::Growth, interval: 90, pageSize: 25);
$report->items[0]->name;                   // OrgReportRecord
$report->items[0]->numUsers;

// Chart metrics: Signups, OrgsCreated, ActiveUsers, ActiveOrgs
$chart = $insights->getChartMetrics(ChartMetric::Signups, ChartCadence::Weekly, now()->subMonths(3), now());
$chart->toArray();                         // ['2026-07-06' => 42, ...]
$chart->points;                            // Each with date, result and cadenceCompleted (false while in progress)
```

Each report takes its own intervals, listed by `$type->intervals()`: `Weekly` or `Monthly` for re-engagement, 7, 14 or 30 days for churn, and 30, 60 or 90 days otherwise. An interval a report doesn't offer throws `InvalidArgumentException` before any request is made. Leave it out for PropelAuth's default.

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

Every API failure throws a `PropelAuthException` or one of its subclasses. Each has `getStatusCode()` and `getContext()`.

| Exception | When |
| --- | --- |
| `InvalidUserException` | 404 on a call that takes a user ID, email or username |
| `InvalidOrgException` | 404 on a call that takes an organisation ID |
| `ValidationException` | 400 or 422. `getErrors()` returns PropelAuth's error body |
| `UnauthorizedException` | 401 or 403, usually a wrong or under-privileged API key |
| `RateLimitException` | 429 after retries are used up. `$retryAfterSeconds` holds the wait |
| `InvalidTokenException` | An access token is malformed, expired, or from another environment |
| `FeatureNotEnabledException` | 426: the feature isn't enabled for the project (organisation calls need B2B support) |
| `StepUpMfaException` | A step-up MFA code was wrong, or the user has no MFA of that kind |
| `InvalidApiKeyException` | An end-user API key is invalid, expired, deleted or the wrong kind |
| `ApiKeyRateLimitException` | An end-user API key hit the rate limit set for it in PropelAuth (not retried) |
| `PropelAuthException` | Anything else, including a 404 on calls naming both a user and an organisation |

Write methods return `true` on success and throw on failure, so a missing user or organisation never passes silently.

Messages hold only the status, method and endpoint. The response body, truncated, is in `getContext()['response_body']`; it may contain user data, so decide whether to send it to your error tracker.

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

// Treat "already gone" as success, e.g. in a GDPR erase
try {
    app('earhart')->deleteUser($propelId);
} catch (InvalidUserException $e) {
    // Nothing to delete
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

### Timeouts and Retries

Requests time out after `earhart.http.timeout` seconds (default 30; connect timeout 10). A 429 is retried `earhart.retries.times` times (default 2), waiting for PropelAuth's `Retry-After` or backing off exponentially, but never longer than `earhart.retries.max_delay_ms` (default 5,000). If `Retry-After` asks for longer, the exception is thrown at once.

Waits block the PHP worker, so you may want no retries in web requests and more in queued jobs:

```php
// .env: PROPELAUTH_RETRY_TIMES=0

// In a job
config(['earhart.retries.times' => 3, 'earhart.retries.max_delay_ms' => 60_000]);
```

### Graceful Error Handling

```php
use Illuminate\Support\Facades\Cache;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;

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
    } catch (PropelAuthException $e) {
        // Log unexpected errors
        Log::error('PropelAuth API error', [
            'user_id' => $userId,
            'status' => $e->getStatusCode(),
            'error' => $e->getMessage(),
        ]);
        return null;
    }
}
```

## Testing

`Earhart::fake()` (or `PropelAuth::fake()`) swaps Earhart for an in-memory fake. It replaces the facade, injected `Earhart`, `UserService` and `OrganisationService`, so the middleware and `users()`/`organisations()` use it too. Nothing is sent to PropelAuth.

```php
use LittleGreenMan\Earhart\Earhart;
use LittleGreenMan\Earhart\Services\UserService;

test('admins can disable a user', function () {
    $fake = Earhart::fake();
    $user = $fake->addUser(['email' => 'jane@example.com']);

    $this->post("/admin/users/{$user->userId}/disable")->assertOk();

    $fake->assertUserDisabled($user->userId);
});
```

### Seeding

```php
$user = $fake->addUser(['email' => 'jane@example.com', 'firstName' => 'Jane']); // UserData; missing fields get defaults
$org = $fake->addOrganisation(['name' => 'Acme'], members: [$user->userId => 'Admin']);
$fake->addMember($org->orgId, $otherUserId, 'Member');
$token = $fake->issueToken($user->userId); // Accepted by validateToken(), verifyAccessToken() and VerifyPropelAuthUser
$fake->withRolePermissions(['Admin' => ['propelauth::can_invite']]); // Roles default to Owner > Admin > Member
```

Also for the newer endpoints:

```php
$fake->addOAuthToken($user->userId, 'google', 'access-token');
$fake->addEmployee('employee_id', 'staff@example.com');
$groupId = $fake->addScimGroup($org->orgId, 'Engineering', [$user->userId]);
$fake->withMfa($user->userId);                      // Authenticator app
$fake->withMfa($user->userId, ['phone_id' => '1234']); // SMS, numbers keyed by MFA phone ID
$fake->withValidMfaCode('654321');                  // Defaults to 123456
$key = $fake->addApiKey(userId: $user->userId);     // Use $key->apiKeyToken in requests
$fake->withUserReport(UserReportType::TopInviter, [['userId' => 'u1', 'email' => 'a@example.com']]);
$fake->withOrgReport(OrgReportType::Growth, [['orgId' => 'o1', 'name' => 'Acme', 'numUsers' => 12]]);
$fake->withChartMetrics(ChartMetric::Signups, ['2026-01-01' => 3, '2026-01-02' => 5]);
```

Seeding is not recorded as a call.

### Behaviour

The fake keeps state: `disableUser()` sets `enabled` to `false`, `deleteUser()` removes the user and their memberships, `createUser()` with an existing email throws a `ValidationException`, and so on. Calls on a missing user or organisation throw the same exceptions as the real services, so you can test "already gone" handling without scripting anything.

### Scripting failures

```php
$fake->failNext(UserService::class, 500);           // Next UserService call throws PropelAuthException (500); MfaService::class works too
$fake->failNext('deleteUser', 429);                 // Next deleteUser() throws RateLimitException
$fake->failNext('getUser', 403, times: 2);          // Next two getUser() calls throw UnauthorizedException
$fake->failNext('getUser', InvalidUserException::notFound('x')); // Throw a specific exception
```

A status maps to the same exception the real services throw. A method-specific failure is used before a service-wide one.

### Assertions

Assertions only count calls that succeeded.

```php
$fake->assertUserCreated('jane@example.com');
$fake->assertUserUpdated($userId);
$fake->assertUserDisabled($userId);
$fake->assertUserEnabled($userId);
$fake->assertUserDeleted($userId);
$fake->assertUserLoggedOut($userId);
$fake->assertOrganisationCreated('Acme');
$fake->assertOrganisationUpdated($orgId);
$fake->assertOrganisationDeleted($orgId);
$fake->assertUserAddedToOrganisation($orgId, $userId, 'Admin');
$fake->assertUserRemovedFromOrganisation($orgId, $userId);
$fake->assertUserInvitedToOrganisation($orgId, 'new@example.com');
$fake->assertApiKeyCreated(userId: $userId);
$fake->assertApiKeyDeleted($apiKeyId);

// Any method, with its named arguments
$fake->assertCalled('createAccessToken', fn (array $args) => $args['durationInMinutes'] === 5);
$fake->assertCalledTimes('getUser', 2);
$fake->assertNotCalled('deleteUser');
$fake->assertNothingCalled();

// Raw log, including failed calls
$fake->calls();
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
6. **Tune `earhart.retries`**: fail fast in web requests, retry in queued jobs
7. **Never expose your API key** in client-side code or logs

## Missing Features & Limitations

Earhart covers every PropelAuth backend API endpoint. Not included:

- **Social login redirects and account linking**: these are browser flows; use the Socialite provider for login.
- **OAuth2 and MCP authorisation servers**: also browser and OAuth-client flows, outside a backend API client.

### Calling Other Endpoints

If PropelAuth adds an endpoint before Earhart does, a subclass can use `makeRequest()`, which handles authentication, retries, errors and case conversion:

```php
use LittleGreenMan\Earhart\Services\UserService;

class AppUserService extends UserService
{
    public function newEndpoint(string $userId): array
    {
        return $this->makeRequest('GET', "/api/backend/v1/user/{$userId}/new_endpoint");
    }
}
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
