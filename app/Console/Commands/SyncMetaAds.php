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
        Log::info('META_SYNC_STARTED');

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

            /*
            |--------------------------------------------------------------------------
            | Sync Campaigns
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

            /*
            |--------------------------------------------------------------------------
            | Sync AdSets
            |--------------------------------------------------------------------------
            */

            $this->info('Syncing adsets...');

            $adsets = $this->meta->getAdSets($accountId);

            foreach ($adsets['data'] ?? [] as $metaAdset) {

                $campaign = Campaign::where(
                    'meta_id',
                    $metaAdset['campaign_id'] ?? null
                )->first();

                if (!$campaign) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Normalize Budget (Meta returns cents)
                |--------------------------------------------------------------------------
                */

                $budget = null;

                if (isset($metaAdset['daily_budget'])) {

                    $rawBudget = (float) $metaAdset['daily_budget'];

                    Log::info('META_ADSET_BUDGET_RAW', [
                        'meta_adset_id' => $metaAdset['id'],
                        'raw_budget' => $rawBudget
                    ]);

                    if ($rawBudget > 100) {
                        $budget = $rawBudget / 100;
                    } else {
                        $budget = $rawBudget;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Prevent accidental budget corruption
                |--------------------------------------------------------------------------
                */

                $existing = AdSet::where('meta_id', $metaAdset['id'])->first();

                if ($existing && $existing->daily_budget && $budget && $budget < 0.1) {

                    Log::warning('META_BUDGET_PROTECTED', [
                        'meta_id' => $metaAdset['id'],
                        'existing_budget' => $existing->daily_budget,
                        'incoming_budget' => $budget
                    ]);

                    $budget = $existing->daily_budget;
                }

                AdSet::updateOrCreate(

                    ['meta_id' => $metaAdset['id']],

                    [
                        'campaign_id' => $campaign->id,
                        'name' => $metaAdset['name'] ?? 'Unnamed AdSet',
                        'status' => $metaAdset['status'] ?? 'PAUSED',
                        'daily_budget' => $budget
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Sync Ads
            |--------------------------------------------------------------------------
            */

            $this->info('Syncing ads...');

            $ads = $this->meta->getAds($accountId);

            $count = 0;

            foreach ($ads['data'] ?? [] as $metaAd) {

                $metaAdId = $metaAd['id'] ?? null;

                if (!$metaAdId) {
                    continue;
                }

                $adset = AdSet::where(
                    'meta_id',
                    $metaAd['adset_id'] ?? null
                )->first();

                if (!$adset) {
                    continue;
                }

                $ad = Ad::updateOrCreate(

                    ['meta_ad_id' => $metaAdId],

                    [
                        'adset_id' => $adset->id,
                        'name' => $metaAd['name'] ?? 'Unnamed Ad',
                        'status' => $metaAd['status'] ?? 'PAUSED'
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
                $currentSpend = 0;

                if (!empty($insights['data'][0])) {

                    $row = $insights['data'][0];

                    $impressions = (int) ($row['impressions'] ?? 0);
                    $clicks = (int) ($row['clicks'] ?? 0);
                    $currentSpend = (float) ($row['spend'] ?? 0);
                }

                /*
                |--------------------------------------------------------------------------
                | Daily Spend Logic
                |--------------------------------------------------------------------------
                */

                $today = now()->toDateString();

                $previousSpend = (float) ($ad->spend ?? 0);
                $dailySpend = (float) ($ad->daily_spend ?? 0);

                if (!$ad->spend_date || $ad->spend_date !== $today) {

                    $dailySpend = 0;

                    Log::info('AD_DAILY_SPEND_RESET', [
                        'ad_id' => $ad->id,
                        'date' => $today
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Spend Increment
                |--------------------------------------------------------------------------
                */

                $increment = $currentSpend - $previousSpend;

                if ($increment < 0) {
                    $increment = 0;
                }

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

                    $this->meta->updateAd(
                        $metaAdId,
                        ['status' => 'PAUSED']
                    );

                    $status = 'PAUSED';
                    $pauseReason = 'budget_limit';

                    Log::info('AD_AUTO_PAUSED_BUDGET_LIMIT', [
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
                | Update Ad
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
                    'lifetime_spend' => $currentSpend,
                    'daily_spend' => $dailySpend
                ]);

                $count++;
            }

            $this->info("Synced {$count} ads.");

            Log::info('META_SYNC_COMPLETED', [
                'count' => $count
            ]);

            return Command::SUCCESS;
        }

        catch (\Throwable $e) {

            Log::error('META_SYNC_FAILED', [

                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->error('Meta Ads sync failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}