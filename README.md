<p align="center"><a href="https://github.com/little-green-man/earhart" target="_blank"><img src="docs/earhart.png" width="400"></a></p>

# Earhart

A Laravel package that makes working with PropelAuth easier.

Primarily it helps your app use PropelAuth's OAuth flow to log users in via Socialite, and [API](https://docs.propelauth.com/reference/api/getting-started) integration to retrieve and manage user and organisation data.

## Features

* **Authentication**: Socialite integration with easy route controllers for PropelAuth OAuth
* **Secure Webhook Handling that drives Events**: Verified webhooks fire events you can listen for in your application when e.g. user details change at PropelAuth
* **API Integration**: Built-in PropelAuth API client for querying data and to build functionality into your app seamlessly.
* **Configuration**: Flexible webhook configuration with cache invalidation rules (v1.4+)

## Installation

### 1. Install the package via composer:

```bash
composer require little-green-man/earhart
```

### 2. Configure PropelAuth

In your PropelAuth dashboard:
   * Configure general settings (e.g. password requirements)
   * Enable these User properties (default settings are fine):
     * Name
     * Profile Picture
     * Terms of Service
   * Configure organisation settings as needed
   * Set webhook URLs: Integration > Svix > tick all events and set test and prod URLs to `https://{your_app_url}/auth/webhooks`

### 3. Set environment variables:

```dotenv
PROPELAUTH_CLIENT_ID="tbc"
PROPELAUTH_CLIENT_SECRET="tbc"
PROPELAUTH_CALLBACK_URL=https://localhost/auth/callback
PROPELAUTH_AUTH_URL=https://0000000000.propelauthtest.com
PROPELAUTH_SVIX_SECRET="whsec_tbd"
PROPELAUTH_API_KEY="tbc"
PROPELAUTH_CACHE_ENABLED=false
PROPELAUTH_CACHE_TTL=60
```

### 4. Configure services

Add PropelAuth configuration to `config/services.php`:

```php
'propelauth' => [
    'client_id' => env('PROPELAUTH_CLIENT_ID'),
    'client_secret' => env('PROPELAUTH_CLIENT_SECRET'),
    'redirect' => env('PROPELAUTH_CALLBACK_URL'),
    'auth_url' => env('PROPELAUTH_AUTH_URL'),
    'svix_secret' => env('PROPELAUTH_SVIX_SECRET'),
    'api_key' => env('PROPELAUTH_API_KEY'),
],
```

Optionally, publish Earhart's config file to customise caching and other settings:

```bash
php artisan vendor:publish --provider="LittleGreenMan\Earhart\ServiceProvider" --tag="config"
```

This creates `config/earhart.php` where you can customise default values.

### 5. Update your database

Add these fields to your User model/migration:
* `propel_id` (string)
* `propel_access_token` (string)
* `propel_refresh_token` (string)
* `avatar` (string)
* `data` (json)
* Make `password` nullable or remove it
* Add any organisation/team models and relations you need

### 6. Add authentication routes

Add the webhook route to `routes/web.php`:

```php
Route::post('/auth/webhooks', AuthWebhookController::class)
    ->middleware(LittleGreenMan\Earhart\Middleware\VerifySvixWebhook::class)
    ->withoutMiddleware('web')
    ->name('auth.webhook');
```

Add the OAuth callback and logout routes to `routes/web.php`:

```php
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

Route::get('/auth/callback', function(Request $request){
    $propelUser = Socialite::driver('propelauth')->user();
    $rawUser = $propelUser->getRaw();

    $user = User::updateOrCreate([
        'propel_id' => $propelUser->id,
    ], [
        'name' => $rawUser['first_name'] . ' ' . $rawUser['last_name'],
        'email' => $propelUser->email,
        'propel_access_token' => $propelUser->token,
        'propel_refresh_token' => $propelUser->refreshToken,
        'avatar' => $rawUser['picture_url'],
        'data' => json_encode($rawUser),
        'email_verified_at' => $rawUser['email_confirmed'] ? now() : null,
    ]);
    Auth::login($user);

    // you might also want to update and sync `$user`'s organisations with `$rawUser['org_id_to_org_info']`;

    return redirect('/dashboard');
})->name('auth.callback');

Route::get('/auth/logout', function(Request $request){
    // IMPORTANT: Fetch the refresh token BEFORE calling Auth::logout()
    // Calling Auth::logout() first will clear the authenticated user, resulting in:
    // "Attempt to read property 'propel_refresh_token' on null"
    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
    ])->post(config('services.propelauth.auth_url') . '/api/backend/v1/logout', [
        'refresh_token' => Auth::user()->propel_refresh_token,
    ]);

    if ($response->failed()) {
        Log::debug('Failed to log out from PropelAuth', ['response' => $response->body()]);
    }

    Auth::logout();

    return redirect('/');
})->name('auth.logout');
```

### 7. Register the Socialite provider

Add this to the `boot` method of your `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\SocialiteWasCalled;

Event::listen(function (SocialiteWasCalled $event) {
    $event->extendSocialite('propelauth', \SocialiteProviders\PropelAuth\Provider::class);
});
```

### 8. Add authentication links to your views:

```blade
<a href="{{ route('auth.redirect') }}">Login</a>
<a href="{{ route('auth.logout') }}">Logout</a>
```

Optionally, add links to PropelAuth's hosted pages:

```blade
<a href="{{ route('auth.account') }}">Account</a>
<a href="{{ route('auth.settings') }}">Account Settings</a>
<a href="{{ route('auth.org.create') }}">Create Organisation</a>
<a href="{{ route('auth.org.members') }}">Organisation Members</a>
<a href="{{ route('auth.org.settings') }}">Organisation Settings</a>
```

