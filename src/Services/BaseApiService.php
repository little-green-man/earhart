<?php

namespace LittleGreenMan\Earhart\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
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
    /**
     * Keys whose contents are user-defined or keyed by ID, so are never case-converted.
     * `org_id_to_org_info` stays snake_case inside; OrgMemberInfo reads it as such.
     */
    protected const UNCONVERTED_KEYS = [
        'properties',
        'metadata',
        'orgs',
        'org_id_to_org_info',
        'org_metadata',
        'user_signup_query_parameters',
        'extra_properties',
    ];

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
            if (is_array($value)) {
                $shouldSkipNested = $skipConversion || in_array($snakeKey, self::UNCONVERTED_KEYS, true);
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
            if (is_array($value)) {
                $shouldSkipNested = $skipConversion || in_array($key, self::UNCONVERTED_KEYS, true);
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
     * @param  (\Closure(): PropelAuthException)|null  $notFound  Builds the exception thrown on a 404
     * @return array<string, mixed>
     *
     * @throws PropelAuthException On any failed response, as the subclass matching its status
     */
    protected function makeRequest(string $method, string $endpoint, array $data = [], ?\Closure $notFound = null): array
    {
        // Convert outgoing parameters to snake_case
        $snakeCaseData = $this->toSnakeCase($data);

        // Convert booleans to string 'true'/'false' for GET requests (query params)
        if ($method === 'GET') {
            $snakeCaseData = $this->convertBooleansToStrings($snakeCaseData);
        }

        try {
            $response = $this->executeWithRetry(fn () => $this->sendRequest($method, $endpoint, $snakeCaseData));
        } catch (PropelAuthException $e) {
            if ($notFound !== null && $e->getStatusCode() === 404) {
                throw $notFound();
            }

            throw $e;
        }

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
     * @return array<string, mixed> The decoded response body
     *
     * @throws PropelAuthException On any failed response, including a 404
     */
    protected function sendRequest(string $method, string $endpoint, array $data = []): array
    {
        $request = Http::withToken($this->apiKey)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->timeout((float) config('earhart.http.timeout', 30))
            ->connectTimeout((float) config('earhart.http.connect_timeout', 10));

        $response = match ($method) {
            'GET' => $request->get($this->authUrl.$endpoint, $data),
            'POST' => $request->post($this->authUrl.$endpoint, $data),
            'PUT' => $request->put($this->authUrl.$endpoint, $data),
            'DELETE' => $request->delete($this->authUrl.$endpoint, $data),
            'PATCH' => $request->patch($this->authUrl.$endpoint, $data),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        if ($response->failed()) {
            throw PropelAuthException::fromResponse($method, $endpoint, $response);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Execute request with automatic retry logic for rate limiting.
     *
     * Retries up to `earhart.retries.times` times. Each wait is the
     * Retry-After value when PropelAuth sends one, otherwise exponential
     * backoff with jitter, and never exceeds `earhart.retries.max_delay_ms`.
     * If Retry-After asks for longer than that cap, the exception is
     * rethrown at once rather than retrying too early.
     */
    protected function executeWithRetry(\Closure $callback): mixed
    {
        $retries = max(0, (int) config('earhart.retries.times', 2));
        $baseDelay = max(0, (int) config('earhart.retries.base_delay_ms', 2000));
        $maxDelay = max(0, (int) config('earhart.retries.max_delay_ms', 5000));

        for ($attempt = 0; ; $attempt++) {
            try {
                return $callback();
            } catch (RateLimitException $e) {
                if ($attempt >= $retries) {
                    throw $e;
                }

                if ($e->retryAfterFromHeader) {
                    $delay = $e->retryAfterSeconds * 1000;

                    if ($delay > $maxDelay) {
                        throw $e;
                    }
                } else {
                    $delay = $baseDelay * (2 ** $attempt);
                    $delay = min($delay + random_int(0, intdiv($delay, 10)), $maxDelay);
                }

                Sleep::for($delay)->milliseconds();
            }
        }
    }
}
