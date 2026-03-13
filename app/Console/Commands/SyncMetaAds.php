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

            foreach ($response['data'] as $metaAd) {

                $metaAdId = $metaAd['id'] ?? null;

                if (!$metaAdId) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Resolve Local AdSet
                |--------------------------------------------------------------------------
                */

                $localAdsetId = null;

                if (!empty($metaAd['adset_id'])) {

                    $adset = AdSet::where('meta_id', $metaAd['adset_id'])->first();

                    if ($adset) {
                        $localAdsetId = $adset->id;
                    } else {

                        Log::warning('META_ADSET_NOT_FOUND', [
                            'meta_adset_id' => $metaAd['adset_id']
                        ]);
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Create / Update Local Ad
                |--------------------------------------------------------------------------
                */

                $record = Ad::updateOrCreate(

                    ['meta_ad_id' => $metaAdId],

                    [
                        'name' => $metaAd['name'] ?? 'Unnamed Ad',
                        'status' => $metaAd['status'] ?? 'PAUSED',
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
                | Daily Spend Handling
                |--------------------------------------------------------------------------
                */

                $today = now()->toDateString();

                if (!$record->spend_date || $record->spend_date !== $today) {

                    $record->daily_spend = $spend;
                    $record->spend_date = $today;

                } else {

                    $record->daily_spend = $spend;

                }

                /*
                |--------------------------------------------------------------------------
                | Enforce Daily Budget (Skip manual pauses)
                |--------------------------------------------------------------------------
                */

                if (
                    $record->pause_reason !== 'manual' &&
                    $record->daily_spend >= $record->daily_budget
                ) {

                    $this->meta->updateAd(
                        $metaAdId,
                        ['status' => 'PAUSED']
                    );

                    $record->status = 'PAUSED';
                    $record->pause_reason = 'budget_limit';

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

                $ctr = $impressions > 0
                    ? round(($clicks / $impressions) * 100, 2)
                    : 0;

                /*
                |--------------------------------------------------------------------------
                | Update Local Metrics
                |--------------------------------------------------------------------------
                */

                $record->update([

                    'status' => $record->status ?? ($metaAd['status'] ?? 'PAUSED'),

                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'spend' => $spend,
                    'ctr' => $ctr,

                    'daily_spend' => $record->daily_spend,
                    'spend_date' => $record->spend_date

                ]);

                Log::info('META_AD_SYNCED', [

                    'meta_ad_id' => $metaAdId,
                    'name' => $record->name,
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'spend' => $spend

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

            $this->error('Meta Ads sync failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}