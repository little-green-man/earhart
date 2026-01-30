# Earhart

A Laravel package that makes working with PropelAuth easier.

## Features

* **Authentication**: Socialite integration with easy route controllers for PropelAuth OAuth
* **Webhooks**: Webhook handler that fires events you can listen for in your application
* **Signature Verification**: Secure webhook signature verification using Svix (v1.4+)
* **Configuration**: Flexible webhook configuration with cache invalidation rules (v1.4+)
* **Events**: Comprehensive event system for all PropelAuth events
* **API Integration**: Built-in PropelAuth API client for querying organizations and users

## Installation

### 1. Configure PropelAuth as follows:

   * General settings e.g. password
   * Enable the following User properties (default settings are ok)
     * Name
     * Profile Picture
     * Terms of Service
   * Org settings
   * Webhook settings
     * Integration > Svix > check all and set test and prod URLs to `https://{your_dev/prod_app_url}/auth/webhooks`

### 2. Install the package via composer:

```bash
composer require little-green-man/earhart
```

### 3. Set your environment variables:

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

### 4. Configure the package:

After publishing the config file (optional but recommended), Earhart automatically merges environment variables into the `config/earhart.php` configuration.

**Environment Variable Mapping:**

| Environment Variable | Config Key | Purpose |
|---|---|---|
| `PROPELAUTH_CLIENT_ID` | `earhart.client_id` | OAuth2 Client ID |
| `PROPELAUTH_CLIENT_SECRET` | `earhart.client_secret` | OAuth2 Client Secret |
| `PROPELAUTH_AUTH_URL` | `earhart.auth_url` | PropelAuth Auth URL (e.g., `https://your-org.propelauthtest.com`) |
| `PROPELAUTH_CALLBACK_URL` | `earhart.redirect_url` | OAuth2 Callback URL |
| `PROPELAUTH_API_KEY` | `earhart.api_key` | PropelAuth API Key for backend requests |
| `PROPELAUTH_SVIX_SECRET` | `earhart.svix_secret` | Svix Webhook Signing Secret |
| `PROPELAUTH_CACHE_ENABLED` | `earhart.cache.enabled` | Enable caching (boolean) |
| `PROPELAUTH_CACHE_TTL` | `earhart.cache.ttl_minutes` | Cache TTL in minutes (default: 60) |

**Publishing the Config File (Optional):**

To customize configuration, publish the config file:

```bash
php artisan vendor:publish --provider="LittleGreenMan\Earhart\ServiceProvider" --tag="config"
```

This creates `config/earhart.php` where you can customize default values.

### 5. Prepare your database:

* Add string `propel_id` to your User model.
* Add string `propel_access_token` to your User model.
* Add string `propel_refresh_token` to your User model.
* Remove or make nullable the `password` field from your User model.
* Add string `avatar` to your User model.
* Add json `data` to your User model.
* Add any Teams models/relations you need

### 6. Add the webhook route to your web.php routes file:

```php
Route::post('/auth/webhooks', \LittleGreenMan\Earhart\Controllers\AuthWebhookController::class)
    ->withoutMiddleware('web')
    ->name('auth.webhook');
```

### 7. Set up authentication routes:

Add the following to your web.php routes file:

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

### 8. Add the Socialite event listener:

Add it to the `boot` method of your `AppServiceProvider`.

```php
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\SocialiteWasCalled;

Event::listen(function (SocialiteWasCalled $event) {
    $event->extendSocialite('propelauth', \SocialiteProviders\PropelAuth\Provider::class);
});
```

### 8. Add login / logout buttons to your views:

```blade
<a href="{{ route('auth.redirect') }}">Login</a>
<a href="{{ route('auth.logout') }}">Logout</a>
```

And optionally the following, which redirect to the relevant sections in PropelAuth:

```blade
<a href="{{ route('auth.account') }}">Account</a>
<a href="{{ route('auth.settings') }}">Account Settings</a>
<a href="{{ route('auth.org.create') }}">Create Organisation</a>
<a href="{{ route('auth.org.members') }}">Organisation Members</a>
<a href="{{ route('auth.org.settings') }}">Organisation Settings</a>
```

## Refreshing User Tokens

PropelAuth access tokens expire after 30 minutes. To keep user tokens fresh and prevent authentication failures, you should set up a scheduled job to refresh them automatically.

**See [REFRESHING_USER_TOKENS.md](REFRESHING_USER_TOKENS.md) for a complete, production-ready example job and setup instructions.**

