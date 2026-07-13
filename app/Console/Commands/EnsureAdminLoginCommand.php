<?php

namespace App\Console\Commands;

use Database\Seeders\AdminUserSeeder;
use Illuminate\Console\Command;

class EnsureAdminLoginCommand extends Command
{
    protected $signature = 'users:ensure-admin
        {--email= : Override admin email}
        {--password= : Override admin password}';

    protected $description = 'Create or update the platform super-admin login';

    public function handle(): int
    {
        $email = (string) ($this->option('email') ?: AdminUserSeeder::ADMIN_EMAIL);
        $password = (string) ($this->option('password') ?: AdminUserSeeder::ADMIN_PASSWORD);

        // Temporarily override seeder constants via direct update
        $user = \App\Models\User::query()->where('email', $email)->first()
            ?? \App\Models\User::query()->where('role', 'super_admin')->orderBy('id')->first();

        $payload = [
            'name' => 'Parrot Canada Admin',
            'email' => $email,
            'password' => \Illuminate\Support\Facades\Hash::make($password),
            'role' => 'super_admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ];

        if ($user) {
            $user->forceFill($payload)->save();
            $this->info("Updated super admin → {$email}");
        } else {
            \App\Models\User::create($payload);
            $this->info("Created super admin → {$email}");
        }

        $this->line("Password set. Login with: {$email}");

        return self::SUCCESS;
    }
}
