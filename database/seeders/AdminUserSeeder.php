<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Super Admin',
            'email' => 'ujeanmethode@gmail.com',
            'password' => Hash::make('Claire2023@'),
            'role' => 'super_admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }
}