The guide includes:
- A ready-to-use `RefreshUserTokenJob` that you can customize for your implementation
- Instructions for adding the job to your Laravel scheduler
- Examples for different token storage approaches
- Error handling and monitoring tips
- Security best practices
- Troubleshooting guide

## Registered Routes

The following routes are registered automatically:

- `auth.redirect` - Redirect to PropelAuth login
- `auth.account` - PropelAuth account manager
- `auth.settings` - Account settings (requires org_id query parameter)
- `auth.org.create` - Create new organization
- `auth.org.members` - Organization members (requires org_id query parameter)
- `auth.org.settings` - Organization settings (requires org_id query parameter)

## Webhook Events

Set up listeners in your app for the following events:

#### Organization Events

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

#### User Events

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

## Webhook Signature Verification (v1.4+)

Starting with v1.4, Earhart includes secure webhook signature verification using Svix.

### Basic Setup

Verify webhook signatures to ensure webhooks come from PropelAuth and haven't been tampered with:

```php
use LittleGreenMan\Earhart\Webhooks\WebhookSignatureVerifier;
use LittleGreenMan\Earhart\Webhooks\WebhookEventParser;
use Illuminate\Http\Request;

Route::post('/auth/webhooks', function(Request $request) {
    $verifier = new WebhookSignatureVerifier(
        config('services.propelauth.svix_secret')
    );

    try {
        // Verify the webhook signature
        $payload = $verifier->verify(
            $request->getContent(),
            $request->headers->all()
        );

        // Parse and dispatch the event
        $parser = new WebhookEventParser();
        $event = $parser->parse($payload);

        if ($event) {
            event($event);
        }

        return response()->json(['status' => 'success']);
    } catch (\Svix\Exception\WebhookVerificationException $e) {
        \Log::warning('Webhook verification failed', ['error' => $e->getMessage()]);
        return response()->json(['error' => 'Unauthorized'], 401);
    }
})->withoutMiddleware('web');
```

### Timestamp Validation

Prevent replay attacks by validating webhook timestamps:

```php
$verifier = new WebhookSignatureVerifier($signingSecret);

// Check if timestamp is within 5-minute tolerance (default)
if (!$verifier->isTimestampValid($timestamp)) {
    abort(400, 'Webhook timestamp too old');
}

// Use custom tolerance (e.g., 10 minutes)
if (!$verifier->isTimestampValid($timestamp, 600)) {
    abort(400, 'Webhook timestamp too old');
}
```

### Configuration

Use `WebhookConfig` for fine-grained control over webhook behavior:

```php
use LittleGreenMan\Earhart\Webhooks\WebhookConfig;

$config = WebhookConfig::default()
    ->setVerifySignatures(true)
    ->withSigningSecret(config('services.propelauth.svix_secret'))
    ->setTimestampTolerance(600)  // 10 minutes
    ->setInvalidateUserListCache(true)
    ->removeUserCacheInvalidationEvent('user.locked');
```

### Configuration from Array

Load configuration from your Laravel config file:

```php
$config = WebhookConfig::fromArray([
    'verify_signatures' => true,
    'signing_secret' => env('PROPELAUTH_WEBHOOK_SECRET'),
    'timestamp_tolerance_seconds' => 600,
    'invalidate_user_list_cache' => false,
]);
```

### Security Best Practices

1. **Always verify signatures in production**
   ```php
   if (config('app.env') === 'production') {
       $payload = $verifier->verify($requestBody, $headers);
   }
   ```

2. **Use environment variables for secrets**
   ```php
   $secret = config('services.propelauth.svix_secret');
   ```

3. **Validate timestamps to prevent replay attacks**
   ```php
   if (!$verifier->isTimestampValid($timestamp)) {
       abort(400);
   }
   ```

4. **Handle errors gracefully**
   ```php
   try {
       $payload = $verifier->verify($requestBody, $headers);
   } catch (\Svix\Exception\WebhookVerificationException $e) {
       \Log::error('Webhook verification failed', ['error' => $e->getMessage()]);
       return response()->json(['error' => 'Unauthorized'], 401);
   }
   ```

5. **Don't log sensitive data**
   ```php
   $masked = $verifier->getMaskedSecret();  // "whsec_lo**********************"
   \Log::info('Webhook processed', ['secret' => $masked]);
   ```

## PropelAuth API Usage

Within your app, you can use the following code to interrogate the PropelAuth API, for example in response to an event:

```php
$orgs = app('earhart')->getOrganisations();
$org = app('earhart')->getOrganisation('org_uuid');
$users = app('earhart')->getUsersInOrganisation('org_uuid');
```

## Testing

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

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Elliot](https://github.com/kurucu)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.