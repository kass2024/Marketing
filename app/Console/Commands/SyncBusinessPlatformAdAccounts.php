<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Support\TenantScope;
use Illuminate\Console\Command;

class SyncBusinessPlatformAdAccounts extends Command
{
    protected $signature = 'business:sync-platform-ad-accounts';

    protected $description = 'Point all business clients at the platform main Meta ad account';

    public function handle(): int
    {
        $platformId = TenantScope::platformAdAccountMetaId();

        if (! $platformId) {
            $this->error('META_AD_ACCOUNT_ID is not configured.');

            return self::FAILURE;
        }

        TenantScope::ensurePlatformAdAccount();

        $updated = Client::query()->update([
            'meta_ad_account_id' => $platformId,
            'meta_ad_account_name' => (string) config('services.meta.ad_account_name', 'Platform Ad Account'),
        ]);

        $this->info("Updated {$updated} business client(s) to use {$platformId}.");

        return self::SUCCESS;
    }
}
