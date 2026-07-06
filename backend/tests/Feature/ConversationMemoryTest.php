<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\AiMemory;
use App\Services\AI\AgentState;
use App\Services\AI\Memory\ConversationMemory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationMemoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_conversation_memory_loads_and_appends_correctly()
    {
        $lead = Lead::factory()->create();
        $memory = app(ConversationMemory::class);

        $state = new AgentState('test-trace');
        $state->lead = $lead;

        // Verify initial empty state
        $history = $memory->load($state);
        $this->assertEmpty($history);

        // Append turn
        $memory->append($lead->id, 'Hello sales team', 'Hello there, how can we help?', 'openai');

        // Load again
        $history = $memory->load($state);
        $this->assertCount(2, $history);
        $this->assertEquals('Hello sales team', $history[0]['content']);
        $this->assertEquals('Hello there, how can we help?', $history[1]['content']);
    }
}
