<?php

namespace LittleGreenMan\Earhart\Webhooks;

use Svix\Exception\WebhookVerificationException;
use Svix\Webhook;

/**
 * Verifies the authenticity of incoming webhook requests using Svix signatures.
 *
 * This verifier ensures that webhooks originate from PropelAuth by validating
 * the signature included in the webhook headers against the configured signing secret.
 *
 * Usage:
 *   $verifier = new WebhookSignatureVerifier($signingSecret);
 *   $payload = $verifier->verify($requestBody, $svixHeaders);
 *
 * The Svix signing secret should be stored securely in your application's
 * environment variables (e.g., PROPELAUTH_WEBHOOK_SECRET).
 */
class WebhookSignatureVerifier
{
    /**
     * The Svix webhook signing secret.
     */
    private string $signingSecret;

    /**
     * Create a new webhook signature verifier.
     *
     * @param  string  $signingSecret  The webhook signing secret from PropelAuth/Svix
     *
     * @throws \InvalidArgumentException If the signing secret is empty
     */
    public function __construct(string $signingSecret)
    {
        if (empty($signingSecret)) {
            throw new \InvalidArgumentException('Webhook signing secret cannot be empty.');
        }

        $this->signingSecret = $signingSecret;
    }

    /**
     * Verify a webhook request's signature and return the parsed payload.
     *
     * This method validates that the webhook request is authentic by checking
     * the signature in the Svix headers against the configured signing secret.
     *
     * @param  string  $requestBody  The raw HTTP request body
     * @param  array  $headers  The HTTP headers from the request (case-insensitive keys recommended)
     * @return array The verified webhook payload as an associative array
     *
     * @throws WebhookVerificationException If signature verification fails
     * @throws \InvalidArgumentException If required headers are missing
     * @throws \Exception If JSON decoding fails
     */
    public function verify(string $requestBody, array $headers): array
    {
        // Normalize header keys to lowercase for case-insensitive lookup
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);

        // Extract required Svix headers
        $msgId = $normalizedHeaders['svix-id'] ?? null;
        $msgSignature = $normalizedHeaders['svix-signature'] ?? null;
        $msgTimestamp = $normalizedHeaders['svix-timestamp'] ?? null;

        if (! $msgId || ! $msgSignature || ! $msgTimestamp) {
            throw new \InvalidArgumentException(
                'Missing required Svix headers: svix-id, svix-signature, and svix-timestamp are required.',
            );
        }

        // Use Svix's Webhook class to verify the signature
        $webhook = new Webhook($this->signingSecret);

        try {
            // Svix's verify() method returns a string (JSON), so we decode it
            $verifiedPayloadJson = $webhook->verify($requestBody, [
                'svix-id' => $msgId,
                'svix-signature' => $msgSignature,
                'svix-timestamp' => $msgTimestamp,
            ]);

            // The verify method returns a JSON string, decode it
            if (is_string($verifiedPayloadJson)) {
                return json_decode($verifiedPayloadJson, associative: true);
            }

            // If it's already an array, return as-is
            return (array) $verifiedPayloadJson;
        } catch (WebhookVerificationException $e) {
            // Re-throw verification exceptions with a clear message
            throw new WebhookVerificationException("Webhook signature verification failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Check if a timestamp is within the acceptable tolerance window.
     *
     * This helps prevent replay attacks by ensuring webhooks are recent.
     * By default, Svix uses a 5-minute tolerance window.
     *
     * @param  string  $timestamp  The webhook timestamp (Unix timestamp as string)
     * @param  int  $toleranceSeconds  The acceptable tolerance in seconds (default: 300/5 minutes)
     * @return bool True if the timestamp is within tolerance, false otherwise
     */
    public function isTimestampValid(string $timestamp, int $toleranceSeconds = 300): bool
    {
        try {
            $webhookTime = (int) $timestamp;
            $currentTime = time();
            $timeDiff = abs($currentTime - $webhookTime);

            return $timeDiff <= $toleranceSeconds;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Set a new signing secret.
     *
     * @param  string  $signingSecret  The new webhook signing secret
     *
     * @throws \InvalidArgumentException If the signing secret is empty
     */
    public function setSigningSecret(string $signingSecret): self
    {
        if (empty($signingSecret)) {
            throw new \InvalidArgumentException('Webhook signing secret cannot be empty.');
        }

        $this->signingSecret = $signingSecret;

        return $this;
    }

    /**
     * Get the currently configured signing secret (first 8 chars + masked).
     *
     * Useful for debugging without exposing the full secret.
     *
     * @return string A masked version of the signing secret
     */
    public function getMaskedSecret(): string
    {
        $length = strlen($this->signingSecret);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        $firstChars = substr($this->signingSecret, 0, 8);
        $masked = str_repeat('*', $length - 8);

        return "{$firstChars}{$masked}";
    }
}
