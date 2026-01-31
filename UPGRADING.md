# Upgrading to v1.7.0

This guide helps you upgrade from Earhart v1.6.x to v1.7.0.

## Overview

Version 1.7.0 fixes the systematic snake_case/camelCase mismatch between PropelAuth's API and Earhart. All API parameters and responses now use proper PHP camelCase conventions, with automatic conversion to/from PropelAuth's snake_case format.

## Breaking Changes

### 1. Configuration Key Renamed

**Required Action:** Update your `config/services.php` file.

**Before:**
```php
'propelauth' => [
    'client_id' => env('PROPELAUTH_CLIENT_ID'),
    'client_secret' => env('PROPELAUTH_CLIENT_SECRET'),
    'redirect_url' => env('PROPELAUTH_CALLBACK_URL'), // ← OLD
    'auth_url' => env('PROPELAUTH_AUTH_URL'),
    'svix_secret' => env('PROPELAUTH_SVIX_SECRET'),
    'api_key' => env('PROPELAUTH_API_KEY'),
],
```

**After:**
```php
'propelauth' => [
    'client_id' => env('PROPELAUTH_CLIENT_ID'),
    'client_secret' => env('PROPELAUTH_CLIENT_SECRET'),
    'redirect' => env('PROPELAUTH_CALLBACK_URL'), // ← NEW
    'auth_url' => env('PROPELAUTH_AUTH_URL'),
    'svix_secret' => env('PROPELAUTH_SVIX_SECRET'),
    'api_key' => env('PROPELAUTH_API_KEY'),
],
```

### 2. UserData Property Names

**Affected Code:** Direct property access on `UserData` objects.

If you're accessing UserData properties directly, update from snake_case to camelCase:

**Before:**
```php
$user = app('earhart')->getUser($userId);

echo $user->user_id;           // OLD
echo $user->first_name;        // OLD
echo $user->last_name;         // OLD
echo $user->email_confirmed;   // OLD
echo $user->picture_url;       // OLD
echo $user->has_password;      // OLD
echo $user->mfa_enabled;       // OLD
echo $user->can_create_orgs;   // OLD
echo $user->created_at;        // OLD
echo $user->last_active_at;    // OLD
```

**After:**
```php
$user = app('earhart')->getUser($userId);

echo $user->userId;            // NEW
echo $user->firstName;         // NEW
echo $user->lastName;          // NEW
echo $user->emailConfirmed;    // NEW
echo $user->pictureUrl;        // NEW
echo $user->hasPassword;       // NEW
echo $user->mfaEnabled;        // NEW
echo $user->canCreateOrgs;     // NEW
echo $user->createdAt;         // NEW
echo $user->lastActiveAt;      // NEW
```

**Note:** These properties were already Carbon instances for dates, and remain unchanged in that respect.

## Non-Breaking Improvements

### API Method Parameters Now Accept camelCase

All API method parameters now accept camelCase (standard PHP convention). If you were already using camelCase, no changes needed. If you were using snake_case to work around the bug, you can now use camelCase:

**Now Works Correctly:**
```php
// Create user with camelCase parameters (now automatically converted)
$userId = app('earhart')->createUser([
    'email' => 'user@example.com',
    'firstName' => 'John',              // Automatically converts to first_name
    'lastName' => 'Doe',                // Automatically converts to last_name
    'emailConfirmed' => true,           // Automatically converts to email_confirmed
    'sendEmailToConfirmEmailAddress' => false,
]);

// Update user email
app('earhart')->updateUserEmail(
    $userId,
    'newemail@example.com',
    requireEmailConfirmation: true  // Automatically converts to require_email_confirmation
);

// Create access token
$token = app('earhart')->createAccessToken(
    $userId,
    durationInMinutes: 1440  // Automatically converts to duration_in_minutes
);

// Create magic link
$url = app('earhart')->createMagicLink(
    'user@example.com',
    redirectUrl: 'https://myapp.com/dashboard'  // Automatically converts to redirect_url
);
```

### Automatic Conversion

All conversions happen automatically:
- **Outgoing**: Your camelCase parameters → snake_case for PropelAuth API
- **Incoming**: PropelAuth's snake_case responses → camelCase for DTOs

You don't need to think about it - just use standard PHP naming conventions throughout your application.

## Testing Your Upgrade

### 1. Update Configuration
```bash
# Edit config/services.php and change redirect_url to redirect
```

### 2. Search for UserData Property Access
```bash
# Find all direct property access that needs updating
grep -r "user_id\|first_name\|last_name\|email_confirmed" app/
```

### 3. Update Property Names
Replace snake_case property names with camelCase equivalents as shown above.

### 4. Test Your Application
```bash
# Run your test suite
php artisan test

# Test key user flows manually:
# - User creation
# - User fetching
# - User updates
# - Organization operations
```

## Migration Checklist

- [ ] Update `config/services.php` - change `redirect_url` to `redirect`
- [ ] Search codebase for `$user->user_id` and similar snake_case property access
- [ ] Update UserData property access from snake_case to camelCase
- [ ] Run test suite to verify everything works
- [ ] Test user creation/update flows manually
- [ ] Deploy and monitor for any issues

## Rollback Plan

If you need to rollback:

```bash
composer require little-green-man/earhart:^1.6.0
```

Then revert your config changes:
```php
'redirect_url' => env('PROPELAUTH_CALLBACK_URL'),  // Back to old key
```

## Getting Help

If you encounter issues during the upgrade:

1. Check that `config/services.php` uses `redirect` not `redirect_url`
2. Verify all UserData property access uses camelCase
3. Review the [API documentation](docs/USING_PROPEL_API.md) for updated examples
4. Check the [CHANGELOG](CHANGELOG.md) for full list of changes

For bugs or questions, please open an issue on GitHub.