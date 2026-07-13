<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use App\Services\Platform\PlatformBootstrapService;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', 'super_admin')->first();

        if (! $admin) {
            $this->command?->warn('No super_admin user — run AdminUserSeeder first.');

            return;
        }

        app(PlatformBootstrapService::class)->syncFromEnv($admin->id);
    }
}