Alternatively, implement these features in your own application using Earhart's API integrations.

## Refreshing User Tokens

PropelAuth access tokens expire after 30 minutes. To keep user tokens fresh and prevent authentication failures, you should set up a scheduled job to refresh them automatically.

See [docs/REFRESHING_USER_TOKENS.md](docs/REFRESHING_USER_TOKENS.md) for implementation details.

## Registered Routes

The following routes are registered automatically:

- `auth.redirect` - Redirect to PropelAuth login
- `auth.account` - PropelAuth account manager
- `auth.settings` - Account settings (requires org_id query parameter)
- `auth.org.create` - Create new organization
- `auth.org.members` - Organization members (requires org_id query parameter)
- `auth.org.settings` - Organization settings (requires org_id query parameter)

## Webhook Events

Create listeners in your application for these events:

### Organization Events

* `LittleGreenMan\Earhart\Events\PropelAuth\OrgCreated` - Organization created
* `LittleGreenMan\Earhart\Events\PropelAuth\OrgDeleted` - Organization deleted
* `LittleGreenMan\Earhart\Events\PropelAuth\OrgUpdated` - Organization updated
* `LittleGreenMan\Earhart\Events\PropelAuth\OrgApiKeyDeleted` - Organization API key deleted
* `LittleGreenMan\Earhart\Events\PropelAuth\OrgSamlRemoved` - SAML connection removed
* `LittleGreenMan\Earhart\Events\PropelAuth\OrgSamlSetup` - SAML setup initiated
* `LittleGreenMan\Earhart\Events\PropelAuth\OrgSamlWentLive` - SAML connection went live
* `LittleGreenMan\Earhart\Events\PropelAuth\OrgScimGroupCreated` - SCIM group created
* `LittleGreenMan\Earhart\Events\PropelAuth\OrgScimGroupDeleted` - SCIM group deleted
* `LittleGreenMan\Earhart\Events\PropelAuth\OrgScimGroupUpdated` - SCIM group updated
* `LittleGreenMan\Earhart\Events\PropelAuth\OrgScimKeyCreated` - SCIM key created
* `LittleGreenMan\Earhart\Events\PropelAuth\OrgScimKeyRevoked` - SCIM key revoked

### User Events

* `LittleGreenMan\Earhart\Events\PropelAuth\UserCreated` - User created
* `LittleGreenMan\Earhart\Events\PropelAuth\UserUpdated` - User profile updated
* `LittleGreenMan\Earhart\Events\PropelAuth\UserDeleted` - User deleted
* `LittleGreenMan\Earhart\Events\PropelAuth\UserEnabled` - User account enabled
* `LittleGreenMan\Earhart\Events\PropelAuth\UserDisabled` - User account disabled
* `LittleGreenMan\Earhart\Events\PropelAuth\UserLocked` - User account locked
* `LittleGreenMan\Earhart\Events\PropelAuth\UserAddedToOrg` - User added to organization
* `LittleGreenMan\Earhart\Events\PropelAuth\UserRemovedFromOrg` - User removed from organization
* `LittleGreenMan\Earhart\Events\PropelAuth\UserRoleChangedWithinOrg` - User role changed in organization
* `LittleGreenMan\Earhart\Events\PropelAuth\UserAddedToScimGroup` - User added to SCIM group
* `LittleGreenMan\Earhart\Events\PropelAuth\UserRemovedFromScimGroup` - User removed from SCIM group
* `LittleGreenMan\Earhart\Events\PropelAuth\UserDeletedPersonalApiKey` - User deleted personal API key
* `LittleGreenMan\Earhart\Events\PropelAuth\UserImpersonated` - User impersonated
* `LittleGreenMan\Earhart\Events\PropelAuth\UserInvitedToOrg` - User invited to organization
* `LittleGreenMan\Earhart\Events\PropelAuth\UserLoggedOut` - User logged out
* `LittleGreenMan\Earhart\Events\PropelAuth\UserLogin` - User logged in
* `LittleGreenMan\Earhart\Events\PropelAuth\UserSendMfaPhoneCode` - MFA phone code sent to user

### Example Listener

```php
namespace App\Listeners;

use App\Models\Organisation;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgCreated;

class PropelAuthOrgCreatedListener
{
    public function handle(OrgCreated $event): void
    {
        Organisation::create([
            'name' => $event->name,
            'propel_id' => $event->org_id,
        ]);
    }
}
```

## PropelAuth API Usage

Use the PropelAuth API within your application:

```php
$orgs = app('earhart')->getOrganisations();
$org = app('earhart')->getOrganisation('org_uuid');

// Returns array of UserData objects
$users = app('earhart')->getUsersInOrganisation('org_uuid');

// For pagination control, use the service directly:
$result = app('earhart')->organisations()->getOrganisationUsers('org_uuid', pageSize: 50);
// $result is PaginatedResult with totalItems, hasMoreResults, etc.
```

See [USING_PROPEL_API.md](docs/USING_PROPEL_API.md) for complete API documentation.

## Advanced Webhook Verification

The middleware shown in step 6 provides secure webhook verification out of the box.

For advanced webhook signature verification options (v1.4+), see [ADVANCED_WEBHOOK_VERIFICATION.md](docs/ADVANCED_WEBHOOK_VERIFICATION.md).

## Package Testing

Run the test suite:

```bash
./vendor/bin/pest
```

Run only webhook tests:

```bash
./vendor/bin/pest tests/Unit/Webhooks/ tests/Feature/Webhooks/
```

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Credits

- [Elliot](https://github.com/kurucu)
- [Yannick](https://github.com/ylynfatt)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
