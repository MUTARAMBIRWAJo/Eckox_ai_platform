<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AI\AIChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AIChatSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Role $staffRole;
    private Role $customerRole;
    private User $staffUser;
    private User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup spatie roles
        $this->staffRole    = Role::firstOrCreate(['name' => 'sales-agent', 'guard_name' => 'web']);
        $this->customerRole = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $this->staffUser = User::factory()->create();
        $this->staffUser->assignRole($this->staffRole);

        $this->customerUser = User::factory()->create();
        $this->customerUser->assignRole($this->customerRole);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson('/api/ai/chat/stream', [
            'message' => 'Hello assistant',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_non_staff_user_is_forbidden(): void
    {
        $response = $this->actingAs($this->customerUser, 'sanctum')
            ->postJson('/api/ai/chat/stream', [
                'message' => 'Hello assistant',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Unauthorized role access.');
    }

    public function test_authenticated_staff_user_passes_authorization(): void
    {
        // Mock the AIChatService to avoid hitting OpenAI library in this controller test
        $chatServiceMock = $this->createMock(AIChatService::class);
        $chatServiceMock->method('getOrCreateConversation')->willReturn(
            \App\Models\Conversation::create([
                'user_id' => $this->staffUser->id,
                'title'   => 'Test Conversation',
            ])
        );
        $chatServiceMock->method('getHistory')->willReturn([]);
        $chatServiceMock->method('streamChat')->willReturnCallback(function ($history, $onChunk) {
            $onChunk('Hello staff');
        });

        $this->app->instance(AIChatService::class, $chatServiceMock);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->postJson('/api/ai/chat/stream', [
                'message' => 'Hello assistant',
            ]);

        $response->assertStatus(200);
    }

    public function test_pricing_context_guard_blocks_pricing_parameters(): void
    {
        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->postJson('/api/ai/chat/stream', [
                'message' => 'Hello assistant',
                'price'   => 100.00, // Pricing parameter — must be blocked by code guard
            ]);

        // Code guard throws LogicException, which Laravel converts to JSON error in test/dev
        $response->assertStatus(500);
        $this->assertStringContainsString('pricing or product context', $response->json('message'));
    }
}
