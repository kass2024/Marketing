<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\MetaAdsService;
use App\Models\Ad;
use App\Models\AdSet;

class SyncMetaAds extends Command
{
    protected $signature = 'meta:sync-ads';

    protected $description = 'Synchronize Meta Ads metrics and enforce daily budgets';

    protected MetaAdsService $meta;

    public function __construct(MetaAdsService $meta)
    {
        parent::__construct();
        $this->meta = $meta;
    }

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

            foreach ($response['data'] as $metaAd) {

                $metaAdId = $metaAd['id'] ?? null;

                if (!$metaAdId) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Resolve AdSet
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
                | Create or Update Local Ad
                |--------------------------------------------------------------------------
                */

                $ad = Ad::updateOrCreate(

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
                | DAILY SPEND LOGIC (FIXED)
                |--------------------------------------------------------------------------
                */

                $today = now()->toDateString();

                if (!$ad->spend_date || $ad->spend_date !== $today) {

                    $ad->daily_spend = 0;
                    $ad->spend_date = $today;
                }

                $previousSpend = $ad->spend ?? 0;

                $increment = $spend - $previousSpend;

                if ($increment < 0) {
                    $increment = 0;
                }

                $ad->daily_spend += $increment;

                $todaySpend = $ad->daily_spend;

                /*
                |--------------------------------------------------------------------------
                | Budget Guard
                |--------------------------------------------------------------------------
                */

                if (
                    $ad->pause_reason !== 'manual' &&
                    $ad->daily_budget &&
                    $todaySpend >= $ad->daily_budget &&
                    $ad->status !== 'PAUSED'
                ) {

                    $this->meta->updateAd(
                        $metaAdId,
                        ['status' => 'PAUSED']
                    );

                    $ad->status = 'PAUSED';
                    $ad->pause_reason = 'budget_limit';

                    Log::info('AD_AUTO_PAUSED_DAILY_BUDGET', [

                        'meta_ad_id' => $metaAdId,
                        'daily_spend' => $todaySpend,
                        'daily_budget' => $ad->daily_budget

                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | CTR
                |--------------------------------------------------------------------------
                */

                $ctr = 0;

                if ($impressions > 0) {
                    $ctr = round(($clicks / $impressions) * 100, 2);
                }

                /*
                |--------------------------------------------------------------------------
                | Update Metrics
                |--------------------------------------------------------------------------
                */

                $ad->update([

                    'status' => $ad->status ?? ($metaAd['status'] ?? 'PAUSED'),
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'spend' => $spend,
                    'ctr' => $ctr,
                    'daily_spend' => $ad->daily_spend,
                    'spend_date' => $today

                ]);

                Log::info('META_AD_SYNCED', [

                    'meta_ad_id' => $metaAdId,
                    'lifetime_spend' => $spend,
                    'daily_spend' => $ad->daily_spend

                ]);

                $count++;
            }

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