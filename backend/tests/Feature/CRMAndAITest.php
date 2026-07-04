<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateStreamedResponse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CRMAndAITest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private Role $managerRole;
    private Role $agentRole;

    private User $admin;
    private User $manager;
    private User $agent1;
    private User $agent2;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Roles
        $this->adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $this->agentRole = Role::firstOrCreate(['name' => 'sales-agent', 'guard_name' => 'web']);

        // 2. Create Users
        $this->admin = User::factory()->create();
        $this->admin->assignRole($this->adminRole);

        $this->manager = User::factory()->create();
        $this->manager->assignRole($this->managerRole);

        $this->agent1 = User::factory()->create();
        $this->agent1->assignRole($this->agentRole);

        $this->agent2 = User::factory()->create();
        $this->agent2->assignRole($this->agentRole);
    }

    public function test_admin_can_view_all_leads(): void
    {
        Lead::factory()->create(['assigned_to' => $this->agent1->id]);
        Lead::factory()->create(['assigned_to' => $this->agent2->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/leads');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_manager_can_view_all_leads(): void
    {
        Lead::factory()->create(['assigned_to' => $this->agent1->id]);
        Lead::factory()->create(['assigned_to' => $this->agent2->id]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/leads');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_sales_agent_can_only_view_assigned_leads(): void
    {
        Lead::factory()->create(['assigned_to' => $this->agent1->id]);
        Lead::factory()->create(['assigned_to' => $this->agent2->id]);

        $response = $this->actingAs($this->agent1, 'sanctum')
            ->getJson('/api/leads');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_lead_crud_operations(): void
    {
        // 1. Create
        $response = $this->actingAs($this->agent1, 'sanctum')
            ->postJson('/api/leads', [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '12345678',
                'status' => 'new',
            ]);

        $response->assertStatus(201);
        $leadId = $response->json('lead.id');

        $this->assertDatabaseHas('leads', [
            'id' => $leadId,
            'name' => 'John Doe',
            'assigned_to' => $this->agent1->id,
        ]);

        // 2. Read details
        $response = $this->actingAs($this->agent1, 'sanctum')
            ->getJson("/api/leads/{$leadId}");

        $response->assertStatus(200)
            ->assertJsonPath('name', 'John Doe');

        // 3. Update details (new -> contacted -> qualified)
        $response = $this->actingAs($this->agent1, 'sanctum')
            ->patchJson("/api/leads/{$leadId}", [
                'status' => 'contacted',
            ]);
        $response->assertStatus(200);

        $response = $this->actingAs($this->agent1, 'sanctum')
            ->patchJson("/api/leads/{$leadId}", [
                'status' => 'qualified',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('leads', [
            'id' => $leadId,
            'status' => 'qualified',
        ]);
    }

    public function test_sales_agent_cannot_reassign_lead(): void
    {
        $lead = Lead::factory()->create(['assigned_to' => $this->agent1->id]);

        $response = $this->actingAs($this->agent1, 'sanctum')
            ->patchJson("/api/leads/{$lead->id}", [
                'assigned_to' => $this->agent2->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_sales_agent_cannot_delete_lead(): void
    {
        $lead = Lead::factory()->create(['assigned_to' => $this->agent1->id]);

        $response = $this->actingAs($this->agent1, 'sanctum')
            ->deleteJson("/api/leads/{$lead->id}");

        $response->assertStatus(403);
    }

    public function test_manager_can_delete_lead(): void
    {
        $lead = Lead::factory()->create(['assigned_to' => $this->agent1->id]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->deleteJson("/api/leads/{$lead->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
    }

    public function test_can_log_lead_activity(): void
    {
        $lead = Lead::factory()->create(['assigned_to' => $this->agent1->id]);

        $response = $this->actingAs($this->agent1, 'sanctum')
            ->postJson("/api/leads/{$lead->id}/activity", [
                'type' => 'call',
                'description' => 'Discussed proposal details.',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => 'call',
            'description' => 'Discussed proposal details.',
        ]);
    }

    public function test_ai_chat_streaming_endpoint(): void
    {
        $clientMock = \Mockery::mock();
        $chatMock = \Mockery::mock();

        $response1 = (object)[
            'choices' => [
                (object)[
                    'delta' => (object)['content' => 'Hello']
                ]
            ]
        ];
        $response2 = (object)[
            'choices' => [
                (object)[
                    'delta' => (object)['content' => ' world!']
                ]
            ]
        ];

        $chatMock->shouldReceive('createStreamed')
            ->once()
            ->andReturn([$response1, $response2]);

        $clientMock->shouldReceive('chat')
            ->once()
            ->andReturn($chatMock);

        $this->app->instance('openai', $clientMock);
        $this->app->instance(\OpenAI\Client::class, $clientMock);

        $response = $this->actingAs($this->agent1, 'sanctum')
            ->postJson('/api/ai/chat/stream', [
                'message' => 'Hi AI',
            ]);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString('Hello', $content);
        $this->assertStringContainsString('world!', $content);
        $this->assertStringContainsString('done', $content);

        // Check if database stored conversation and messages
        $this->assertDatabaseHas('conversations', [
            'user_id' => $this->agent1->id,
        ]);

        $this->assertDatabaseHas('messages', [
            'role' => 'user',
            'content' => 'Hi AI',
        ]);

        $this->assertDatabaseHas('messages', [
            'role' => 'assistant',
            'content' => 'Hello world!',
        ]);
    }

    public function test_manager_cannot_delete_admin_lead(): void
    {
        $lead = Lead::factory()->create(['assigned_to' => $this->admin->id]);

        $response = $this->actingAs($this->manager, 'sanctum')
            ->deleteJson("/api/leads/{$lead->id}");

        $response->assertStatus(403);
    }

    public function test_invalid_lead_status_transition_is_blocked(): void
    {
        $lead = Lead::factory()->create([
            'assigned_to' => $this->agent1->id,
            'status' => 'new',
        ]);

        // 'new' directly to 'qualified' or 'lost' is allowed?
        // Let's check: allowed new -> contacted only.
        // Direct transition new -> qualified should fail.
        $response = $this->actingAs($this->agent1, 'sanctum')
            ->patchJson("/api/leads/{$lead->id}", [
                'status' => 'qualified',
            ]);

        $response->assertStatus(422);

        // Valid transition new -> contacted
        $response = $this->actingAs($this->agent1, 'sanctum')
            ->patchJson("/api/leads/{$lead->id}", [
                'status' => 'contacted',
            ]);
        $response->assertStatus(200);
    }

    public function test_role_escalation_prevention(): void
    {
        // Public registration requesting 'admin' role should fail
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Hacker Admin',
            'email' => 'hacker@eckox.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertStatus(422);
    }

    public function test_ai_streaming_stress_test_large_tokens(): void
    {
        $clientMock = \Mockery::mock();
        $chatMock = \Mockery::mock();

        // Simulate 50 small chunks
        $chunks = [];
        for ($i = 0; $i < 50; $i++) {
            $chunks[] = (object)[
                'choices' => [
                    (object)[
                        'delta' => (object)['content' => 'token' . $i]
                    ]
                ]
            ];
        }

        $chatMock->shouldReceive('createStreamed')
            ->once()
            ->andReturn($chunks);

        $clientMock->shouldReceive('chat')
            ->once()
            ->andReturn($chatMock);

        $this->app->instance('openai', $clientMock);
        $this->app->instance(\OpenAI\Client::class, $clientMock);

        $response = $this->actingAs($this->agent1, 'sanctum')
            ->postJson('/api/ai/chat/stream', [
                'message' => 'Generate large response',
            ]);

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('token0', $content);
        $this->assertStringContainsString('token49', $content);
        $this->assertStringContainsString('done', $content);
    }
}
