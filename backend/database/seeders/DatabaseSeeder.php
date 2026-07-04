<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Spatie Roles
        $superAdminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $managerRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $salesAgentRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'sales-agent', 'guard_name' => 'web']);

        // Create Super Admin User
        $superAdmin = User::firstOrCreate([
            'email' => 'superadmin@eckox.com',
        ], [
            'name' => 'Super Admin',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);
        $superAdmin->assignRole($superAdminRole);

        // Create Admin User
        $admin = User::firstOrCreate([
            'email' => 'admin@eckox.com',
        ], [
            'name' => 'Admin User',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);
        $admin->assignRole($adminRole);

        // Create Manager User
        $manager = User::firstOrCreate([
            'email' => 'manager@eckox.com',
        ], [
            'name' => 'Manager User',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);
        $manager->assignRole($managerRole);

        // Create Sales Agent User
        $agent = User::firstOrCreate([
            'email' => 'agent@eckox.com',
        ], [
            'name' => 'Sales Agent User',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);
        $agent->assignRole($salesAgentRole);
    }
}
