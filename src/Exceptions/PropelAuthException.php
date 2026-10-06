<?php

namespace LittleGreenMan\Earhart\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

class PropelAuthException extends Exception
{
    /**
     * Maximum length of a response body kept in an exception's context.
     */
    public const MAX_BODY_LENGTH = 1024;

    protected int $statusCode;

    protected ?array $context;

    public function __construct(
        string $message,
        int $statusCode = 500,
        ?array $context = null,
        ?Exception $previous = null,
    ) {
        $this->statusCode = $statusCode;
        $this->context = $context;
        parent::__construct($message, 0, $previous);
    }

    /**
     * Build the exception for a failed PropelAuth API response.
     *
     * The message holds only the status, method and endpoint, so it is safe to
     * send to logs and error trackers. The (truncated) response body is kept in
     * the context under `response_body`.
     */
    public static function fromResponse(string $method, string $endpoint, Response $response): self
    {
        if ($response->status() === 429) {
            // PropelAuth's per-key limit on an end-user API key, not a limit on your backend calls
            if (is_array($response->json()) && isset($response->json()['wait_seconds'])) {
                return ApiKeyRateLimitException::fromBody($response->json());
            }

            return RateLimitException::fromHeaders($response->header('Retry-After') ?: null);
        }

        return static::forStatus(
            $response->status(),
            "PropelAuth API error: {$response->status()} on {$method} {$endpoint}",
            [
                'method' => $method,
                'endpoint' => $endpoint,
                'response_body' => Str::limit($response->body(), self::MAX_BODY_LENGTH),
            ],
            is_array($response->json()) ? $response->json() : null,
        );
    }

    /**
     * Build the exception subclass matching an HTTP status.
     *
     * @param  array<mixed>|null  $errors  Field errors, used for a ValidationException
     */
    public static function forStatus(int $status, string $message, ?array $context = null, ?array $errors = null): self
    {
        return match (true) {
            $status === 429 => new RateLimitException($message),
            in_array($status, [400, 422], true) => new ValidationException($message, $errors, $status, $context),
            in_array($status, [401, 403], true) => new UnauthorizedException($message, $status, $context),
            $status === 426 => new FeatureNotEnabledException(
                "{$message}. The feature is not enabled for this PropelAuth project; organisations need B2B support enabled in the dashboard",
                $status,
                $context,
            ),
            default => new self($message, $status, $context),
        };
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    /**
     * Extra log context, added by Laravel's exception handler when it reports
     * this exception. The response body may echo user data, so it is left out.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        $context = $this->context;
        unset($context['response_body']);

        return [
            'status_code' => $this->statusCode,
            'context' => $context,
        ];
    }
}
