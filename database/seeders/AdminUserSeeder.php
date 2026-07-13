<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'infos@visaconsultantcanada.com';

    public const ADMIN_PASSWORD = 'VisaCanada2026!';

    public function run(): void
    {
        $user = User::query()->where('email', self::ADMIN_EMAIL)->first()
            ?? User::query()->where('role', 'super_admin')->orderBy('id')->first();

        $payload = [
            'name' => 'Parrot Canada Admin',
            'email' => self::ADMIN_EMAIL,
            'password' => Hash::make(self::ADMIN_PASSWORD),
            'role' => 'super_admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ];

        if ($user) {
            $user->forceFill($payload)->save();
            $this->command?->info('Updated super admin: '.self::ADMIN_EMAIL);
        } else {
            User::create($payload);
            $this->command?->info('Created super admin: '.self::ADMIN_EMAIL);
        }
    }
}
