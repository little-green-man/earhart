# Refreshing User Tokens

PropelAuth access tokens expire after 30 minutes. This guide shows you how to set up a job to automatically refresh user tokens and keep their information up to date.

## Why You Need This

- **Access tokens expire**: PropelAuth access tokens are valid for 30 minutes
- **Prevent authentication failures**: Expired tokens will return 401 errors
- **Keep data fresh**: Refresh user information regularly to sync changes from PropelAuth

## How Token Refresh Works

1. **Try to fetch fresh user data** with the current access token
2. **If token is expired (401 error)**, use the refresh token to obtain new tokens
3. **Save new tokens and updated data** back to your User model
4. **Schedule this regularly** to keep tokens fresh

## Understanding the Challenge

The Earhart package has no knowledge of how you store tokens on your User model. Different applications store them differently:

- Some store tokens directly as `propel_access_token` and `propel_refresh_token` columns
- Others store them in a separate `propel_tokens` table
- Some use session storage or cache
- Many customize column names and data structures

For this reason, **this guide provides an example job that you must customize for your specific implementation**.

## The Example Job

Below is a complete, production-ready job that you can copy and adapt for your application. Create this file at `app/Jobs/RefreshUserTokenJob.php`:

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class RefreshUserTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Try up to 3 times before giving up
    public int $tries = 3;

    // Wait 60 seconds between retry attempts
    public int $backoff = 60;

    /**
     * Create a new job instance.
     *
     * This job is responsible for refreshing a user's PropelAuth tokens and user information.
     * It handles the case where the access token has expired and needs to be refreshed.
     *
     * @param User $user The user model instance with stored tokens
     */
    public function __construct(
        public User $user,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Skip if user has no refresh token stored
        if (!$this->user->propel_refresh_token) {
            Log::warning('User has no refresh token', [
                'user_id' => $this->user->id,
            ]);
            return;
        }

        try {
            // STEP 1: Try to fetch fresh user data with current access token
            $freshUser = $this->fetchFreshUserData();

            if ($freshUser) {
                // Token is still valid - save updated user data
                $this->updateUserData($freshUser);
                Log::info('User token still valid, data refreshed', [
                    'user_id' => $this->user->id,
                ]);
                return;
            }

            // STEP 2: Token is expired (401), attempt to refresh it
            $newTokens = $this->refreshTokens();

            if (!$newTokens) {
                // Refresh failed - user needs to re-authenticate
                Log::warning('Failed to refresh user tokens', [
                    'user_id' => $this->user->id,
                    'email' => $this->user->email,
                ]);
                $this->handleFailedRefresh();
                return;
            }

            // STEP 3: Save new tokens to the User model
            // ⚠️  IMPORTANT: Customize this section based on how YOUR app stores tokens
            // The example below assumes tokens are stored as database columns.
            // If you store tokens differently, see "Customizing for Your Implementation" below.
            $this->user->update([
                'propel_access_token' => $newTokens['access_token'],
                'propel_refresh_token' => $newTokens['refresh_token'],
            ]);

            // STEP 4: Fetch fresh user data with the new token
            $freshUser = $this->fetchFreshUserData();

            if ($freshUser) {
                $this->updateUserData($freshUser);
            }

            Log::info('User tokens refreshed successfully', [
                'user_id' => $this->user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Error refreshing user tokens', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Attempt to fetch fresh user data using current access token.
     *
     * @return array|null User data array if successful, null if token is expired (401)
     */
    private function fetchFreshUserData(): ?array
    {
        try {
            $propelUser = Socialite::driver('propelauth')
                ->userFromToken($this->user->propel_access_token);

            return $propelUser->getRaw();
        } catch (\Exception $e) {
            // Check if error is due to expired token (401 Unauthorized)
            if (str_contains($e->getMessage(), '401') || 
                str_contains($e->getMessage(), 'Unauthorized')) {
                return null; // Token is expired, needs refresh
            }

            // Other errors should be logged and re-thrown
            Log::warning('Error fetching fresh user data', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Use the refresh token to obtain new access and refresh tokens.
     *
     * @return array|null Array with 'access_token' and 'refresh_token' keys, or null on failure
     */
    private function refreshTokens(): ?array
    {
        try {
            $response = Http::asForm()->post(
                config('services.propelauth.auth_url') . '/propelauth/oauth/token',
                [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $this->user->propel_refresh_token,
                    'client_id' => config('services.propelauth.client_id'),
                ],
            );

            if (!$response->ok()) {
                Log::warning('Token refresh request failed', [
                    'user_id' => $this->user->id,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();

            // Ensure response has the required tokens
            if (!isset($data['access_token']) || !isset($data['refresh_token'])) {
                Log::warning('Refresh token response missing token fields', [
                    'user_id' => $this->user->id,
                ]);
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Exception during token refresh', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Update the User model with fresh data from PropelAuth.
     *
     * ⚠️  IMPORTANT: Customize this method to match your User model's column names and structure.
     * See "Customizing for Your Implementation" section below for examples.
     *
     * @param array $userData The raw user data from PropelAuth
     */
    private function updateUserData(array $userData): void
    {
        // Basic fields that match the README setup
        $updateData = [
            // Update name from PropelAuth
            'name' => trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')),

            // Update avatar if available
            'avatar' => $userData['picture_url'] ?? null,

            // Store the full user data as JSON for later reference
            'data' => json_encode($userData),

            // Mark email as verified if PropelAuth confirms it
            'email_verified_at' => ($userData['email_confirmed'] ?? false) ? now() : null,
        ];

        // ⚠️  CUSTOMIZATION: Add any of these based on your User model schema:

        // If you track whether user is enabled/disabled:
        // 'is_active' => $userData['enabled'] ?? true,

        // If you track whether user has a password set:
        // 'has_password' => $userData['has_password'] ?? false,

        // If you track when tokens were last synced:
        // 'propel_last_sync' => now(),

        // If you have custom columns for other PropelAuth fields:
        // 'propel_username' => $userData['username'] ?? null,
        // 'propel_mfa_enabled' => $userData['mfa_enabled'] ?? false,

        $this->user->update($updateData);

        // ⚠️  OPTIONAL: Sync organizations separately (complex logic)
        // Uncomment the line below if you want to sync organization membership
        // $this->syncOrganizations($userData);
    }

    /**
     * Handle the case where token refresh failed permanently.
     *
     * The user's refresh token is no longer valid, which typically means:
     * - They logged out of PropelAuth
     * - Their account was deleted
     * - The refresh token expired
     *
     * ⚠️  CUSTOMIZE this method based on your security requirements.
     */
    private function handleFailedRefresh(): void
    {
        // Always clear invalid tokens so they don't get used again
        $this->user->update([
            'propel_access_token' => null,
            'propel_refresh_token' => null,
        ]);

        // ⚠️  CUSTOMIZATION: Choose one or more actions based on your needs:

        // Option 1: Mark user as requiring re-authentication
        // $this->user->update(['requires_reauthentication' => true]);

        // Option 2: Send email notification to user
        // Mail::to($this->user->email)->send(new ReauthenticationRequired($this->user));

        // Option 3: Temporarily disable the account
        // $this->user->update(['is_active' => false]);

        // Option 4: Log to external monitoring service
        // Sentry::captureMessage('User token refresh failed', 'warning');
    }

    /**
     * OPTIONAL: Sync user's organization membership from PropelAuth.
     *
     * This is optional because organization syncing logic varies significantly
     * between applications. Only implement if you need organization data synchronized.
     *
     * @param array $userData The raw user data from PropelAuth
     */
    private function syncOrganizations(array $userData): void
    {
        // This is a basic example - your implementation may differ significantly
        // based on how you model organizations and team relationships.

        $orgData = $userData['org_id_to_org_info'] ?? [];

        // Example: If using a many-to-many pivot table
        // $this->user->organizations()->sync(
        //     collect($orgData)->mapWithKeys(fn ($org, $id) => [
        //         $id => ['user_role' => $org['user_role']]
        //     ])->toArray()
        // );
    }
}
```

## Using the Job

### Step 1: Add to Your Scheduler

Edit `app/Console/Kernel.php` to schedule the job:

```php
<?php

namespace App\Console;

use App\Jobs\RefreshUserTokenJob;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Refresh tokens for all users every 30 minutes
        $schedule->call(function () {
            User::whereNotNull('propel_refresh_token')->get()->each(
                fn (User $user) => RefreshUserTokenJob::dispatch($user)
            );
        })->everyThirtyMinutes();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
```

### Step 2: Customize for Your Implementation

**Read through the job code and update:**

1. **Token storage** (see "Customizing Token Storage" below)
2. **User data fields** (see "Customizing User Data Fields" below)
3. **Error handling** (see "Customizing Error Handling" below)

## Customizing for Your Implementation

### Customizing Token Storage

The example job assumes tokens are stored as database columns `propel_access_token` and `propel_refresh_token`. If you store them differently:

**If tokens are in a separate table:**

```php
// In RefreshUserTokenJob::handle(), replace the token-saving section:
$latestToken = $this->user->propelTokens()->latest()->first();
$accessToken = $latestToken?->access_token;

// After refresh:
$this->user->propelTokens()->create([
    'access_token' => $newTokens['access_token'],
    'refresh_token' => $newTokens['refresh_token'],
    'expires_at' => now()->addMinutes(30),
]);
```

**If tokens are stored in cache/Redis:**

```php
// In RefreshUserTokenJob::handle(), replace:
$tokens = cache()->get("user.{$this->user->id}.tokens", []);
$accessToken = $tokens['access_token'] ?? null;

// After refresh:
cache()->put("user.{$this->user->id}.tokens", [
    'access_token' => $newTokens['access_token'],
    'refresh_token' => $newTokens['refresh_token'],
], 30 * 60);
```

**If tokens use different column names:**

```php
// In RefreshUserTokenJob::handle(), replace:
$this->user->update([
    'propelauth_access_token' => $newTokens['access_token'],
    'propelauth_refresh_token' => $newTokens['refresh_token'],
]);
```

### Customizing User Data Fields

If your User model uses different column names, update the `updateUserData()` method:

```php
private function updateUserData(array $userData): void
{
    $this->user->update([
        'full_name' => $userData['first_name'] . ' ' . $userData['last_name'],
        'profile_image_url' => $userData['picture_url'],
        'raw_propel_data' => json_encode($userData),
        'last_synced_from_propel' => now(),
    ]);
}
```

### Customizing Error Handling

Update `handleFailedRefresh()` based on your security model:

```php
private function handleFailedRefresh(): void
{
    $this->user->update([
        'propel_access_token' => null,
        'propel_refresh_token' => null,
    ]);

    // Send alert to support team
    Mail::to('support@example.com')->send(
        new TokenRefreshFailed($this->user)
    );

    // Log for monitoring
    Log::channel('security')->warning(
        'User token refresh failed - may indicate account compromise',
        ['user_id' => $this->user->id, 'email' => $this->user->email]
    );
}
```

## Adjusting Refresh Frequency

Depending on your application's needs, adjust how often tokens are refreshed:

```php
// Every 15 minutes (more conservative, safer)
->everyFifteenMinutes();

// Every 30 minutes (default, good balance)
->everyThirtyMinutes();

// Every hour (less frequent, some token expiries possible)
->hourly();

// Every 6 hours (minimal server load)
->everyHours(6);

// Only during specific times
->weekdays()->between('9:00', '17:00');

// Only refresh active users (to reduce load)
User::where('last_activity_at', '>', now()->subDays(7))
    ->whereNotNull('propel_refresh_token')
    ->get()
    ->each(fn (User $user) => RefreshUserTokenJob::dispatch($user));
```

## Monitoring and Debugging

### View Failed Jobs

```bash
# List all failed jobs
php artisan queue:failed

# Show details of a failed job
php artisan queue:failed {job_id}

# Retry a failed job
php artisan queue:retry {job_id}

# Flush all failed jobs
php artisan queue:flush
```

### Check Application Logs

```bash
# View recent logs
tail -f storage/logs/laravel.log | grep "RefreshUserTokenJob"

# Filter for failures only
grep "Failed to refresh" storage/logs/laravel.log
```

### Track Sync Success with Database Logging

Create a migration to track token refresh attempts:

```php
Schema::create('propel_token_syncs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->boolean('success');
    $table->string('failure_reason')->nullable();
    $table->timestamp('synced_at');
    $table->timestamps();
});
```

Then log in your job:

```php
// In RefreshUserTokenJob::handle()
use App\Models\PropelTokenSync;

PropelTokenSync::create([
    'user_id' => $this->user->id,
    'success' => true,
    'synced_at' => now(),
]);
```

## Security Best Practices

1. **Never log tokens**: Don't include access or refresh tokens in logs or error messages
2. **Use environment variables**: All credentials must be in `.env`, never hardcoded
3. **HTTPS only**: All communication with PropelAuth uses HTTPS
4. **Validate certificates**: Don't disable SSL verification
5. **Rate limiting**: Be aware of PropelAuth API rate limits
6. **Queue isolation**: Consider running sensitive operations on separate queue workers

```php
// ✅ Good: Safe logging
Log::info('Token refreshed', ['user_id' => $user->id]);

// ❌ Bad: Never do this
Log::info('Refresh', ['token' => $this->user->propel_access_token]);
```

## Troubleshooting

### "Refresh token invalid or expired"

The user's refresh token is no longer valid. This happens when:
- User logged out of PropelAuth
- Their account was deleted
- Refresh token naturally expired

**Solution:** User must log in again

```php
$user->update([
    'propel_access_token' => null,
    'propel_refresh_token' => null,
]);
// Next page load: redirect to login
```

### "Still getting 401 errors after refresh"

Check:
1. Is the job actually running? Check your scheduler and queue workers
2. Are new tokens actually being saved to the database?
3. Is there a race condition (multiple refresh attempts simultaneously)?
4. Is old code still using cached tokens?

**Debug by adding logs:**

```php
Log::info('Before refresh', [
    'user_id' => $this->user->id,
    'has_token' => !empty($this->user->propel_access_token),
]);

// ... refresh code ...

$this->user->refresh();
Log::info('After refresh', [
    'user_id' => $this->user->id,
    'has_new_token' => !empty($this->user->propel_access_token),
]);
```

### "Queue jobs are backing up"

If token refresh jobs are accumulating in your queue:

1. **Reduce frequency**: Refresh every 60 minutes instead of 30
2. **Filter users**: Only refresh active users with recent activity
3. **Increase workers**: Run more queue workers
4. **Use sync mode for testing**: Use `dispatchSync()` during development

```php
// More efficient: only refresh active users
$schedule->call(function () {
    User::where('last_activity_at', '>', now()->subDays(7))
        ->whereNotNull('propel_refresh_token')
        ->chunk(50, function ($users) {
            $users->each(fn (User $user) => RefreshUserTokenJob::dispatch($user));
        });
})->hourly();
```

### PropelAuth API is down or returning errors

Your job will fail and retry. Update your error handling:

```php
} catch (\Exception $e) {
    // Don't retry 4xx errors (client errors - our problem)
    if (str_contains($e->getMessage(), '4')) {
        $this->fail($e);
    }
    
    // Retry on 5xx errors (server errors - their problem)
    throw $e;
}
```

## Testing the Job

Here's a basic test to verify your job works:

```php
<?php

namespace Tests\Feature;

use App\Jobs\RefreshUserTokenJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RefreshUserTokenJobTest extends TestCase
{
    public function test_job_handles_user_without_refresh_token()
    {
        $user = User::factory()->create([
            'propel_refresh_token' => null,
        ]);

        // Should complete without error or side effects
        RefreshUserTokenJob::dispatchSync($user);

        $this->assertNull($user->propel_access_token);
    }

    public function test_job_is_dispatchable()
    {
        Queue::fake();

        $user = User::factory()->create([
            'propel_refresh_token' => 'test_token',
        ]);

        RefreshUserTokenJob::dispatch($user);

        Queue::assertPushed(RefreshUserTokenJob::class);
    }
}
```

## Next Steps

1. **Copy the job** to your `app/Jobs` directory
2. **Customize** the token storage and user data fields for your app
3. **Add to scheduler** in `app/Console/Kernel.php`
4. **Test locally** with `php artisan schedule:test`
5. **Monitor** the job logs once in production
6. **Adjust frequency** based on your app's load and requirements

For more information about Laravel's job queue system, see the [Laravel documentation](https://laravel.com/docs/queues).
```

Now let me update the README.md to reference this new guide:
