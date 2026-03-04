<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\MetaAdsService;
use App\Models\Insight;

class SyncMetaInsights extends Command
{
    /**
     * Command signature
     */
    protected $signature = 'meta:sync-insights';

    /**
     * Command description
     */
    protected $description = 'Sync Meta Ads performance insights';

    protected MetaAdsService $meta;

    public function __construct(MetaAdsService $meta)
    {
        parent::__construct();
        $this->meta = $meta;
    }

    /**
     * Execute the console command
     */
    public function handle()
    {
        $this->info('Starting Meta Insights Sync...');
        Log::info('Meta Insights Sync Started');

        try {

            $response = $this->meta->getInsights();

            if (empty($response['data'])) {

                $this->warn('No insights returned from Meta.');
                Log::warning('Meta Insights API returned empty data');

                return Command::SUCCESS;
            }

            $count = 0;

            foreach ($response['data'] as $row) {

                $campaign = $row['campaign_name'] ?? 'Unknown';

                $record = Insight::updateOrCreate(

                    [
                        'campaign_name' => $campaign,
                        'date' => now()->toDateString()
                    ],

                    [
                        'impressions' => $row['impressions'] ?? 0,
                        'clicks' => $row['clicks'] ?? 0,
                        'spend' => $row['spend'] ?? 0,
                        'ctr' => $row['ctr'] ?? 0,
                        'cpc' => $row['cpc'] ?? 0
                    ]
                );

                $count++;

                Log::info('Meta Insight Synced', [
                    'campaign' => $campaign,
                    'spend' => $record->spend,
                    'clicks' => $record->clicks
                ]);
            }

            $this->info("Synced {$count} insight records.");

            Log::info("Meta Insights Sync Completed ({$count})");

            return Command::SUCCESS;

        } catch (\Throwable $e) {

            Log::error('Meta Insights Sync Failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->error('Meta insights sync failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}