<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

use App\Services\MetaAdsService;
use App\Models\AdAccount;
use App\Models\Campaign;
use App\Models\AdSet;
use App\Models\Ad;

class SyncMetaAds extends Command
{
    protected $signature = 'meta:sync-ads';
    protected $description = 'Meta sync with batch insights + smart rate limiting';

    protected MetaAdsService $meta;

    // 🔥 GLOBAL THROTTLE SETTINGS
    protected int $delayBetweenCalls = 300000; // 0.3 sec
    protected int $maxRetries = 3;

    public function __construct(MetaAdsService $meta)
    {
        parent::__construct();
        $this->meta = $meta;
    }

    public function handle()
    {
        $this->info('🚀 Starting Meta Ads Sync...');

        try {

            $account = AdAccount::first();

            if (!$account) {
                $this->error('No Meta account connected');
                return Command::FAILURE;
            }

            $accountId = $account->meta_id;

            /*
            |----------------------------------------------------------
            | 1️⃣ CAMPAIGNS
            |----------------------------------------------------------
            */
            $campaigns = $this->safeMetaCall(fn() =>
                $this->meta->getCampaigns($accountId)
            );

            foreach ($campaigns['data'] ?? [] as $c) {

                Campaign::updateOrCreate(
                    ['meta_id' => $c['id']],
                    [
                        'ad_account_id' => $account->id,
                        'name' => $c['name'],
                        'status' => $c['status'],
                        'objective' => $c['objective'] ?? null
                    ]
                );
            }

            $campaignMap = Campaign::pluck('id', 'meta_id');

            /*
            |----------------------------------------------------------
            | 2️⃣ ADSETS
            |----------------------------------------------------------
            */
            $metaAdsets = $this->safeMetaCall(fn() =>
                $this->meta->getAdSets($accountId)
            );

            foreach ($metaAdsets['data'] ?? [] as $a) {

                $campaignId = $campaignMap[$a['campaign_id']] ?? null;
                if (!$campaignId) continue;

                $budget = isset($a['daily_budget'])
                    ? ((int)$a['daily_budget']) / 100
                    : null;

                AdSet::updateOrCreate(
                    ['meta_id' => $a['id']],
                    [
                        'campaign_id' => $campaignId,
                        'name' => $a['name'],
                        'status' => $a['status'],
                        'daily_budget' => $budget
                    ]
                );
            }

            $adsetMap = AdSet::pluck('id', 'meta_id');

            /*
            |----------------------------------------------------------
            | 3️⃣ ADS
            |----------------------------------------------------------
            */
            $metaAds = $this->safeMetaCall(fn() =>
                $this->meta->getAds($accountId)
            );

            /*
            |----------------------------------------------------------
            | 4️⃣ BATCH INSIGHTS (ONE CALL ONLY ✅)
            |----------------------------------------------------------
            */
            $insights = $this->safeMetaCall(fn() =>
                $this->meta->getInsightsBatch($accountId)
            );

            $insightMap = collect($insights['data'] ?? [])
                ->keyBy('ad_id');

            foreach ($metaAds['data'] ?? [] as $metaAd) {

                try {

                    $metaAdId = $metaAd['id'];
                    $adsetId = $adsetMap[$metaAd['adset_id']] ?? null;

                    if (!$adsetId) continue;

                    $ad = Ad::firstOrCreate(
                        ['meta_ad_id' => $metaAdId],
                        [
                            'adset_id' => $adsetId,
                            'name' => $metaAd['name'],
                            'status' => 'ACTIVE'
                        ]
                    );

                    $insight = $insightMap[$metaAdId] ?? [];

                    $todaySpend = (float)($insight['spend'] ?? 0);
                    $impressions = (int)($insight['impressions'] ?? 0);
                    $clicks = (int)($insight['clicks'] ?? 0);

                    $ctr = $impressions > 0
                        ? round(($clicks / $impressions) * 100, 2)
                        : 0;

                    /*
                    |--------------------------------------------------
                    | 🔥 SMART BUDGET CONTROL
                    |--------------------------------------------------
                    */
                    $status = $ad->status;
                    $pauseReason = $ad->pause_reason;

                    if ($ad->daily_budget) {

                        if ($todaySpend >= $ad->daily_budget) {

                            if ($pauseReason !== 'budget') {

                                Log::warning('AUTO_PAUSE', ['ad' => $metaAdId]);

                                $this->safeMetaCall(fn() =>
                                    $this->meta->updateAd($metaAdId, [
                                        'status' => 'PAUSED'
                                    ])
                                );

                                $status = 'PAUSED';
                                $pauseReason = 'budget';
                            }

                        } else {

                            if ($pauseReason === 'budget') {

                                Log::info('AUTO_RESUME', ['ad' => $metaAdId]);

                                $this->safeMetaCall(fn() =>
                                    $this->meta->updateAd($metaAdId, [
                                        'status' => 'ACTIVE'
                                    ])
                                );

                                $status = 'ACTIVE';
                                $pauseReason = null;
                            }
                        }
                    }

                    /*
                    |--------------------------------------------------
                    | SAVE
                    |--------------------------------------------------
                    */
                    $ad->update([
                        'status' => $status,
                        'pause_reason' => $pauseReason,
                        'impressions' => $impressions,
                        'clicks' => $clicks,
                        'ctr' => $ctr,
                        'daily_spend' => $todaySpend,
                        'spend_date' => Carbon::today()->toDateString()
                    ]);

                } catch (\Throwable $e) {

                    Log::error('AD_SYNC_FAILED', [
                        'ad_id' => $metaAd['id'] ?? null,
                        'error' => $e->getMessage()
                    ]);
                }

                // 🔒 GLOBAL THROTTLE
                usleep($this->delayBetweenCalls);
            }

            $this->info('✅ Sync completed successfully');
            return Command::SUCCESS;

        } catch (\Throwable $e) {

            Log::error('META_SYNC_FATAL', [
                'error' => $e->getMessage()
            ]);

            $this->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    /*
    |--------------------------------------------------------------
    | 🔥 SAFE META CALL WITH RETRY + BACKOFF
    |--------------------------------------------------------------
    */
    private function safeMetaCall(callable $callback)
    {
        $attempt = 0;

        start:

        try {
            return $callback();

        } catch (\Throwable $e) {

            $attempt++;

            $message = $e->getMessage();

            // 🔴 RATE LIMIT HANDLING
            if (str_contains($message, 'limit') || str_contains($message, 'code":17')) {

                if ($attempt <= $this->maxRetries) {

                    $delay = pow(2, $attempt); // exponential backoff

                    Log::warning("RATE LIMIT HIT - RETRY {$attempt} in {$delay}s");

                    sleep($delay);

                    goto start;
                }

                Log::error('MAX RETRIES REACHED (RATE LIMIT)');
            }

            throw $e;
        }
    }
}