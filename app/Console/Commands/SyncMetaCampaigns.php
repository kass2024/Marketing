<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\MetaAdsService;
use App\Models\Campaign;

class SyncMetaCampaigns extends Command
{
    /**
     * Command name used in Artisan
     */
    protected $signature = 'meta:sync-campaigns';

    /**
     * Command description
     */
    protected $description = 'Sync Meta campaigns from Meta API';

    protected MetaAdsService $service;

    public function __construct(MetaAdsService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle(): int
    {
        $this->info('Starting Meta Campaign Sync...');

        Log::info('Meta Campaign Sync Started');

        try {

            $response = $this->service->getCampaigns();

            if (!isset($response['data']) || empty($response['data'])) {

                $this->warn('Meta returned no campaigns.');

                Log::warning('Meta Campaign Sync: empty response');

                return Command::SUCCESS;
            }

            $count = 0;

            foreach ($response['data'] as $campaign) {

                if (empty($campaign['id'])) {
                    continue;
                }

                $record = Campaign::updateOrCreate(
                    ['meta_id' => $campaign['id']],
                    [
                        'name' => $campaign['name'] ?? 'Unnamed Campaign',
                        'status' => $campaign['status'] ?? 'UNKNOWN',
                        'objective' => $campaign['objective'] ?? null
                    ]
                );

                $count++;

                $this->line("Synced: {$record->name}");
            }

            $this->info("Meta Campaign Sync Completed ({$count} campaigns)");

            Log::info("Meta Campaign Sync Completed ({$count})");

            return Command::SUCCESS;

        } catch (\Throwable $e) {

            Log::error('Meta Campaign Sync Failed', [
                'error' => $e->getMessage()
            ]);

            $this->error('Meta Campaign Sync Failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}