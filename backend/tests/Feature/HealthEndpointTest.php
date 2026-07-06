<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;
    public function test_ai_health_endpoint_returns_valid_structure()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/health/ai');

        // Since live pings to LLM APIs might be made or mock-intercepted, 
        // we check the returned JSON schema structure.
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'openai',
                'anthropic',
                'groq',
                'redis',
                'supabase_db',
            ],
            'diagnostics',
        ]);
    }
}
