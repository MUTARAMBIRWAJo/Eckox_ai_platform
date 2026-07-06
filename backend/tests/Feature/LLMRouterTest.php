<?php

namespace Tests\Feature;

use App\Services\AI\AgentState;
use App\Services\AI\Router\CircuitBreaker;
use App\Services\AI\Router\LLMRouter;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\GroqProvider;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LLMRouterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_router_selects_correct_preferred_provider_based_on_intent()
    {
        $router = app(LLMRouter::class);
        $this->assertInstanceOf(LLMRouter::class, $router);

        // Intention checks based on config routing
        // TEMPORARY (2026-07): All intents prefer Groq due to OpenAI/Anthropic billing hold
        $reflector = new \ReflectionClass($router);
        $method = $reflector->getMethod('buildProviderChain');
        $method->setAccessible(true);

        // All intents now prefer Groq as primary
        $chainGeneral = $method->invoke($router, 'general');
        $this->assertEquals('groq', $chainGeneral[0]);

        $chainBuy = $method->invoke($router, 'buy_intent');
        $this->assertEquals('groq', $chainBuy[0]); // Changed from 'openai' due to temporary config

        $chainLegal = $method->invoke($router, 'complaint_legal');
        $this->assertEquals('groq', $chainLegal[0]); // Changed from 'anthropic' due to temporary config
    }

    public function test_circuit_breaker_state_transitions()
    {
        $cb = app(CircuitBreaker::class);
        $provider = 'groq';

        $this->assertTrue($cb->isAvailable($provider));
        $this->assertEquals('CLOSED', $cb->getState($provider));

        // Record failures up to threshold (default 3)
        $cb->recordFailure($provider);
        $cb->recordFailure($provider);
        $cb->recordFailure($provider);

        $this->assertEquals('OPEN', $cb->getState($provider));
        $this->assertFalse($cb->isAvailable($provider));

        // Success records should clear and reset CLOSED
        $cb->recordSuccess($provider);
        $this->assertEquals('CLOSED', $cb->getState($provider));
        $this->assertTrue($cb->isAvailable($provider));
    }
}
