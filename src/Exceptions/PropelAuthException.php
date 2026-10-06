<?php

namespace LittleGreenMan\Earhart\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
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
        $status = $response->status();

        if ($status === 429) {
            return RateLimitException::fromHeaders($response->header('Retry-After') ?: null);
        }

        $message = "PropelAuth API error: {$status} on {$method} {$endpoint}";
        $context = [
            'method' => $method,
            'endpoint' => $endpoint,
            'response_body' => Str::limit($response->body(), self::MAX_BODY_LENGTH),
        ];

        return match (true) {
            in_array($status, [400, 422], true) => new ValidationException(
                $message,
                is_array($response->json()) ? $response->json() : null,
                $status,
                $context,
            ),
            in_array($status, [401, 403], true) => new UnauthorizedException($message, $status, $context),
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

    public function report(): void
    {
        // The response body may echo user data, so it is left out of the log.
        $context = $this->context;
        unset($context['response_body']);

        Log::error($this->message, [
            'status_code' => $this->statusCode,
            'context' => $context,
            'exception' => static::class,
            'trace' => $this->getTrace(),
        ]);
    }
}
