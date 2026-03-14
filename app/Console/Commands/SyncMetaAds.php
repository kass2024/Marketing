<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use App\Services\MetaAdsService;

use App\Models\AdAccount;
use App\Models\Campaign;
use App\Models\AdSet;
use App\Models\Ad;

class SyncMetaAds extends Command
{
    protected $signature = 'meta:sync-ads';

    protected $description = 'Synchronize Meta campaigns, adsets, ads and performance metrics';

    protected MetaAdsService $meta;

    public function __construct(MetaAdsService $meta)
    {
        parent::__construct();
        $this->meta = $meta;
    }

    public function handle()
    {
        $this->info('Starting Meta Ads Sync...');

        Log::info('META_SYNC_START', [
            'timestamp' => now()->toDateTimeString()
        ]);

        try {

            /*
            |--------------------------------------------------------------------------
            | Resolve Ad Account
            |--------------------------------------------------------------------------
            */

            $account = AdAccount::first();

            if (!$account) {

                Log::error('META_ACCOUNT_NOT_FOUND');

                $this->error('No Meta Ad Account connected.');

                return Command::FAILURE;
            }

            $accountId = $account->meta_id;

            Log::info('META_ACCOUNT_RESOLVED', [
                'account_id' => $accountId
            ]);

            /*
            |--------------------------------------------------------------------------
            | CAMPAIGNS
            |--------------------------------------------------------------------------
            */

            $campaigns = $this->meta->getCampaigns($accountId);

            foreach ($campaigns['data'] ?? [] as $metaCampaign) {

                Campaign::updateOrCreate(

                    ['meta_id' => $metaCampaign['id']],

                    [
                        'ad_account_id' => $account->id,
                        'name' => $metaCampaign['name'] ?? 'Unnamed Campaign',
                        'status' => $metaCampaign['status'] ?? 'PAUSED',
                        'objective' => $metaCampaign['objective'] ?? null
                    ]
                );
            }

            $campaignMap = Campaign::pluck('id', 'meta_id');

            Log::info('META_CAMPAIGNS_SYNCED', [
                'count' => count($campaigns['data'] ?? [])
            ]);

            /*
            |--------------------------------------------------------------------------
            | ADSETS
            |--------------------------------------------------------------------------
            */

            $metaAdsets = $this->meta->getAdSets($accountId);

            foreach ($metaAdsets['data'] ?? [] as $metaAdset) {

                $campaignId = $campaignMap[$metaAdset['campaign_id'] ?? null] ?? null;

                if (!$campaignId) continue;

                $budgetUsd = null;

                if (isset($metaAdset['daily_budget'])) {
                    $budgetUsd = ((int)$metaAdset['daily_budget']) / 100;
                }

                AdSet::updateOrCreate(

                    ['meta_id' => $metaAdset['id']],

                    [
                        'campaign_id' => $campaignId,
                        'name' => $metaAdset['name'] ?? 'Unnamed AdSet',
                        'status' => $metaAdset['status'] ?? 'PAUSED',
                        'daily_budget' => $budgetUsd
                    ]
                );
            }

            Log::info('META_ADSETS_SYNCED', [
                'count' => count($metaAdsets['data'] ?? [])
            ]);

            /*
            |--------------------------------------------------------------------------
            | ADS
            |--------------------------------------------------------------------------
            */

            $adsetMap = AdSet::pluck('id', 'meta_id');

            $metaAds = $this->meta->getAds($accountId);

            $count = 0;

            foreach ($metaAds['data'] ?? [] as $metaAd) {

                $metaAdId = $metaAd['id'] ?? null;

                $adsetId = $adsetMap[$metaAd['adset_id'] ?? null] ?? null;

                if (!$metaAdId || !$adsetId) continue;

                $ad = Ad::updateOrCreate(

                    ['meta_ad_id' => $metaAdId],

                    [
                        'adset_id' => $adsetId,
                        'name' => $metaAd['name'] ?? 'Unnamed Ad',
                        'status' => $metaAd['status'] ?? 'PAUSED'
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | LIFETIME INSIGHTS
                |--------------------------------------------------------------------------
                */

                $lifetime = $this->meta->getInsights($metaAdId, 'maximum');

                $impressions = $lifetime['impressions'] ?? 0;
                $clicks = $lifetime['clicks'] ?? 0;
                $lifetimeSpend = $lifetime['spend'] ?? 0;

                /*
                |--------------------------------------------------------------------------
                | TODAY INSIGHTS
                |--------------------------------------------------------------------------
                */

                $todayInsights = $this->meta->getInsights($metaAdId, 'today');

                $todaySpend = $todayInsights['spend'] ?? 0;

                Log::info('META_AD_INSIGHTS', [

                    'meta_ad_id' => $metaAdId,

                    'lifetime_spend' => $lifetimeSpend,

                    'today_spend' => $todaySpend,

                    'impressions' => $impressions,

                    'clicks' => $clicks
                ]);

                /*
                |--------------------------------------------------------------------------
                | CTR
                |--------------------------------------------------------------------------
                */

                $ctr = $impressions > 0
                    ? round(($clicks / $impressions) * 100, 2)
                    : 0;

                /*
                |--------------------------------------------------------------------------
                | Budget Guard
                |--------------------------------------------------------------------------
                */

                $status = $metaAd['status'] ?? $ad->status;

                $pauseReason = $ad->pause_reason;

                if (

                    $pauseReason !== 'manual' &&
                    $ad->daily_budget &&
                    $todaySpend >= $ad->daily_budget &&
                    $status !== 'PAUSED'

                ) {

                    Log::warning('AD_BUDGET_LIMIT_REACHED', [

                        'meta_ad_id' => $metaAdId,

                        'today_spend' => $todaySpend,

                        'daily_budget' => $ad->daily_budget

                    ]);

                    $this->meta->updateAd($metaAdId, ['status' => 'PAUSED']);

                    $status = 'PAUSED';

                    $pauseReason = 'budget_limit';
                }

                /*
                |--------------------------------------------------------------------------
                | Save Metrics
                |--------------------------------------------------------------------------
                */

                $ad->update([

                    'status' => $status,

                    'pause_reason' => $pauseReason,

                    'impressions' => $impressions,

                    'clicks' => $clicks,

                    'ctr' => $ctr,

                    'spend' => $lifetimeSpend,

                    'daily_spend' => $todaySpend,

                    'spend_date' => now()->toDateString()

                ]);

                Log::info('META_AD_DB_UPDATED', [

                    'meta_ad_id' => $metaAdId,

                    'daily_spend' => $todaySpend,

                    'lifetime_spend' => $lifetimeSpend,

                    'status' => $status
                ]);

                $count++;
            }

            Log::info('META_SYNC_COMPLETE', [

                'ads_synced' => $count

            ]);

            $this->info("Synced {$count} ads.");

            return Command::SUCCESS;

        } catch (\Throwable $e) {

            Log::error('META_SYNC_FAILED', [

                'error' => $e->getMessage(),

                'trace' => $e->getTraceAsString()

            ]);

            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}