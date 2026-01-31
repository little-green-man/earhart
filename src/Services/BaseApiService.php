<?php

namespace LittleGreenMan\Earhart\Services;

use Illuminate\Support\Facades\Http;
use LittleGreenMan\Earhart\Exceptions\RateLimitException;

/**
 * Base class for PropelAuth API services with automatic case conversion.
 *
 * PropelAuth API uses snake_case for all parameters and response keys,
 * while this package uses camelCase for PHP conventions. This class
 * handles bidirectional conversion automatically.
 */
abstract class BaseApiService
{
    protected int $maxRetries = 3;

    protected int $initialRetryDelay = 1; // seconds

    public function __construct(
        protected string $apiKey,
        protected string $authUrl,
        protected CacheService $cache,
    ) {}

    /**
     * Convert array keys from camelCase to snake_case recursively.
     *
     * @param  array<string, mixed>  $data
     * @param  bool  $skipConversion  Skip conversion for user-defined data
     * @return array<string, mixed>
     */
    protected function toSnakeCase(array $data, bool $skipConversion = false): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            // Skip conversion if requested (for user-defined data)
            if ($skipConversion) {
                $snakeKey = $key;
            } else {
                // Convert camelCase to snake_case
                // Handle consecutive capitals (HTTPResponse -> http_response)
                // Handle numeric suffixes (mfaBase32 -> mfa_base_32)
                $snakeKey = $key;
                $snakeKey = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $snakeKey);
                $snakeKey = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $snakeKey);
                $snakeKey = strtolower($snakeKey);
            }

            // Recursively convert nested arrays, but preserve user-defined data
            // Don't convert keys in properties, metadata, or orgs arrays
            if (is_array($value)) {
                $shouldSkipNested = $skipConversion || in_array($snakeKey, ['properties', 'metadata', 'orgs'], true);
                $result[$snakeKey] = $this->toSnakeCase($value, $shouldSkipNested);
            } else {
                $result[$snakeKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Convert array keys from snake_case to camelCase recursively.
     *
     * @param  array<string, mixed>  $data
     * @param  bool  $skipConversion  Skip conversion for user-defined data
     * @return array<string, mixed>
     */
    protected function toCamelCase(array $data, bool $skipConversion = false): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            // Skip conversion if requested (for user-defined data)
            if ($skipConversion) {
                $camelKey = $key;
            } else {
                // Convert snake_case to camelCase
                $camelKey = lcfirst(str_replace('_', '', ucwords($key, '_')));
            }

            // Recursively convert nested arrays, but preserve user-defined data
            // Don't convert keys in properties, metadata, or orgs arrays
            if (is_array($value)) {
                $shouldSkipNested = $skipConversion || in_array($key, ['properties', 'metadata', 'orgs'], true);
                $result[$camelKey] = $this->toCamelCase($value, $shouldSkipNested);
            } else {
                $result[$camelKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Make API request with automatic case conversion.
     *
     * Converts outgoing parameters to snake_case for PropelAuth API,
     * then converts response keys to camelCase for PHP conventions.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        // Convert outgoing parameters to snake_case
        $snakeCaseData = $this->toSnakeCase($data);

        // Convert booleans to string 'true'/'false' for GET requests (query params)
        if ($method === 'GET') {
            $snakeCaseData = $this->convertBooleansToStrings($snakeCaseData);
        }

        // Execute request with retry logic
        $response = $this->executeWithRetry(fn () => $this->sendRequest($method, $endpoint, $snakeCaseData));

        // Convert response keys to camelCase for PHP conventions
        return $this->toCamelCase($response);
    }

    /**
     * Convert boolean values to string literals 'true'/'false' for query parameters.
     * PropelAuth API requires string 'true'/'false', not 1/0.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function convertBooleansToStrings(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $result[$key] = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $result[$key] = $this->convertBooleansToStrings($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Send HTTP request to PropelAuth API.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sendRequest(string $method, string $endpoint, array $data = []): array
    {
        $request = Http::withToken($this->apiKey)->withHeaders(['Content-Type' => 'application/json'])->timeout(30);

        $response = match ($method) {
            'GET' => $request->get($this->authUrl.$endpoint, $data),
            'POST' => $request->post($this->authUrl.$endpoint, $data),
            'PUT' => $request->put($this->authUrl.$endpoint, $data),
            'DELETE' => $request->delete($this->authUrl.$endpoint, $data),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        if ($response->status() === 429) {
            throw RateLimitException::fromHeaders($response->header('Retry-After'));
        }

        // Allow 404 to pass through - let callers handle it
        // But throw for other error responses
        if ($response->failed() && $response->status() !== 404) {
            throw new \Exception("PropelAuth API error: {$response->status()} - {$response->body()}");
        }

        $json = $response->json();
        if (! is_array($json)) {
            $json = [];
        }

        return $json + ['status' => $response->status()];
    }

    /**
     * Execute request with automatic retry logic for rate limiting.
     */
    protected function executeWithRetry(\Closure $callback): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            try {
                return $callback();
            } catch (RateLimitException $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt >= $this->maxRetries) {
                    break;
                }

                // Exponential backoff with jitter
                $delay = $this->initialRetryDelay * (2 ** $attempt);
                $jitter = random_int(0, (int) ($delay * 0.1));
                sleep($delay + $jitter);
            }
        }

        throw $lastException ?? new \RuntimeException('Max retries exceeded');
    }
}
