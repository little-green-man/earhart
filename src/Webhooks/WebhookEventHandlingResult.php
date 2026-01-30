<?php

namespace LittleGreenMan\Earhart\Webhooks;

/**
 * Result object for webhook event handling operations.
 *
 * Provides detailed information about the outcome of webhook processing,
 * including success/failure status, event type, and any warnings or errors.
 */
class WebhookEventHandlingResult
{
    private bool $success = false;

    private bool $supported = true;

    private string $eventType = '';

    private ?object $event = null;

    private bool $enriched = false;

    private bool $dispatched = false;

    private bool $cacheInvalidated = false;

    private string $errorMessage = '';

    /** @var array<string> */
    private array $warnings = [];

    /**
     * Mark the webhook as successfully processed.
     */
    public function markAsSuccess(): self
    {
        $this->success = true;

        return $this;
    }

    /**
     * Mark the webhook as failed with an error message.
     */
    public function markAsFailed(string $message): self
    {
        $this->success = false;
        $this->errorMessage = $message;

        return $this;
    }

    /**
     * Mark the webhook event type as unsupported.
     */
    public function markAsUnsupported(string $eventType): self
    {
        $this->supported = false;
        $this->eventType = $eventType;

        return $this;
    }

    /**
     * Set the parsed event object.
     */
    public function setEvent(object $event): self
    {
        $this->event = $event;
        $this->eventType = $event::class;

        return $this;
    }

    /**
     * Mark the event as enriched.
     */
    public function setEnriched(bool $enriched): self
    {
        $this->enriched = $enriched;

        return $this;
    }

    /**
     * Mark the event as dispatched.
     */
    public function setDispatched(bool $dispatched): self
    {
        $this->dispatched = $dispatched;

        return $this;
    }

    /**
     * Mark cache as invalidated.
     */
    public function setCacheInvalidated(bool $invalidated): self
    {
        $this->cacheInvalidated = $invalidated;

        return $this;
    }

    /**
     * Add a warning message.
     */
    public function addWarning(string $message): self
    {
        $this->warnings[] = $message;

        return $this;
    }

    /**
     * Check if webhook processing was successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if the event type is supported.
     */
    public function isSupported(): bool
    {
        return $this->supported;
    }

    /**
     * Get the event type.
     */
    public function getEventType(): string
    {
        return $this->eventType;
    }

    /**
     * Get the parsed event object.
     */
    public function getEvent(): ?object
    {
        return $this->event;
    }

    /**
     * Check if the event was enriched.
     */
    public function isEnriched(): bool
    {
        return $this->enriched;
    }

    /**
     * Check if the event was dispatched.
     */
    public function isDispatched(): bool
    {
        return $this->dispatched;
    }

    /**
     * Check if cache was invalidated.
     */
    public function isCacheInvalidated(): bool
    {
        return $this->cacheInvalidated;
    }

    /**
     * Get the error message if processing failed.
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    /**
     * Get all warning messages.
     *
     * @return array<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Check if there are any warnings.
     */
    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    /**
     * Get a summary of the result as an array.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'supported' => $this->supported,
            'event_type' => $this->eventType,
            'enriched' => $this->enriched,
            'dispatched' => $this->dispatched,
            'cache_invalidated' => $this->cacheInvalidated,
            'error' => $this->errorMessage,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * Convert to JSON.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
