<?php

namespace App\Services\AI\Router;

use App\Services\AI\AgentState;
use App\Services\AI\Contracts\LLMProviderInterface;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\GroqProvider;
use Illuminate\Support\Facades\Log;

/**
 * LLMRouter — intelligent, intent-aware multi-provider routing.
 *
 * Routing priority (per request):
 *   1. Select preferred provider from config('llm.routing') based on intent
 *   2. If that provider's circuit is OPEN, skip and try next in fallback chain
 *   3. Apply exponential backoff between retries
 *   4. If all providers fail, return null → caller triggers escalation
 *
 * Fallback chain (configurable via config/llm.php):
 *   default → fallback → second_fallback
 */
class LLMRouter
{
    /** @var array<string, LLMProviderInterface> */
    private array $providers;

    public function __construct(private readonly CircuitBreaker $circuitBreaker)
    {
        $this->providers = [
            'openai'    => app(OpenAIProvider::class),
            'anthropic' => app(AnthropicProvider::class),
            'groq'      => app(GroqProvider::class),
        ];
    }

    /**
     * Route a chat completion request through the optimal provider with fallover.
     *
     * @return array|null Normalised provider response, or null if all providers failed.
     */
    public function chat(array $messages, array $tools, AgentState $state): ?array
    {
        $chain   = $this->buildProviderChain($state->intent);
        $maxRetry = config('llm.retries', 3);
        $attempt  = 0;

        foreach ($chain as $providerName) {
            // Skip disabled providers entirely (no attempt, no retry loop)
            if (!$this->isProviderEnabled($providerName)) {
                Log::channel('production')->info("LLMRouter: skipping [{$providerName}] — provider disabled (config)", [
                    'trace_id' => $state->traceId,
                    'intent'   => $state->intent,
                ]);
                continue;
            }

            if (!$this->circuitBreaker->isAvailable($providerName)) {
                Log::channel('production')->info("LLMRouter: skipping [{$providerName}] — circuit OPEN", [
                    'trace_id' => $state->traceId,
                    'intent'   => $state->intent,
                ]);
                continue;
            }

            $provider = $this->providers[$providerName];
            $attempt++;
            $retryCount = 0;

            while ($retryCount <= $maxRetry) {
                if ($retryCount > 0) {
                    // Exponential backoff: 100ms, 200ms, 400ms …
                    $sleepMs = min(100 * (2 ** ($retryCount - 1)), 30_000);
                    usleep($sleepMs * 1000);
                }

                try {
                    $result = $provider->chat($messages, $tools, $state);

                    if ($result !== null) {
                        $this->circuitBreaker->recordSuccess($providerName);
                        $state->llmProvider = $providerName;

                        if ($retryCount > 0 || $attempt > 1) {
                            Log::channel('production')->info('LLMRouter: provider served request', [
                                'trace_id'       => $state->traceId,
                                'provider'       => $providerName,
                                'retry_count'    => $retryCount,
                                'fallback_used'  => $attempt > 1,
                                'intent'         => $state->intent,
                            ]);
                        }

                        return $result;
                    }

                } catch (\Throwable $e) {
                    $retryCount++;
                    Log::channel('production')->warning("LLMRouter: [{$providerName}] attempt {$retryCount} failed", [
                        'trace_id' => $state->traceId,
                        'error'    => $e->getMessage(),
                        'intent'   => $state->intent,
                    ]);

                    if ($retryCount > $maxRetry) {
                        $this->circuitBreaker->recordFailure($providerName);
                        break; // Move to next provider in chain
                    }
                }
            }
        }

        // All providers exhausted
        Log::channel('production')->error('LLMRouter: all providers failed', [
            'trace_id'       => $state->traceId,
            'intent'         => $state->intent,
            'chain_attempted' => $chain,
        ]);

        return null;
    }

    /**
     * Get the provider instance by name (for direct access in health checks etc.)
     */
    public function getProvider(string $name): LLMProviderInterface
    {
        return $this->providers[$name] ?? throw new \InvalidArgumentException("Unknown provider: {$name}");
    }

    /**
     * Return all registered providers (for health checks).
     * @return array<string, LLMProviderInterface>
     */
    public function allProviders(): array
    {
        return $this->providers;
    }

    /**
     * Return circuit breaker state for all providers (for diagnostics).
     * Includes provider enabled/disabled status for observability.
     */
    public function diagnostics(): array
    {
        $out = [];
        foreach (array_keys($this->providers) as $name) {
            $out[$name] = [
                'enabled'         => $this->isProviderEnabled($name),
                'circuit_state'   => $this->circuitBreaker->getState($name),
                'failure_count'   => $this->circuitBreaker->getFailureCount($name),
                'model'           => $this->providers[$name]->model(),
                'supports_vision' => $this->providers[$name]->supportsVision(),
                'supports_tools'  => $this->providers[$name]->supportsTools(),
                'reason'          => !$this->isProviderEnabled($name) ? config('llm.provider_status.' . $name . '.reason', 'unknown') : null,
            ];
        }
        return $out;
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Check if a provider is enabled via config.
     * Disabled providers are skipped without attempting a call.
     */
    private function isProviderEnabled(string $providerName): bool
    {
        return config('llm.providers_enabled.' . $providerName, true);
    }

    /**
     * Build an ordered provider list starting from the intent-preferred provider.
     * The configured fallback chain fills remaining slots without repeating any provider.
     *
     * @return string[]
     */
    private function buildProviderChain(string $intent): array
    {
        $routingMap = config('llm.routing', []);
        $preferred  = $routingMap[$intent] ?? config('llm.default', 'openai');

        $fallback1 = config('llm.fallback', 'anthropic');
        $fallback2 = config('llm.second_fallback', 'groq');

        // Build de-duplicated chain: preferred first, then remaining providers
        $allProviders = ['openai', 'anthropic', 'groq'];
        $chain = [$preferred];

        foreach ([$fallback1, $fallback2, ...$allProviders] as $candidate) {
            if (!in_array($candidate, $chain, true) && isset($this->providers[$candidate])) {
                $chain[] = $candidate;
            }
            if (count($chain) >= count($allProviders)) {
                break;
            }
        }

        return $chain;
    }
}
