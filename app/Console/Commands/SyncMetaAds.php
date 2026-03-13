<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use App\Services\MetaAdsService;
use App\Models\Ad;
use App\Models\AdSet;

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

    protected $description = 'Synchronize Meta Ads performance, insights and enforce daily budgets';

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
    | Main Handler
    |--------------------------------------------------------------------------
    */

    public function handle()
    {
        $this->info('Starting Meta Ads Sync...');
        Log::info('META_SYNC_STARTED');

        try {

            /*
            |--------------------------------------------------------------------------
            | Fetch Ads From Meta
            |--------------------------------------------------------------------------
            */

            $response = $this->meta->getAds();

            if (empty($response['data'])) {

                $this->warn('No ads returned from Meta.');
                Log::warning('META_SYNC_EMPTY_RESULT');

                return Command::SUCCESS;
            }

            $count = 0;

            foreach ($response['data'] as $ad) {

                $metaAdId = $ad['id'] ?? null;

                if (!$metaAdId) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Resolve Local AdSet ID
                |--------------------------------------------------------------------------
                */

                $metaAdsetId = $ad['adset_id'] ?? null;
                $localAdsetId = null;

                if ($metaAdsetId) {

                    $adset = AdSet::where('meta_id', $metaAdsetId)->first();

                    if ($adset) {
                        $localAdsetId = $adset->id;
                    } else {

                        Log::warning('META_ADSET_NOT_FOUND', [
                            'meta_adset_id' => $metaAdsetId
                        ]);
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Create / Update Ad Record
                |--------------------------------------------------------------------------
                */

                $record = Ad::updateOrCreate(

                    ['meta_ad_id' => $metaAdId],

                    [
                        'name' => $ad['name'] ?? 'Unnamed Ad',
                        'status' => $ad['status'] ?? 'PAUSED',
                        'adset_id' => $localAdsetId
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | Fetch Insights
                |--------------------------------------------------------------------------
                */

                $insights = $this->meta->getInsights($metaAdId);

                $impressions = 0;
                $clicks = 0;
                $spend = 0;

                if (!empty($insights['data'][0])) {

                    $row = $insights['data'][0];

                    $impressions = (int) ($row['impressions'] ?? 0);
                    $clicks = (int) ($row['clicks'] ?? 0);
                    $spend = (float) ($row['spend'] ?? 0);
                }

                /*
                |--------------------------------------------------------------------------
                | Daily Budget Guard
                |--------------------------------------------------------------------------
                */

                $today = now()->toDateString();

                if (!$record->spend_date || $record->spend_date !== $today) {

                    $record->daily_spend = 0;
                    $record->spend_date = $today;
                }

                $spentToday = $spend - ($record->spend ?? 0);

                if ($spentToday < 0) {
                    $spentToday = 0;
                }

                $record->daily_spend += $spentToday;

                /*
                |--------------------------------------------------------------------------
                | Enforce Daily Budget
                |--------------------------------------------------------------------------
                */

                if ($record->daily_spend >= $record->daily_budget) {

                    $this->meta->updateAd(
                        $metaAdId,
                        ['status' => 'PAUSED']
                    );

                    $record->status = 'PAUSED';

                    Log::info('AD_AUTO_PAUSED_DAILY_BUDGET', [

                        'meta_ad_id' => $metaAdId,
                        'daily_spend' => $record->daily_spend,
                        'daily_budget' => $record->daily_budget

                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Calculate CTR
                |--------------------------------------------------------------------------
                */

                $ctr = 0;

                if ($impressions > 0) {
                    $ctr = ($clicks / $impressions) * 100;
                }

                /*
                |--------------------------------------------------------------------------
                | Update Metrics
                |--------------------------------------------------------------------------
                */

                $record->update([

                    'status' => $record->status ?? ($ad['status'] ?? 'PAUSED'),

                    'impressions' => $impressions,

                    'clicks' => $clicks,

                    'spend' => $spend,

                    'ctr' => round($ctr, 2),

                    'daily_spend' => $record->daily_spend,

                    'spend_date' => $record->spend_date

                ]);

                Log::info('META_AD_SYNCED', [

                    'meta_ad_id' => $metaAdId,
                    'name' => $record->name,
                    'spend' => $spend,
                    'clicks' => $clicks,
                    'impressions' => $impressions

                ]);

                $count++;
            }

            /*
            |--------------------------------------------------------------------------
            | Sync Completed
            |--------------------------------------------------------------------------
            */

            $this->info("Synced {$count} ads.");
            Log::info('META_SYNC_COMPLETED', ['count' => $count]);

            return Command::SUCCESS;

        } catch (\Throwable $e) {

            Log::error('META_SYNC_FAILED', [

                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()

            ]);

            $this->error('Meta Ads sync failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}