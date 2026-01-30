<?php

namespace LittleGreenMan\Earhart\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class PropelAuthException extends Exception
{
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
        Log::error($this->message, [
            'status_code' => $this->statusCode,
            'context' => $this->context,
            'exception' => static::class,
            'trace' => $this->getTrace(),
        ]);
    }
}
