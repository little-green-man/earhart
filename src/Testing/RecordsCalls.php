<?php

namespace LittleGreenMan\Earhart\Testing;

/**
 * @internal
 */
trait RecordsCalls
{
    protected FakeState $state;

    /**
     * Record a call, throw any scripted failure, then run the fake behaviour.
     *
     * @param  array<string, mixed>  $args
     */
    protected function fake(string $method, array $args, \Closure $callback): mixed
    {
        $service = get_parent_class($this);
        $index = count($this->state->calls);
        $this->state->calls[] = ['service' => $service, 'method' => $method, 'args' => $args, 'failed' => false];

        try {
            if ($failure = $this->state->nextFailure($service, $method)) {
                throw $failure;
            }

            return $callback();
        } catch (\Throwable $e) {
            $this->state->calls[$index]['failed'] = true;

            throw $e;
        }
    }

    /**
     * The fake never sends HTTP requests.
     */
    protected function makeRequest(string $method, string $endpoint, array $data = [], ?\Closure $notFound = null): array
    {
        throw new \LogicException("Earhart fake has no behaviour for {$method} {$endpoint}.");
    }
}
