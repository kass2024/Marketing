<?php

namespace App\Console\Commands;

use App\Models\AdAccount;
use App\Models\Campaign;
use App\Services\MetaAdsService;
use App\Support\TenantScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncMetaCampaigns extends Command
{
    protected $signature = 'meta:sync-campaigns';

    protected $description = 'Sync Meta campaigns from Meta API into local campaigns (delivery-ready status)';

    public function __construct(protected MetaAdsService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting Meta Campaign Sync...');
        Log::info('Meta Campaign Sync Started');

        try {
            $account = TenantScope::resolveAdAccount() ?? AdAccount::query()->first();
            if (! $account?->meta_id) {
                $this->error('No Meta ad account connected.');

                return Command::FAILURE;
            }

            $accountId = str_starts_with((string) $account->meta_id, 'act_')
                ? (string) $account->meta_id
                : 'act_'.$account->meta_id;

            $response = $this->service->getCampaigns($accountId);

            if (empty($response['data'])) {
                $this->warn('Meta returned no campaigns.');
                Log::warning('Meta Campaign Sync: empty response');

                return Command::SUCCESS;
            }

            $count = 0;
            foreach ($response['data'] as $campaign) {
                if (empty($campaign['id'])) {
                    continue;
                }

                $existing = Campaign::query()->where('meta_id', $campaign['id'])->first();

                $payload = [
                    'ad_account_id' => $account->id,
                    'name' => $campaign['name'] ?? 'Unnamed Campaign',
                    'status' => Campaign::normalizeStatus($campaign['effective_status'] ?? $campaign['status'] ?? null),
                    'meta_effective_status' => $campaign['effective_status'] ?? $campaign['status'] ?? null,
                    'objective' => $campaign['objective'] ?? null,
                ];

                if (! $existing) {
                    $payload['client_id'] = $account->client_id
                        ?? TenantScope::clientId()
                        ?? \App\Models\Client::query()->value('id');
                }

                if (empty($payload['client_id']) && ! $existing) {
                    $this->warn("Skipped {$campaign['id']} — no client_id available.");
                    continue;
                }

                $record = Campaign::updateOrCreate(
                    ['meta_id' => $campaign['id']],
                    $payload
                );

                $count++;
                $this->line("Synced: {$record->name} [{$record->status}]");
            }

            $this->info("Meta Campaign Sync Completed ({$count} campaigns)");
            Log::info("Meta Campaign Sync Completed ({$count})");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Meta Campaign Sync Failed', ['error' => $e->getMessage()]);
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
