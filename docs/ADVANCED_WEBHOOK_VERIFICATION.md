## Webhook Signature Verification (Optional)

Starting with v1.4, Earhart includes more advanced webhook signature verification.

You don't need to use this feature unless you want to implement more advanced verification, following the README will work fine and securely.

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
