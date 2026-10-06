# Upgrading to v3.0

This guide covers upgrading from Earhart v2.x to v3.0. Most apps need no code changes unless they rely on a missing user or organisation "succeeding", or parse exception messages.

## Breaking changes checklist

- [ ] Handle not-found exceptions on write calls
- [ ] Stop parsing exception messages for the response body
- [ ] Check your rate-limit retry expectations
- [ ] Check PropelAuth errors now reaching your error tracker
- [ ] Update any subclass of `UserService`, `OrganisationService` or `BaseApiService`

## 1. Write calls throw on a 404

**Likelihood of impact: high**

In v2, `sendRequest()` let a 404 through and only the read methods checked for it. Writes such as `disableUser()`, `enableUser()` and `deleteUser()` returned `true` even when the user did not exist, so a wrong or stale ID silently "succeeded".

In v3, every call throws on a 404:

| Call | Throws |
| --- | --- |
| Methods taking a `$userId` (e.g. `disableUser()`, `enableUser()`, `deleteUser()`, `updateUser()`, `logoutAllSessions()`, `createAccessToken()`) | `InvalidUserException` (status 404) |
| Methods taking an `$orgId` (e.g. `updateOrganisation()`, SAML methods, `migrateOrgToIsolated()`, `getOrganisationUsers()`) | `InvalidOrgException` (status 404) |
| `addUserToOrganisation()`, `removeUserFromOrganisation()`, `changeUserRole()` | `PropelAuthException` (status 404), since either ID could be missing |

Write methods still return `bool`, which is always `true`; failures are reported by exceptions.

If "already gone" counts as success for you, for example in a GDPR erase, catch it:

```php
use LittleGreenMan\Earhart\Exceptions\InvalidUserException;

// OLD
$earhart->deleteUser($propelId);

// NEW
try {
    $earhart->deleteUser($propelId);
} catch (InvalidUserException $e) {
    // Already deleted in PropelAuth
}
```

Search your app for these calls:

```bash
grep -rnE "(disableUser|enableUser|deleteUser|updateUser|clearUserPassword|disable2FA|logoutAllSessions|updateOrganisation|removeUserFromOrganisation)\(" app
```

## 2. Typed exceptions with short messages

**Likelihood of impact: low**

API failures now throw `PropelAuthException` or a subclass, all of which extend `\Exception`, so existing `catch (\Exception $e)` blocks still work.

| Status | v2 | v3 |
| --- | --- | --- |
| 400, 422 | `\Exception` | `ValidationException` |
| 401, 403 | `\Exception` | `UnauthorizedException` |
| 404 | see section 1 | see section 1 |
| 429 | `RateLimitException` | `RateLimitException` |
| Other | `\Exception` | `PropelAuthException` |

Messages no longer include the response body, because they flow to logs and error trackers and PropelAuth bodies can echo user data:

```
v2: PropelAuth API error: 500 - {"error":"..."}
v3: PropelAuth API error: 500 on DELETE /api/backend/v1/user/{id}
```

If you read the body from the message, use the context instead:

```php
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\Exceptions\ValidationException;

try {
    $earhart->createUser($email);
} catch (ValidationException $e) {
    $e->getErrors();                       // Decoded PropelAuth error body
} catch (PropelAuthException $e) {
    $e->getStatusCode();                   // e.g. 500
    $e->getContext()['response_body'];     // Truncated to 1,024 characters
}
```

### Reporting

v2's `PropelAuthException::report()` wrote its own `Log::error()` entry, which stopped Laravel's exception handler going any further: error trackers such as Sentry and Nightwatch never saw these exceptions. v3 removes `report()` and adds `context()`, so Laravel reports them as normal, with `status_code` and `context` (minus `response_body`) added to the log entry.

If you relied on the old log entry's shape, or you now see PropelAuth errors in your tracker that you don't want, filter them in `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->dontReport(InvalidUserException::class);
})
```

## 3. Rate-limit retries

**Likelihood of impact: low**

v2 ignored `Retry-After` and always slept 2 s then 4 s. v3:

- waits for `Retry-After` when PropelAuth sends it, otherwise backs off exponentially (2 s, 4 s, …) with jitter
- never waits longer than `earhart.retries.max_delay_ms` (default 5,000 ms). If `Retry-After` asks for longer, the `RateLimitException` is thrown at once instead of retrying too early
- `RateLimitException::fromHeaders()` no longer raises values below 60 s to 60 s. A missing header still defaults to 60 s; check `$e->retryAfterFromHeader` to tell them apart

The defaults keep v2's three attempts. To fail fast in web requests and let queued jobs retry:

```env
PROPELAUTH_RETRY_TIMES=0
```

Settings are read on each request, so a job can raise them at runtime:

```php
config(['earhart.retries.times' => 3, 'earhart.retries.max_delay_ms' => 60_000]);
```

## 4. New config values

If you published `config/earhart.php`, add the new keys (or re-publish it with `--force`):

```php
'http' => [
    'timeout' => env('PROPELAUTH_HTTP_TIMEOUT', 30),
    'connect_timeout' => env('PROPELAUTH_HTTP_CONNECT_TIMEOUT', 10),
],

'retries' => [
    'times' => env('PROPELAUTH_RETRY_TIMES', 2),
    'base_delay_ms' => env('PROPELAUTH_RETRY_BASE_DELAY_MS', 2000),
    'max_delay_ms' => env('PROPELAUTH_RETRY_MAX_DELAY_MS', 5000),
],
```

The defaults apply even if you don't.

## 5. Service subclasses

**Only if you extend `BaseApiService`, `UserService` or `OrganisationService`.**

- `makeRequest()` and `sendRequest()` no longer add a `status` key to the returned array. Replace `($response['status'] ?? 200) === 404` checks with the new `$notFound` argument:

  ```php
  // OLD
  $response = $this->makeRequest('GET', "/api/backend/v1/user/{$userId}");
  if (($response['status'] ?? 200) === 404) {
      throw InvalidUserException::notFound($userId);
  }

  // NEW
  $response = $this->makeRequest('GET', "/api/backend/v1/user/{$userId}", notFound: fn () => InvalidUserException::notFound($userId));
  ```

- `sendRequest()` throws on every failed response, including a 404.
- The `$maxRetries` and `$initialRetryDelay` properties are removed; use the `earhart.retries` config.

## New: testing fake

Not breaking, but it replaces mocking `Earhart` with Mockery or faking raw PropelAuth URLs with `Http::fake()`:

```php
use LittleGreenMan\Earhart\Earhart;

$fake = Earhart::fake();
$user = $fake->addUser(['email' => 'jane@example.com']);

$this->post("/admin/users/{$user->userId}/disable");

$fake->assertUserDisabled($user->userId);
```

See [Testing](docs/USING_PROPEL_API.md#testing).
