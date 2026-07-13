<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->where('email', 'demo@tenant.example')->exists()) {
            return;
        }

        $user = User::create([
            'name'              => 'Demo Tenant',
            'email'             => 'demo@tenant.example',
            'password'          => Hash::make('DemoTenant2026!'),
            'role'              => 'client',
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        Client::create([
            'user_id'             => $user->id,
            'company_name'        => 'Demo Business',
            'business_email'      => 'demo@tenant.example',
            'subscription_plan'   => Client::PLAN_PRO,
            'subscription_status' => Client::STATUS_ACTIVE,
            'is_platform'         => false,
            'meta_page_id'        => config('platform.meta.page_id'),
            'meta_page_name'      => 'Demo Page (change in profile)',
            'whatsapp_phone_number' => '14385559999',
            'whatsapp_verification_status' => 'verified',
            'whatsapp_verified_name' => 'Demo Business',
            'whatsapp_verified_at' => now(),
            'whatsapp_meta_synced_at' => now(),
        ]);
    }
}
