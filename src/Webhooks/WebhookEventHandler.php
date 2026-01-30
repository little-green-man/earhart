<?php

namespace LittleGreenMan\Earhart\Webhooks;

use Illuminate\Contracts\Events\Dispatcher;
use LittleGreenMan\Earhart\Exceptions\PropelAuthException;
use LittleGreenMan\Earhart\Services\CacheService;
use LittleGreenMan\Earhart\Services\OrganisationService;
use LittleGreenMan\Earhart\Services\UserService;

/**
 * Orchestrates end-to-end webhook event processing.
 *
 * This handler manages the complete lifecycle of webhook events:
 * 1. Parsing raw webhook data into typed events
 * 2. Enriching events with lazy-loaded data
 * 3. Dispatching events through Laravel's event system
 * 4. Invalidating caches automatically
 *
 * Usage:
 *   $handler = new WebhookEventHandler($parser, $enricher, $invalidator, $dispatcher);
 *   $result = $handler->handle(['event_type' => 'user.created', ...]);
 */
class WebhookEventHandler
{
    private WebhookEventParser $parser;

    private WebhookEventEnricher $enricher;

    private WebhookCacheInvalidator $invalidator;

    private Dispatcher $dispatcher;

    /**
     * Create a new webhook event handler.
     *
     * @param  WebhookEventParser|null  $parser  Optional custom parser (uses default if null)
     * @param  WebhookEventEnricher|null  $enricher  Optional custom enricher (uses default if null)
     * @param  CacheService|null  $cacheService  Optional cache service for invalidator
     * @param  UserService|null  $userService  Optional user service for enricher
     * @param  OrganisationService|null  $organisationService  Optional org service for enricher
     * @param  Dispatcher|null  $dispatcher  Optional event dispatcher (uses app() if null)
     */
    public function __construct(
        ?WebhookEventParser $parser = null,
        ?WebhookEventEnricher $enricher = null,
        ?CacheService $cacheService = null,
        ?UserService $userService = null,
        ?OrganisationService $organisationService = null,
        ?Dispatcher $dispatcher = null,
    ) {
        $this->parser = $parser ?? new WebhookEventParser;

        // Initialize enricher if services are provided
        if ($enricher) {
            $this->enricher = $enricher;
        } else {
            $userService ??= app(UserService::class);
            $organisationService ??= app(OrganisationService::class);
            $this->enricher = new WebhookEventEnricher($userService, $organisationService);
        }

        // Initialize invalidator with cache service
        if ($cacheService) {
            $this->invalidator = new WebhookCacheInvalidator($cacheService);
        } else {
            $cacheService = app(CacheService::class);
            $this->invalidator = new WebhookCacheInvalidator($cacheService);
        }

        // Initialize event dispatcher
        $this->dispatcher = $dispatcher ?? app('events');
    }

    /**
     * Handle a complete webhook event.
     *
     * This method orchestrates the full lifecycle:
     * 1. Parse the raw data into a typed event
     * 2. Enrich the event with lazy-loaded data
     * 3. Dispatch the event through Laravel's event system
     * 4. Invalidate relevant caches
     *
     * @param  array  $data  The raw webhook payload
     * @return WebhookEventHandlingResult The result of webhook processing
     */
    public function handle(array $data): WebhookEventHandlingResult
    {
        $result = new WebhookEventHandlingResult;

        try {
            // Step 1: Parse the webhook data
            $event = $this->parser->parse($data);

            if (! $event) {
                $eventType = $data['event_type'] ?? 'unknown';
                $result->markAsUnsupported($eventType);

                return $result;
            }

            $result->setEvent($event);

            // Step 2: Enrich the event with lazy-loaded data
            try {
                $event = $this->enricher->enrich($event);
                $result->setEnriched(true);
            } catch (\Exception $e) {
                // Enrichment failures are not fatal - event can still be dispatched
                $result->addWarning("Enrichment failed: {$e->getMessage()}");
            }

            // Step 3: Dispatch the event through Laravel's event system
            $this->dispatcher->dispatch($event);
            $result->setDispatched(true);

            // Step 4: Invalidate relevant caches
            try {
                $this->invalidator->handleEvent($event);
                $result->setCacheInvalidated(true);
            } catch (\Exception $e) {
                // Cache invalidation failures are not fatal
                $result->addWarning("Cache invalidation failed: {$e->getMessage()}");
            }

            $result->markAsSuccess();
        } catch (PropelAuthException $e) {
            $result->markAsFailed($e->getMessage());
        } catch (\Exception $e) {
            $result->markAsFailed("Unexpected error: {$e->getMessage()}");
        }

        return $result;
    }

    /**
     * Handle multiple webhook events.
     *
     * @param  array  $dataArray  Array of webhook payloads
     * @return array Array of WebhookEventHandlingResult objects
     */
    public function handleBatch(array $dataArray): array
    {
        return array_map(fn ($data) => $this->handle($data), $dataArray);
    }

    /**
     * Get the event parser instance.
     */
    public function getParser(): WebhookEventParser
    {
        return $this->parser;
    }

    /**
     * Get the event enricher instance.
     */
    public function getEnricher(): WebhookEventEnricher
    {
        return $this->enricher;
    }

    /**
     * Get the cache invalidator instance.
     */
    public function getInvalidator(): WebhookCacheInvalidator
    {
        return $this->invalidator;
    }

    /**
     * Set a custom event parser.
     */
    public function setParser(WebhookEventParser $parser): self
    {
        $this->parser = $parser;

        return $this;
    }

    /**
     * Set a custom event enricher.
     */
    public function setEnricher(WebhookEventEnricher $enricher): self
    {
        $this->enricher = $enricher;

        return $this;
    }

    /**
     * Set a custom cache invalidator.
     */
    public function setInvalidator(WebhookCacheInvalidator $invalidator): self
    {
        $this->invalidator = $invalidator;

        return $this;
    }
}
