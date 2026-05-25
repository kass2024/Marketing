<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\User;
use App\Support\TenantScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProvisionBusinessAccount extends Command
{
    protected $signature = 'business:provision
        {email : Business login email}
        {--password= : Login password (min 6 characters)}
        {--name= : Account owner full name}
        {--company= : Business / company name}
        {--page-id= : Facebook Page ID}
        {--page-name= : Facebook Page name}
        {--phone= : Optional phone number}
        {--reset-password : Reset password when the user already exists}';

    protected $description = 'Create or repair a business account linked to a Facebook Page (uses platform main ad account)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $password = (string) ($this->option('password') ?: User::defaultClientPassword());

        if (strlen($password) < 6) {
            $this->error('Password must be at least 6 characters.');

            return self::FAILURE;
        }

        $platformAdAccountId = TenantScope::platformAdAccountMetaId();

        if (! $platformAdAccountId) {
            $this->error('META_AD_ACCOUNT_ID is not configured in .env');

            return self::FAILURE;
        }

        $existing = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($existing && ! $this->option('reset-password')) {
            $this->error("User already exists: {$email}. Pass --reset-password to update credentials.");

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?: $this->ask('Owner full name', 'Business Owner'));
        $company = (string) ($this->option('company') ?: $this->ask('Business name', $name.' Company'));
        $pageId = (string) ($this->option('page-id') ?: $this->ask('Facebook Page ID'));
        $pageName = (string) ($this->option('page-name') ?: $this->ask('Facebook Page name', 'Facebook Page'));

        if ($pageId === '') {
            $this->error('Facebook Page ID is required.');

            return self::FAILURE;
        }

        DB::beginTransaction();

        try {
            if ($existing) {
                $existing->update([
                    'name' => $name,
                    'password' => $password,
                    'role' => User::ROLE_CLIENT,
                    'status' => User::STATUS_ACTIVE,
                ]);

                $user = $existing;

                $client = Client::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'company_name' => $company,
                        'business_email' => $email,
                        'phone' => $this->option('phone'),
                        'subscription_plan' => Client::PLAN_FREE,
                        'subscription_status' => Client::STATUS_ACTIVE,
                    ]
                );
            } else {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'role' => User::ROLE_CLIENT,
                    'status' => User::STATUS_ACTIVE,
                ]);

                $client = Client::create([
                    'user_id' => $user->id,
                    'company_name' => $company,
                    'business_email' => $email,
                    'phone' => $this->option('phone'),
                    'subscription_plan' => Client::PLAN_FREE,
                    'subscription_status' => Client::STATUS_ACTIVE,
                ]);
            }

            $client->update([
                'company_name' => $company,
                'business_email' => $email,
                'phone' => $this->option('phone') ?: $client->phone,
                'meta_page_id' => $pageId,
                'meta_page_name' => $pageName,
                'meta_ad_account_id' => $platformAdAccountId,
                'meta_ad_account_name' => (string) config('services.meta.ad_account_name', 'Platform Ad Account'),
                'subscription_status' => Client::STATUS_ACTIVE,
            ]);

            TenantScope::ensurePlatformAdAccount($pageName);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            $this->error('Failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Business account ready: {$email}");
        $this->line("  Company: {$company}");
        $this->line("  Facebook Page: {$pageName} ({$pageId})");
        $this->line("  Ad account: platform main ({$platformAdAccountId})");
        $this->line('  Login at: '.url('/login'));

        return self::SUCCESS;
    }
}
