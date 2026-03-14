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
                        'name'        => $metaCampaign['name'] ?? 'Unnamed Campaign',
                        'status'      => $metaCampaign['status'] ?? 'PAUSED',
                        'objective'   => $metaCampaign['objective'] ?? null,
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Build Campaign Map
            |--------------------------------------------------------------------------
            */

            $campaignMap = Campaign::pluck('id', 'meta_id');

            /*
            |--------------------------------------------------------------------------
            | Sync AdSets
            |--------------------------------------------------------------------------
            */

            $this->info('Syncing adsets...');

            $metaAdsets = $this->meta->getAdSets($accountId);

            foreach ($metaAdsets['data'] ?? [] as $metaAdset) {

                $campaignId = $campaignMap[$metaAdset['campaign_id'] ?? null] ?? null;

                if (!$campaignId) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Budget Conversion (Meta returns cents)
                |--------------------------------------------------------------------------
                */

                $budget = null;

                if (isset($metaAdset['daily_budget'])) {

                    $rawBudget = (float) $metaAdset['daily_budget'];

                    Log::info('META_ADSET_BUDGET_RAW', [
                        'meta_adset_id' => $metaAdset['id'],
                        'raw_budget'    => $rawBudget
                    ]);

                    $budget = $rawBudget / 100;
                }

                /*
                |--------------------------------------------------------------------------
                | Protect DB budget if Meta returns suspicious value
                |--------------------------------------------------------------------------
                */

                $existing = AdSet::where('meta_id', $metaAdset['id'])->first();

                if (
                    $existing &&
                    $existing->daily_budget &&
                    $budget !== null &&
                    $budget < 0.05
                ) {
                    Log::warning('META_BUDGET_PROTECTED', [
                        'meta_id'        => $metaAdset['id'],
                        'existing_budget'=> $existing->daily_budget,
                        'incoming_budget'=> $budget
                    ]);

                    $budget = $existing->daily_budget;
                }

                AdSet::updateOrCreate(

                    ['meta_id' => $metaAdset['id']],

                    [
                        'campaign_id' => $campaignId,
                        'name'        => $metaAdset['name'] ?? 'Unnamed AdSet',
                        'status'      => $metaAdset['status'] ?? 'PAUSED',
                        'daily_budget'=> $budget
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Build AdSet Map
            |--------------------------------------------------------------------------
            */

            $adsetMap = AdSet::pluck('id', 'meta_id');

            /*
            |--------------------------------------------------------------------------
            | Sync Ads
            |--------------------------------------------------------------------------
            */

            $this->info('Syncing ads...');

            $metaAds = $this->meta->getAds($accountId);

            $count = 0;

            foreach ($metaAds['data'] ?? [] as $metaAd) {

                $metaAdId = $metaAd['id'] ?? null;
                $adsetId  = $adsetMap[$metaAd['adset_id'] ?? null] ?? null;

                if (!$metaAdId || !$adsetId) {
                    continue;
                }

                $ad = Ad::updateOrCreate(

                    ['meta_ad_id' => $metaAdId],

                    [
                        'adset_id' => $adsetId,
                        'name'     => $metaAd['name'] ?? 'Unnamed Ad',
                        'status'   => $metaAd['status'] ?? 'PAUSED'
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | Fetch Insights
                |--------------------------------------------------------------------------
                */

                $insights = $this->meta->getInsights($metaAdId);

                $impressions = (int) ($insights['data'][0]['impressions'] ?? 0);
                $clicks      = (int) ($insights['data'][0]['clicks'] ?? 0);
                $currentSpend= (float) ($insights['data'][0]['spend'] ?? 0);

                /*
                |--------------------------------------------------------------------------
                | Daily Spend Logic
                |--------------------------------------------------------------------------
                */

                $today = now()->toDateString();

                $previousSpend = (float) $ad->spend;
                $dailySpend    = (float) $ad->daily_spend;

                if (!$ad->spend_date || $ad->spend_date !== $today) {

                    $dailySpend = 0;

                    Log::info('AD_DAILY_SPEND_RESET', [
                        'ad_id' => $ad->id,
                        'date'  => $today
                    ]);
                }

                $increment = max(0, $currentSpend - $previousSpend);
                $dailySpend += $increment;

                /*
                |--------------------------------------------------------------------------
                | Budget Guard
                |--------------------------------------------------------------------------
                */

                $status      = $metaAd['status'] ?? $ad->status;
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
                        'meta_ad_id'  => $metaAdId,
                        'daily_spend' => $dailySpend,
                        'daily_budget'=> $ad->daily_budget
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | CTR Calculation
                |--------------------------------------------------------------------------
                */

                $ctr = $impressions > 0
                    ? round(($clicks / $impressions) * 100, 2)
                    : 0;

                /*
                |--------------------------------------------------------------------------
                | Update Ad Metrics
                |--------------------------------------------------------------------------
                */

                $ad->update([

                    'status'       => $status,
                    'pause_reason' => $pauseReason,
                    'impressions'  => $impressions,
                    'clicks'       => $clicks,
                    'spend'        => $currentSpend,
                    'ctr'          => $ctr,
                    'daily_spend'  => $dailySpend,
                    'spend_date'   => $today
                ]);

                Log::info('META_AD_SYNCED', [

                    'meta_ad_id'    => $metaAdId,
                    'lifetime_spend'=> $currentSpend,
                    'daily_spend'   => $dailySpend
                ]);

                $count++;
            }

            $this->info("Synced {$count} ads.");

            Log::info('META_SYNC_COMPLETED', [
                'count' => $count
            ]);

            return Command::SUCCESS;

        } catch (\Throwable $e) {

            Log::error('META_SYNC_FAILED', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            $this->error('Meta Ads sync failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}