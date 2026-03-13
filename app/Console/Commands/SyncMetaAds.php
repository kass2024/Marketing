<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\MetaAdsService;
use App\Models\Ad;

class SyncMetaAds extends Command
{
    /*
    |--------------------------------------------------------------------------
    | Command Signature
    |--------------------------------------------------------------------------
    */

    protected $signature = 'meta:sync-ads';

    /*
    |--------------------------------------------------------------------------
    | Description
    |--------------------------------------------------------------------------
    */

    protected $description = 'Sync Meta Ads, Insights and enforce daily budget';

    protected MetaAdsService $meta;

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    public function __construct(MetaAdsService $meta)
    {
        parent::__construct();
        $this->meta = $meta;
    }

    /*
    |--------------------------------------------------------------------------
    | Handle Command
    |--------------------------------------------------------------------------
    */

    public function handle()
    {
        $this->info('Starting Meta Ads Sync...');
        Log::info('META_SYNC_STARTED');

        try {

            $response = $this->meta->getAds();

            if (empty($response['data'])) {

                $this->warn('No ads returned from Meta.');
                Log::warning('META_SYNC_EMPTY_RESULT');

                return Command::SUCCESS;
            }

            $count = 0;

            foreach ($response['data'] as $ad) {

                $metaId = $ad['id'] ?? null;

                if (!$metaId) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Create or Update Local Ad
                |--------------------------------------------------------------------------
                */

                $record = Ad::updateOrCreate(

                    ['meta_ad_id' => $metaId],

                    [
                        'name' => $ad['name'] ?? 'Unnamed Ad',
                        'status' => $ad['status'] ?? 'PAUSED',
                        'adset_id' => $ad['adset_id'] ?? null,
                        'creative_id' => $ad['creative']['id'] ?? null
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | Fetch Insights
                |--------------------------------------------------------------------------
                */

                $insights = $this->meta->getInsights($metaId);

                $impressions = 0;
                $clicks = 0;
                $spend = 0;

                if (isset($insights['data'][0])) {

                    $row = $insights['data'][0];

                    $impressions = $row['impressions'] ?? 0;
                    $clicks = $row['clicks'] ?? 0;
                    $spend = $row['spend'] ?? 0;
                }

                /*
                |--------------------------------------------------------------------------
                | Daily Budget Guard
                |--------------------------------------------------------------------------
                */

                $today = now()->toDateString();

                if ($record->spend_date !== $today) {

                    $record->daily_spend = 0;
                    $record->spend_date = $today;
                }

                $spentToday = $spend - $record->spend;

                if ($spentToday < 0) {
                    $spentToday = 0;
                }

                $record->daily_spend += $spentToday;

                if ($record->daily_spend >= $record->daily_budget) {

                    $this->meta->updateAd(
                        $metaId,
                        ['status' => 'PAUSED']
                    );

                    $record->status = 'PAUSED';

                    Log::info('AD_AUTO_PAUSED_DAILY_BUDGET', [

                        'meta_ad_id' => $metaId,
                        'daily_spend' => $record->daily_spend,
                        'daily_budget' => $record->daily_budget

                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Calculate CTR
                |--------------------------------------------------------------------------
                */

                $ctr = $impressions > 0
                    ? ($clicks / $impressions) * 100
                    : 0;

                /*
                |--------------------------------------------------------------------------
                | Update Local Record
                |--------------------------------------------------------------------------
                */

                $record->update([

                    'status' => $record->status ?? ($ad['status'] ?? 'PAUSED'),

                    'impressions' => $impressions,

                    'clicks' => $clicks,

                    'spend' => $spend,

                    'ctr' => $ctr,

                    'daily_spend' => $record->daily_spend,

                    'spend_date' => $record->spend_date

                ]);

                Log::info('META_AD_SYNCED', [

                    'meta_ad_id' => $metaId,
                    'name' => $record->name,
                    'spend' => $spend

                ]);

                $count++;
            }

            $this->info("Synced {$count} ads.");
            Log::info("META_SYNC_COMPLETED", ['count'=>$count]);

            return Command::SUCCESS;

        } catch (\Throwable $e) {

            Log::error('META_SYNC_FAILED', [

                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()

            ]);

            $this->error('Meta Ads sync failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}