<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\MetaAdsService;
use App\Models\AdAccount;

class SyncMetaAccounts extends Command
{
    /**
     * Command signature
     */
    protected $signature = 'meta:sync-accounts';

    /**
     * Description
     */
    protected $description = 'Sync Meta advertising accounts from Meta Marketing API';

    protected MetaAdsService $meta;

    public function __construct(MetaAdsService $meta)
    {
        parent::__construct();

        $this->meta = $meta;
    }

    /**
     * Execute command
     */
    public function handle()
    {
        $this->info('Starting Meta Ad Account Sync...');

        Log::info('Meta Account Sync Job Started');

        try {

            $response = $this->meta->getAdAccounts();

            if (empty($response['data'])) {

                $this->warn('No Meta ad accounts found.');

                Log::warning('Meta returned empty account list');

                return Command::SUCCESS;
            }

            $count = 0;

            foreach ($response['data'] as $account) {

                $metaId = $account['id'] ?? null;

                if (!$metaId) {
                    continue;
                }

                $statusMap = [
                    1 => 'ACTIVE',
                    2 => 'DISABLED',
                    3 => 'UNSETTLED',
                    7 => 'PENDING'
                ];

                $status = $statusMap[$account['account_status']] ?? 'UNKNOWN';

                $record = AdAccount::updateOrCreate(

                    ['meta_id' => $metaId],

                    [
                        'ad_account_id' => $metaId,
                        'name' => AdAccount::normalizeSyncedName($account['name'] ?? null),
                        'currency' => $account['currency'] ?? null,
                        'account_status' => $status
                    ]
                );

                $count++;

                Log::info('Meta Account Synced', [
                    'meta_id' => $metaId,
                    'name' => $record->name,
                    'status' => $status
                ]);
            }

            $this->info("Synced {$count} Meta ad accounts.");

            Log::info("Meta Account Sync Completed ({$count})");

            return Command::SUCCESS;

        } catch (\Throwable $e) {

            Log::error('Meta Account Sync Failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->error('Meta sync failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}