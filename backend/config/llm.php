<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default & Fallback Providers
    |--------------------------------------------------------------------------
    | Specifies the provider chain for general routing.
    | TEMPORARY CONFIG (2026-07): Groq is primary due to OpenAI/Anthropic
    | billing issues. Once billing is resolved, revert to original order by
    | setting env vars: LLM_DEFAULT_PROVIDER=openai, LLM_FALLBACK_PROVIDER=anthropic
    |
    | Individual intents can override via the 'routing' table below.
    */
    'default'         => env('LLM_DEFAULT_PROVIDER', 'groq'),
    'fallback'        => env('LLM_FALLBACK_PROVIDER', 'anthropic'),
    'second_fallback' => env('LLM_SECOND_FALLBACK', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeouts & Retry Policy
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('LLM_TIMEOUT', 30),
    'retries' => (int) env('LLM_MAX_RETRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Provider Enable/Disable Flags
    |--------------------------------------------------------------------------
    | TEMPORARY CONFIG (2026-07): Disable OpenAI and Anthropic due to billing
    | issues. Set to true to re-enable once billing is resolved (no code
    | changes required — only env var update). Disabled providers are skipped
    | immediately in the fallback chain, not attempted and failed.
    */
    'providers_enabled' => [
        'openai'    => (bool) env('LLM_PROVIDER_OPENAI_ENABLED', false),
        'anthropic' => (bool) env('LLM_PROVIDER_ANTHROPIC_ENABLED', false),
        'groq'      => (bool) env('LLM_PROVIDER_GROQ_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    | threshold        — consecutive failures before OPEN
    | recovery_seconds — seconds before transitioning OPEN → HALF_OPEN
    */
    'circuit_breaker' => [
        'threshold'        => (int) env('LLM_CB_THRESHOLD', 3),
        'recovery_seconds' => (int) env('LLM_CB_RECOVERY', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Mapping
    |--------------------------------------------------------------------------
    */
    'models' => [
        'openai' => [
            'chat'      => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
            'embedding' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'vision'    => env('OPENAI_VISION_MODEL', 'gpt-4o'),
        ],
        'anthropic' => [
            'chat' => env('ANTHROPIC_CHAT_MODEL', 'claude-sonnet-5'),
            'fast' => env('ANTHROPIC_FAST_MODEL', 'claude-haiku-4-5-20251001'),
        ],
        'groq' => [
            'chat' => env('GROQ_CHAT_MODEL', 'llama-3.3-70b-versatile'),
            'fast' => env('GROQ_FAST_MODEL', 'llama-3.1-8b-instant'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Intent-Based Routing Rules
    |--------------------------------------------------------------------------
    | Maps intent or task type → preferred provider.
    | TEMPORARY: All routes now prefer Groq (primary). OpenAI/Anthropic routes
    | remain defined for future restoration; fallback chain will skip disabled
    | providers automatically.
    | If that provider's circuit is OPEN, LLMRouter falls back to the chain.
    */
    'routing' => [
        // All intents → Groq (temporary, 2026-07)
        'general'            => 'groq',
        'summary'            => 'groq',
        'summarization'      => 'groq',
        'support_request'    => 'groq',
        'language_detection' => 'groq',
        'buy_intent'         => 'groq',
        'document_generation' => 'groq',
        'invoice'            => 'groq',
        'quote'              => 'groq',
        'knowledge_search'   => 'groq',
        'complex_reasoning'  => 'groq',
        'long_reasoning'     => 'groq',
        'complaint_legal'    => 'groq',
        'agreement'          => 'groq',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Limits (USD)
    |--------------------------------------------------------------------------
    */
    'cost_limits' => [
        'daily_usd'       => (float) env('LLM_DAILY_COST_LIMIT', 50.0),
        'per_request_usd' => (float) env('LLM_PER_REQUEST_LIMIT', 0.50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversation Memory
    |--------------------------------------------------------------------------
    */
    'memory' => [
        'window_size'     => (int) env('LLM_MEMORY_WINDOW', 10),
        'cache_ttl'       => (int) env('LLM_MEMORY_CACHE_TTL', 86400),  // 24 h
        'summarize_after' => (int) env('LLM_SUMMARIZE_AFTER', 20),      // turns
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Observability Status (for health checks & dashboards)
    |--------------------------------------------------------------------------
    | Current active status of each provider — read-only, for monitoring.
    | This reflects the enable/disable flags above. Updated at runtime.
    */
    'provider_status' => [
        'openai'    => ['enabled' => false, 'reason' => 'billing_hold'],
        'anthropic' => ['enabled' => false, 'reason' => 'billing_hold'],
        'groq'      => ['enabled' => true, 'reason' => 'primary_active'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching TTLs (seconds)
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'embeddings_ttl' => (int) env('LLM_EMBEDDING_CACHE_TTL', 604800), // 7 days
        'response_ttl'   => (int) env('LLM_RESPONSE_CACHE_TTL', 60),      // 1 min
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing Table (USD per 1 000 000 tokens)
    |--------------------------------------------------------------------------
    | Used by each provider's cost() method for accurate per-call accounting.
    */
    'pricing' => [
        'openai' => [
            'gpt-4o-mini'             => ['input' => 0.150,  'output' => 0.600],
            'gpt-4o'                  => ['input' => 2.500,  'output' => 10.000],
            'text-embedding-3-small'  => ['input' => 0.020,  'output' => 0.000],
            'text-embedding-3-large'  => ['input' => 0.130,  'output' => 0.000],
        ],
        'anthropic' => [
            'claude-sonnet-5'            => ['input' => 3.000,  'output' => 15.000],
            'claude-haiku-4-5-20251001'  => ['input' => 0.250,  'output' => 1.250],
            'claude-3-5-sonnet-20241022' => ['input' => 3.000,  'output' => 15.000],
            'claude-3-haiku-20240307'    => ['input' => 0.250,  'output' => 1.250],
            'claude-3-opus-20240229'     => ['input' => 15.000, 'output' => 75.000],
        ],
        'groq' => [
            'llama-3.3-70b-versatile' => ['input' => 0.590, 'output' => 0.790],
            'llama-3.1-8b-instant'    => ['input' => 0.050, 'output' => 0.080],
            'mixtral-8x7b-32768'      => ['input' => 0.240, 'output' => 0.240],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Language → Preferred Provider
    |--------------------------------------------------------------------------
    | Some languages have better tokenisation/training on specific providers.
    */
    'language_routing' => [
        'ar' => 'anthropic',  // Arabic — Claude handles RTL better
        'fr' => 'openai',
        'en' => 'openai',
        'pt' => 'groq',
        'sw' => 'groq',
        'rw' => 'groq',
        'de' => 'openai',
        'it' => 'openai',
        'es' => 'groq',
    ],

];
