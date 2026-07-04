<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private Role $salesAgentRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Spatie Roles
        $this->adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->salesAgentRole = Role::firstOrCreate(['name' => 'sales-agent', 'guard_name' => 'web']);
    }

    public function test_user_can_register_with_role(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'New Agent',
            'email' => 'agent@eckox.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'sales-agent',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'roles'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'agent@eckox.com',
        ]);

        $user = User::where('email', 'agent@eckox.com')->first();
        $this->assertTrue($user->hasRole('sales-agent'));
    }

    public function test_user_cannot_register_with_invalid_data(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
            'role' => 'invalid-role',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@eckox.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole($this->adminRole);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@eckox.com',
            'password' => 'password123',
            'device_name' => 'iPhone',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'roles'],
                'token',
            ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@eckox.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@eckox.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials',
            ]);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@eckox.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole($this->adminRole);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'admin@eckox.com');
    }

    public function test_unauthenticated_user_cannot_get_profile(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_update_profile(): void
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@eckox.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/auth/profile', [
            'name' => 'Updated Admin',
            'email' => 'updatedadmin@eckox.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.name', 'Updated Admin')
            ->assertJsonPath('user.email', 'updatedadmin@eckox.com');

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@eckox.com',
            'password' => Hash::make('password123'),
        ]);
        $token = $user->createToken('test_token');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Logged out successfully',
            ]);

        $this->assertCount(0, $user->tokens);
    }
}
