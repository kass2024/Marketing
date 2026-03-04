<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\MetaAdsService;
use App\Models\Ad;

class SyncMetaAds extends Command
{
    /**
     * Command signature
     */
    protected $signature = 'meta:sync-ads';

    /**
     * Command description
     */
    protected $description = 'Sync Ads from Meta Marketing API';

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
        $this->info('Starting Meta Ads Sync...');
        Log::info('Meta Ads Sync Started');

        try {

            $response = $this->meta->getAds();

            if (empty($response['data'])) {

                $this->warn('No ads returned from Meta.');
                Log::warning('Meta Ads API returned empty result');

                return Command::SUCCESS;
            }

            $count = 0;

            foreach ($response['data'] as $ad) {

                $metaId = $ad['id'] ?? null;

                if (!$metaId) {
                    continue;
                }

                $record = Ad::updateOrCreate(

                    ['meta_id' => $metaId],

                    [
                        'name' => $ad['name'] ?? 'Unnamed Ad',
                        'status' => $ad['status'] ?? 'UNKNOWN',
                        'adset_id' => $ad['adset_id'] ?? null,
                        'creative_id' => $ad['creative']['id'] ?? null
                    ]
                );

                $count++;

                Log::info('Meta Ad Synced', [
                    'meta_id' => $metaId,
                    'name' => $record->name,
                    'status' => $record->status
                ]);
            }

            $this->info("Synced {$count} ads.");
            Log::info("Meta Ads Sync Completed ({$count})");

            return Command::SUCCESS;

        } catch (\Throwable $e) {

            Log::error('Meta Ads Sync Failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->error('Meta Ads sync failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}