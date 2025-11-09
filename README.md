# Earhart

A Laravel package that makes working with PropelAuth easier.

## Installation

1. Configure PropelAuth as follows:

* General settings e.g. password
* Enable the following User properties (default settings are ok)
  * Name
  * Profile Picture
  * Terms of Service
* Org settings
* Webhook settings
  * Integration > Svix > check all and set test and prod URLs to `https://{your_dev/prod_app_url}/auth/webhooks`

2. Install the package via composer:

```php
composer require little-green-man/earhart
```

3. Set your environment variables:

```dotenv
PROPELAUTH_CLIENT_ID="tbc"
PROPELAUTH_CLIENT_SECRET="tbc"
PROPELAUTH_CALLBACK_URL=https:///localhost/auth/callback
PROPELAUTH_AUTH_URL=https://0000000000.propelauthtest.com
PROPELAUTH_SVIX_SECRET="whsec_tbd"
```

4. Prepare your database:
    * Add string `propel_id` to your User model.
    * Add string `propel_access_token` to your User model.
    * Add string `propel_refresh_token` to your User model.
    * Add string `avatar` to your User model.
    * Add json `data` to your User model.
    * Add any Teams models/relations you need


5. Add (and amemd as required) the following to your web.php routes file:

```php
use LittleGreenMan\Earhart\Controllers\AuthRedirectController;
use LittleGreenMan\Earhart\Controllers\AuthWebhookController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

Route::get('/auth/redirect', AuthRedirectController::class)->name('auth.redirect');
Route::post('/auth/webhooks', AuthWebhookController::class)
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

6. Add the Socialite event listener to the `boot` method of your `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\SocialiteWasCalled;

Event::listen(function (SocialiteWasCalled $event) {
    $event->extendSocialite('propelauth', \SocialiteProviders\PropelAuth\Provider::class);
});
```

7. Add login and logout buttons to your views.

```bladehtml
<a href="{{ route('auth.redirect') }}">Login</a>
<a href="{{ route('auth.logout') }}">Logout</a>
```

8. (Optional) set up Listeners for the following events:
   * LittleGreenMan\Earhart\Events\PropelAuth\OrgCreated

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

## Usage

ddd

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [:author_name](https://github.com/:author_username)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
