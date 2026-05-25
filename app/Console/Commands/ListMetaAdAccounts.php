<?php

namespace App\Console\Commands;

use App\Services\MetaAdsService;
use App\Support\TenantScope;
use Illuminate\Console\Command;

class ListMetaAdAccounts extends Command
{
    protected $signature = 'meta:list-ad-accounts';

    protected $description = 'List Meta ad accounts and mark which is reserved for the platform admin';

    public function handle(MetaAdsService $meta): int
    {
        $platformId = TenantScope::platformAdAccountMetaId();

        $this->line('Platform main account (admin only): '.($platformId ?: 'not configured'));
        $this->newLine();

        try {
            $response = $meta->getAdAccounts();
        } catch (\Throwable $e) {
            $this->error('Unable to fetch ad accounts: '.$e->getMessage());

            return self::FAILURE;
        }

        $rows = [];

        foreach ($response['data'] ?? [] as $account) {
            $id = TenantScope::formatMetaAccountId($account['id'] ?? '');
            $rows[] = [
                $id,
                $account['name'] ?? '—',
                $account['currency'] ?? '—',
                TenantScope::isPlatformAdAccount($id) ? 'PLATFORM (admin)' : 'Business OK',
            ];
        }

        if ($rows === []) {
            $this->warn('No ad accounts returned by Meta for this token.');

            return self::SUCCESS;
        }

        $this->table(['Meta ID', 'Name', 'Currency', 'Usage'], $rows);

        $businessCount = count($meta->getBusinessAdAccounts());
        $this->newLine();
        $this->info("Business registration can use {$businessCount} dedicated account(s).");

        if ($businessCount === 0) {
            $this->warn('Add more ad accounts in Meta Business Manager and grant this system user access before businesses can register.');
        }

        return self::SUCCESS;
    }
}
