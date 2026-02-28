<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Super Admin if not exists
        User::firstOrCreate(
            ['email' => 'admin@example.com'], // Check by email
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Admin123!'),
                'role' => 'super_admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
    }
}