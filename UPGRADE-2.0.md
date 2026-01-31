# Upgrading to v2.0

This guide will help you upgrade from Earhart v1.x to v2.0.

## Overview

Version 2.0 includes breaking changes that improve type safety, fix critical bugs, and streamline the API. Most applications will only need to make 2-3 small changes.

**Estimated upgrade time:** 10-15 minutes for most applications

## Breaking Changes Checklist

- [ ] Update `config/services.php` configuration key
- [ ] Update `getUsersInOrganisation()` calls (if used)
- [ ] Update `createMagicLink()` calls (if used)
- [ ] Update `UserData` property access (if accessing directly)

## Step-by-Step Migration

### 1. Update Configuration (Required for All)

**File:** `config/services.php`

**Change:**
```php
// OLD
'propelauth' => [
    'redirect_url' => env('PROPELAUTH_CALLBACK_URL'),
    // ...
],

// NEW
'propelauth' => [
    'redirect' => env('PROPELAUTH_CALLBACK_URL'),
    // ...
],
```

**Why:** Aligns with PropelAuth's API naming conventions.

---

### 2. Update getUsersInOrganisation() Calls

**If you use:** `getUsersInOrganisation()`

#### Option A: Switch to Array Return (Simple)

**OLD:**
```php
$usersData = $earhart->getUsersInOrganisation($orgId);
echo "Total: " . $usersData->total_users;
foreach ($usersData->users as $user) {
    echo $user->email;
}
```

**NEW:**
```php
$users = $earhart->getUsersInOrganisation($orgId);
echo "Total: " . count($users);
foreach ($users as $user) {
    echo $user->email;
}
```

#### Option B: Use Service Method (For Pagination)

**If you need pagination metadata:**
```php
$result = $earhart->organisations()->getOrganisationUsers($orgId, pageSize: 50);

echo "Total: " . $result->totalItems;
echo "Page: " . $result->currentPage;
echo "Has more: " . ($result->hasMoreResults ? 'yes' : 'no');

foreach ($result->items as $user) {
    echo $user->email;
}

// Get next page if available
if ($result->hasMoreResults) {
    $nextPage = $result->nextPage();
}
```

---

### 3. Update createMagicLink() Calls

**If you use:** `createMagicLink()`

**OLD:**
```php
$link = $earhart->createMagicLink(
    userId: $user->userId,
    redirectUrl: 'https://example.com/dashboard',
    expiresInHours: 24
);
```

**NEW:**
```php
$link = $earhart->createMagicLink(
    email: $user->email,  // Changed from userId to email
    redirectUrl: 'https://example.com/dashboard',
    expiresInHours: 24,
    createIfNotExists: false  // New parameter
);
```

**Why:** PropelAuth API uses email for magic link generation, not user ID.

---

### 4. Update UserData Property Access (If Applicable)

**If you access UserData properties directly** (most applications don't):

**OLD:**
```php
$user = $earhart->getUser($userId);
echo $user->first_name;
echo $user->last_name;
echo $user->email_confirmed;
echo $user->created_at->format('Y-m-d');
```

**NEW:**
```php
$user = $earhart->getUser($userId);
echo $user->firstName;        // camelCase
echo $user->lastName;         // camelCase
echo $user->emailConfirmed;   // camelCase
echo $user->createdAt->format('Y-m-d');  // camelCase
```

**Complete property mapping:**

| Old (v1.x)                    | New (v2.0)                |
|-------------------------------|---------------------------|
| `user_id`                     | `userId`                  |
| `email_confirmed`             | `emailConfirmed`          |
| `first_name`                  | `firstName`               |
| `last_name`                   | `lastName`                |
| `picture_url`                 | `pictureUrl`              |
| `has_password`                | `hasPassword`             |
| `mfa_enabled`                 | `mfaEnabled`              |
| `can_create_orgs`             | `canCreateOrgs`           |
| `created_at`                  | `createdAt`               |
| `last_active_at`              | `lastActiveAt`            |
| `update_password_required`    | `updatePasswordRequired`  |

**Note:** If you only use API methods like `createUser()`, `updateUser()`, etc., you likely don't need to change anything here. The API methods already accept camelCase parameters.

---

## What's Improved in v2.0

### Automatic Case Conversion

You can now use PHP's camelCase conventions throughout:

```php
// This now works correctly:
$user = $earhart->createUser(
    email: 'user@example.com',
    firstName: 'John',           // camelCase - automatically converted
    lastName: 'Doe',             // camelCase - automatically converted
    emailConfirmed: true,        // camelCase - automatically converted
);

// Access properties in camelCase:
echo $user->firstName;           // "John"
echo $user->emailConfirmed;      // true
```

Earhart automatically converts between PHP's camelCase and PropelAuth's snake_case API.

### Better Type Safety

Methods now return properly typed objects:

```php
// getUsersInOrganisation() returns array<UserData>
$users = $earhart->getUsersInOrganisation($orgId);
// IDE autocomplete works correctly now

// queryOrganisations() items are already OrganisationData objects
$result = $earhart->organisations()->queryOrganisations();
foreach ($result->items as $org) {
    echo $org->displayName;  // Proper type hints
}
```

### Bug Fixes

- Fixed double-wrapping bugs in `getUsersInOrganisation()` and `getOrganisations()`
- Fixed systematic parameter naming issues across 20+ API methods
- Improved error messages for type mismatches

---

## Testing Your Upgrade

After upgrading, test these core flows:

### 1. User Creation
```php
$userId = $earhart->createUser(
    email: 'test@example.com',
    password: 'SecurePass123!',
    firstName: 'Test',
    lastName: 'User'
);
$user = $earhart->getUser($userId);
assert($user->firstName === 'Test');
```

### 2. Organisation Users
```php
$users = $earhart->getUsersInOrganisation($orgId);
assert(is_array($users));
assert($users[0] instanceof \LittleGreenMan\Earhart\PropelAuth\UserData);
```

### 3. Magic Links (if used)
```php
$link = $earhart->createMagicLink(
    email: 'user@example.com',
    redirectUrl: 'https://example.com'
);
assert(str_starts_with($link, 'https://'));
```

---

## Getting Help

If you encounter issues during the upgrade:

1. **Check the CHANGELOG:** See `CHANGELOG.md` for detailed technical notes
2. **Review API docs:** See `docs/USING_PROPEL_API.md` for updated examples
3. **Run tests:** Ensure your application tests pass after changes
4. **GitHub Issues:** Report bugs or ask questions at the package repository

---

## Reverting (If Needed)

If you need to temporarily revert to v1.x:

```bash
composer require little-green-man/earhart:^1.6
```

Then undo the configuration changes above.

---

**Congratulations!** You're now running Earhart v2.0 with improved type safety and automatic case conversion.
