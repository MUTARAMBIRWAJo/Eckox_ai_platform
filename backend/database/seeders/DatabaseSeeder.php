<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Spatie Roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $adminRole      = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $managerRole    = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $salesAgentRole = Role::firstOrCreate(['name' => 'sales-agent', 'guard_name' => 'web']);

        // 2. Create Default/System Roles
        $superAdmin = User::firstOrCreate([
            'email' => 'superadmin@eckox.com',
        ], [
            'name' => 'Super Admin',
            'password' => Hash::make('password'),
        ]);
        $superAdmin->assignRole($superAdminRole);

        $admin = User::firstOrCreate([
            'email' => 'admin@eckox.com',
        ], [
            'name' => 'Admin User',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole($adminRole);

        $manager = User::firstOrCreate([
            'email' => 'manager@eckox.com',
        ], [
            'name' => 'Manager User',
            'password' => Hash::make('password'),
        ]);
        $manager->assignRole($managerRole);

        $agent = User::firstOrCreate([
            'email' => 'agent@eckox.com',
        ], [
            'name' => 'Sales Agent User',
            'password' => Hash::make('password'),
        ]);
        $agent->assignRole($salesAgentRole);

        // 3. Seed 10 additional test users with emails and passwords
        $testUsers = [
            ['name' => 'John Doe', 'email' => 'john.doe@eckox.com'],
            ['name' => 'Jane Smith', 'email' => 'jane.smith@eckox.com'],
            ['name' => 'Alice Johnson', 'email' => 'alice.johnson@eckox.com'],
            ['name' => 'Bob Brown', 'email' => 'bob.brown@eckox.com'],
            ['name' => 'Charlie Davis', 'email' => 'charlie.davis@eckox.com'],
            ['name' => 'Diana Evans', 'email' => 'diana.evans@eckox.com'],
            ['name' => 'Ethan Foster', 'email' => 'ethan.foster@eckox.com'],
            ['name' => 'Fiona Green', 'email' => 'fiona.green@eckox.com'],
            ['name' => 'George Harris', 'email' => 'george.harris@eckox.com'],
            ['name' => 'Hannah Martin', 'email' => 'hannah.martin@eckox.com'],
        ];

        foreach ($testUsers as $user) {
            $createdUser = User::firstOrCreate([
                'email' => $user['email'],
            ], [
                'name' => $user['name'],
                'password' => Hash::make('password'),
            ]);
            
            // Assign default sales-agent role to the seeded test users
            $createdUser->assignRole($salesAgentRole);
        }
    }
}
