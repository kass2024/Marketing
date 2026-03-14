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

        try {

            $account = AdAccount::first();

            if (!$account) {
                $this->error('No Meta Ad Account connected.');
                return Command::FAILURE;
            }

            $accountId = $account->meta_id;

            /*
            |--------------------------------------------------------------------------
            | Campaign Sync
            |--------------------------------------------------------------------------
            */

            $campaigns = $this->meta->getCampaigns($accountId);

            foreach ($campaigns['data'] ?? [] as $metaCampaign) {

                Campaign::updateOrCreate(
                    ['meta_id' => $metaCampaign['id']],
                    [
                        'ad_account_id' => $account->id,
                        'name' => $metaCampaign['name'],
                        'status' => $metaCampaign['status'],
                        'objective' => $metaCampaign['objective'] ?? null
                    ]
                );
            }

            $campaignMap = Campaign::pluck('id','meta_id');

            /*
            |--------------------------------------------------------------------------
            | AdSet Sync
            |--------------------------------------------------------------------------
            */

            $metaAdsets = $this->meta->getAdSets($accountId);

            foreach ($metaAdsets['data'] ?? [] as $metaAdset) {

                $campaignId = $campaignMap[$metaAdset['campaign_id']] ?? null;

                if (!$campaignId) continue;

                $budget = isset($metaAdset['daily_budget'])
                    ? ((int)$metaAdset['daily_budget']) / 100
                    : null;

                AdSet::updateOrCreate(
                    ['meta_id'=>$metaAdset['id']],
                    [
                        'campaign_id'=>$campaignId,
                        'name'=>$metaAdset['name'],
                        'status'=>$metaAdset['status'],
                        'daily_budget'=>$budget
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Ads Sync
            |--------------------------------------------------------------------------
            */

            $adsetMap = AdSet::pluck('id','meta_id');

            $metaAds = $this->meta->getAds($accountId);

            foreach ($metaAds['data'] ?? [] as $metaAd) {

                $metaAdId = $metaAd['id'];
                $adsetId = $adsetMap[$metaAd['adset_id']] ?? null;

                if (!$adsetId) continue;

                $ad = Ad::updateOrCreate(
                    ['meta_ad_id'=>$metaAdId],
                    [
                        'adset_id'=>$adsetId,
                        'name'=>$metaAd['name'],
                        'status'=>$metaAd['status']
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | Insights
                |--------------------------------------------------------------------------
                */

                $lifetime = $this->meta->getInsights($metaAdId,'maximum');
                $today = $this->meta->getInsights($metaAdId,'today');

                $impressions = $lifetime['impressions'] ?? 0;
                $clicks = $lifetime['clicks'] ?? 0;
                $lifetimeSpend = $lifetime['spend'] ?? 0;
                $todaySpend = $today['spend'] ?? 0;

                $ctr = $impressions > 0
                    ? round(($clicks/$impressions)*100,2)
                    : 0;

                /*
                |--------------------------------------------------------------------------
                | Budget Guard
                |--------------------------------------------------------------------------
                */

                $status = $metaAd['status'];

                if (
                    $ad->daily_budget &&
                    $todaySpend >= $ad->daily_budget &&
                    $status !== 'PAUSED'
                ) {

                    Log::warning('AUTO_PAUSING_AD',[
                        'ad'=>$metaAdId,
                        'today_spend'=>$todaySpend,
                        'budget'=>$ad->daily_budget
                    ]);

                    $this->meta->updateAd($metaAdId,[
                        'status'=>'PAUSED'
                    ]);

                    $status = 'PAUSED';

                    $pauseReason = 'budget_limit';

                } else {

                    $pauseReason = $ad->pause_reason;
                }

                /*
                |--------------------------------------------------------------------------
                | Save Metrics
                |--------------------------------------------------------------------------
                */

                $ad->update([

                    'status'=>$status,
                    'pause_reason'=>$pauseReason,

                    'impressions'=>$impressions,
                    'clicks'=>$clicks,
                    'ctr'=>$ctr,

                    'spend'=>$lifetimeSpend,
                    'daily_spend'=>$todaySpend,
                    'spend_date'=>now()->toDateString()
                ]);
            }

            $this->info('Sync complete.');

            return Command::SUCCESS;

        } catch (\Throwable $e) {

            Log::error('META_SYNC_FAILED',[
                'error'=>$e->getMessage()
            ]);

            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}