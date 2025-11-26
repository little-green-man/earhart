# Earhart

A Laravel package that makes working with PropelAuth easier.

Including:
* socialite and easy route controllers already set up; other code examples below.
* a controller to handle webhook requests from PropelAuth, which fires events you can listen for in your application.
* And of course this Readme which guides you through the process in one place.

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

```php
composer require little-green-man/earhart
```

### 3. Set your environment variables:

```dotenv
PROPELAUTH_CLIENT_ID="tbc"
PROPELAUTH_CLIENT_SECRET="tbc"
PROPELAUTH_CALLBACK_URL=https:///localhost/auth/callback
PROPELAUTH_AUTH_URL=https://0000000000.propelauthtest.com
PROPELAUTH_SVIX_SECRET="whsec_tbd"
PROPELAUTH_API_KEY="tbc"
```

### 4. Prepare your database:

* Add string `propel_id` to your User model.
* Add string `propel_access_token` to your User model.
* Add string `propel_refresh_token` to your User model.
* Add string `avatar` to your User model.
* Add json `data` to your User model.
* Add any Teams models/relations you need

### 5. Add (and amemd as required) the following to your web.php routes file:

```php
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

Route::post('/auth/webhooks', \LittleGreenMan\Earhart\Controllers\AuthWebhookController::class)
    ->withMiddleware(VerifySvixWebhook::class)
    ->withoutMiddleware('web')
    ->name('auth.webhook');

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
    Auth::logout();

    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
    ])->post(config('services.propelauth.auth_url') . '/api/backend/v1/logout', [
        'refresh_token' => Auth::user()->propel_refresh_token,
    ]);

    if ($response->failed()) {
        Log::debug('Failed to log out from PropelAuth', ['response' => $response->body()]);
    }

    return redirect('/');
})->name('auth.logout');
```

Noting that the following routes are registered for you:

- auth.redirect
- auth.account
- auth.settings
- auth.org.create
- auth.org.members
- auth.org.settings

### 6. Add the Socialite event listener: 

Add it to the `boot` method of your `AppServiceProvider`.

```php
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\SocialiteWasCalled;

Event::listen(function (SocialiteWasCalled $event) {
    $event->extendSocialite('propelauth', \SocialiteProviders\PropelAuth\Provider::class);
});
```

### 7. Add login / logout buttons to your views.

```bladehtml
<a href="{{ route('auth.redirect') }}">Login</a>
<a href="{{ route('auth.logout') }}">Logout</a>
```

And optionally the following, which redirect to the relevant sections in PropelAuth:

```bladehtml
<a href="{{ route('auth.account') }}">Account</a>
<a href="{{ route('auth.settings') }}">Account Settings</a>
<a href="{{ route('auth.org.create') }}">Create Organisation</a>
<a href="{{ route('auth.org.members') }}">Organisation Members</a>
<a href="{{ route('auth.org.settings') }}">Organisation Settings</a>
```

### 8. Optionally, set up Listeners in your app for the following events:

* LittleGreenMan\Earhart\Events\PropelAuth\OrgCreated
* LittleGreenMan\Earhart\Events\PropelAuth\OrgDeleted
* LittleGreenMan\Earhart\Events\PropelAuth\OrgUpdated
* LittleGreenMan\Earhart\Events\PropelAuth\UserAddedToOrg
* LittleGreenMan\Earhart\Events\PropelAuth\UserCreated
* LittleGreenMan\Earhart\Events\PropelAuth\UserDeleted
* LittleGreenMan\Earhart\Events\PropelAuth\UserDisabled
* LittleGreenMan\Earhart\Events\PropelAuth\UserEnabled
* LittleGreenMan\Earhart\Events\PropelAuth\UserLocked
* LittleGreenMan\Earhart\Events\PropelAuth\UserRemovedFromOrg
* LittleGreenMan\Earhart\Events\PropelAuth\UserRoleChangedWithinOrg
* LittleGreenMan\Earhart\Events\PropelAuth\UserUpdated

One example is shown below:

```php
namespace App\Listeners;

use App\Models\Organisation;
use LittleGreenMan\Earhart\Events\PropelAuth\OrgCreated;

class PropelAuthOrgCreatedListener
{
    public function __construct() {}

    public function handle(OrgCreated $event): void
    {
        // If you inspect $event, you'll see we populate the data for you
        
        Organisation::create([
            'name' => $event->name,
            'propel_id' => $event->org_id,
        ]);
    }
}
```

## PropelAuth API Usage

Note: This is still a work in progress.

Within your app, you can use the following code to interrogate the PropelAuth API, for example in response to
an event:

```php
$orgs = app('earhart')->getOrganisations();
$org = app('earhart')->getOrganisation('org_uuid');
$users = app('earhart')->getUsersInOrganisation('org_uuid');
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Elliot](https://github.com/kurucu)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
