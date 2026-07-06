<?php

namespace App\Services\AI\Router;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * CircuitBreaker — protects the LLM failover chain from cascading failures.
 *
 * States:
 *   CLOSED    — Normal operation. Requests pass through.
 *   OPEN      — Too many failures. Requests are rejected immediately.
 *   HALF_OPEN — Recovery probe: one trial request allowed.
 *
 * State is persisted in Redis with a TTL matching recovery_seconds so that
 * open circuits auto-expire across PHP process boundaries.
 */
class CircuitBreaker
{
    private const STATE_CLOSED    = 'CLOSED';
    private const STATE_OPEN      = 'OPEN';
    private const STATE_HALF_OPEN = 'HALF_OPEN';

    private int $threshold;
    private int $recoverySeconds;

    public function __construct()
    {
        $this->threshold       = config('llm.circuit_breaker.threshold', 3);
        $this->recoverySeconds = config('llm.circuit_breaker.recovery_seconds', 60);
    }

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Returns true if requests should be allowed through for this provider.
     */
    public function isAvailable(string $provider): bool
    {
        $state = $this->getState($provider);

        if ($state === self::STATE_CLOSED) {
            return true;
        }

        if ($state === self::STATE_HALF_OPEN) {
            // Allow one trial request
            return true;
        }

        // OPEN — check if recovery window has elapsed
        $openedAt = Cache::get($this->openedAtKey($provider));
        if ($openedAt && (time() - $openedAt) >= $this->recoverySeconds) {
            $this->transitionTo($provider, self::STATE_HALF_OPEN);
            Log::channel('production')->info("CircuitBreaker [{$provider}] transitioning OPEN → HALF_OPEN", [
                'provider' => $provider,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Record a successful call — resets failure count and closes circuit.
     */
    public function recordSuccess(string $provider): void
    {
        $state = $this->getState($provider);

        if ($state !== self::STATE_CLOSED) {
            Log::channel('production')->info("CircuitBreaker [{$provider}] CLOSED after successful call", [
                'provider' => $provider,
            ]);
        }

        Cache::forget($this->failuresKey($provider));
        Cache::forget($this->openedAtKey($provider));
        $this->transitionTo($provider, self::STATE_CLOSED);
    }

    /**
     * Record a failed call — increments failure count, may trip the circuit.
     */
    public function recordFailure(string $provider): void
    {
        $failures = (int) Cache::increment($this->failuresKey($provider));
        Cache::put($this->failuresKey($provider), $failures, $this->recoverySeconds * 2);

        $state = $this->getState($provider);

        if ($state === self::STATE_HALF_OPEN) {
            // Trial request failed — reopen
            $this->openCircuit($provider);
            Log::channel('production')->warning("CircuitBreaker [{$provider}] HALF_OPEN probe failed — reopened", [
                'provider' => $provider,
            ]);
            return;
        }

        if ($failures >= $this->threshold && $state === self::STATE_CLOSED) {
            $this->openCircuit($provider);
            Log::channel('production')->warning("CircuitBreaker [{$provider}] OPEN after {$failures} failures", [
                'provider'  => $provider,
                'threshold' => $this->threshold,
            ]);
        }
    }

    /**
     * Return the current state of the circuit for a provider.
     */
    public function getState(string $provider): string
    {
        return Cache::get($this->stateKey($provider), self::STATE_CLOSED);
    }

    /**
     * Return failure count for a provider (for diagnostics).
     */
    public function getFailureCount(string $provider): int
    {
        return (int) Cache::get($this->failuresKey($provider), 0);
    }

    /**
     * Force-reset a provider's circuit (admin action / testing).
     */
    public function reset(string $provider): void
    {
        Cache::forget($this->stateKey($provider));
        Cache::forget($this->failuresKey($provider));
        Cache::forget($this->openedAtKey($provider));
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function openCircuit(string $provider): void
    {
        Cache::put($this->openedAtKey($provider), time(), $this->recoverySeconds * 3);
        $this->transitionTo($provider, self::STATE_OPEN);
    }

    private function transitionTo(string $provider, string $state): void
    {
        Cache::put($this->stateKey($provider), $state, $this->recoverySeconds * 3);
    }

    private function stateKey(string $provider): string    { return "llm:circuit:state:{$provider}"; }
    private function failuresKey(string $provider): string { return "llm:circuit:failures:{$provider}"; }
    private function openedAtKey(string $provider): string { return "llm:circuit:opened_at:{$provider}"; }
}
