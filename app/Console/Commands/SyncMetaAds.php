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

                $this->error('No Meta Ad Account connected.');

                Log::error('META_ACCOUNT_NOT_FOUND');

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

            $this->info('Syncing campaigns...');

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

            Log::info('META_CAMPAIGNS_SYNCED', [
                'count' => count($campaigns['data'] ?? [])
            ]);

            $campaignMap = Campaign::pluck('id', 'meta_id');

            /*
            |--------------------------------------------------------------------------
            | ADSETS
            |--------------------------------------------------------------------------
            */

            $this->info('Syncing adsets...');

            $metaAdsets = $this->meta->getAdSets($accountId);

            foreach ($metaAdsets['data'] ?? [] as $metaAdset) {

                $campaignId = $campaignMap[$metaAdset['campaign_id'] ?? null] ?? null;

                if (!$campaignId) continue;

                /*
                |--------------------------------------------------------------------------
                | Budget Conversion
                |--------------------------------------------------------------------------
                */

                $budgetUsd = null;

                if (isset($metaAdset['daily_budget'])) {

                    $metaBudgetCents = (int) $metaAdset['daily_budget'];

                    $budgetUsd = $metaBudgetCents / 100;

                    Log::info('META_ADSET_BUDGET_CONVERSION', [

                        'meta_adset_id' => $metaAdset['id'],

                        'meta_budget_cents' => $metaBudgetCents,

                        'converted_usd' => $budgetUsd

                    ]);
                }

                $existing = AdSet::where('meta_id', $metaAdset['id'])->first();

                if ($existing) {

                    Log::info('META_ADSET_DB_BEFORE', [

                        'meta_id' => $metaAdset['id'],

                        'existing_budget_usd' => $existing->daily_budget

                    ]);
                }

                $adset = AdSet::updateOrCreate(

                    ['meta_id' => $metaAdset['id']],

                    [
                        'campaign_id' => $campaignId,
                        'name' => $metaAdset['name'] ?? 'Unnamed AdSet',
                        'status' => $metaAdset['status'] ?? 'PAUSED',
                        'daily_budget' => $budgetUsd
                    ]
                );

                Log::info('META_ADSET_SYNCED', [

                    'meta_id' => $metaAdset['id'],

                    'db_budget_usd' => $adset->daily_budget

                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | ADS
            |--------------------------------------------------------------------------
            */

            $this->info('Syncing ads...');

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
                | INSIGHTS
                |--------------------------------------------------------------------------
                */

                Log::info('META_FETCH_INSIGHTS', [
                    'meta_ad_id' => $metaAdId
                ]);

                $insights = $this->meta->getInsights($metaAdId);

                $impressions = (int)($insights['data'][0]['impressions'] ?? 0);
                $clicks = (int)($insights['data'][0]['clicks'] ?? 0);
                $currentSpend = (float)($insights['data'][0]['spend'] ?? 0);

                Log::info('META_INSIGHTS_RESULT', [

                    'meta_ad_id' => $metaAdId,
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'spend' => $currentSpend

                ]);

                /*
                |--------------------------------------------------------------------------
                | Daily Spend Logic
                |--------------------------------------------------------------------------
                */

                $today = now()->toDateString();

                $previousSpend = (float)$ad->spend;
                $dailySpend = (float)$ad->daily_spend;

                if (!$ad->spend_date || $ad->spend_date !== $today) {

                    $dailySpend = 0;

                    Log::info('AD_DAILY_SPEND_RESET', [

                        'ad_id' => $ad->id,

                        'date' => $today

                    ]);
                }

                $increment = max(0, $currentSpend - $previousSpend);

                $dailySpend += $increment;

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
                    $dailySpend >= $ad->daily_budget &&
                    $status !== 'PAUSED'

                ) {

                    $this->meta->updateAd($metaAdId, ['status' => 'PAUSED']);

                    $status = 'PAUSED';
                    $pauseReason = 'budget_limit';

                    Log::warning('AD_PAUSED_BUDGET_LIMIT', [

                        'meta_ad_id' => $metaAdId,
                        'daily_spend' => $dailySpend,
                        'daily_budget' => $ad->daily_budget

                    ]);
                }

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
                | Update Metrics
                |--------------------------------------------------------------------------
                */

                $ad->update([

                    'status' => $status,
                    'pause_reason' => $pauseReason,
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'spend' => $currentSpend,
                    'ctr' => $ctr,
                    'daily_spend' => $dailySpend,
                    'spend_date' => $today

                ]);

                Log::info('META_AD_SYNCED', [

                    'meta_ad_id' => $metaAdId,

                    'spend' => $currentSpend,

                    'daily_spend' => $dailySpend

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