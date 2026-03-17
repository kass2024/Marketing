<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Ad;
use App\Services\MetaAdsService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ResetDailyAdBudgets extends Command
{
    protected $signature = 'ads:reset-daily-budget';

    protected $description = 'Reset daily spend for all ads and resume paused ads when budget allows';

    protected MetaAdsService $meta;

    public function __construct(MetaAdsService $meta)
    {
        parent::__construct();
        $this->meta = $meta;
    }

    public function handle()
    {
        $today = Carbon::today()->toDateString();

        /*
        |------------------------------------------------------------------
        | Fetch ALL ads (IMPORTANT FIX)
        |------------------------------------------------------------------
        */
        $ads = Ad::whereNotNull('meta_ad_id')->get();

        $resumedCount = 0;
        $resetCount = 0;

        foreach ($ads as $ad) {

            try {

                /*
                |------------------------------------------------------------------
                | 1️⃣ ALWAYS RESET DAILY SPEND (ALL ADS)
                |------------------------------------------------------------------
                */
                if (!$ad->spend_date || $ad->spend_date < $today) {

                    $ad->daily_spend = 0;
                    $ad->spend_date = $today;
                    $ad->save();

                    $resetCount++;

                    Log::info('AD_DAILY_RESET', [
                        'ad_id' => $ad->id
                    ]);
                }

                /*
                |------------------------------------------------------------------
                | 2️⃣ ONLY PROCESS PAUSED ADS FOR RESUME
                |------------------------------------------------------------------
                */
                if ($ad->status !== 'PAUSED') {
                    continue;
                }

                /*
                |------------------------------------------------------------------
                | 3️⃣ SKIP MANUAL PAUSE
                |------------------------------------------------------------------
                */
                if ($ad->pause_reason === 'manual') {

                    Log::info('AD_SKIPPED_MANUAL', [
                        'ad_id' => $ad->id
                    ]);

                    continue;
                }

                /*
                |------------------------------------------------------------------
                | 4️⃣ RESUME IF BUDGET AVAILABLE
                |------------------------------------------------------------------
                */
                if ($ad->daily_budget > $ad->daily_spend) {

                    $response = $this->meta->updateAd(
                        $ad->meta_ad_id,
                        ['status' => 'ACTIVE']
                    );

                    Log::info('META_RESUME_RESPONSE', [
                        'ad_id' => $ad->id,
                        'response' => $response
                    ]);

                    // Handle Meta error
                    if (isset($response['error'])) {
                        throw new \Exception(
                            $response['error']['message'] ?? 'Meta resume error'
                        );
                    }

                    $ad->update([
                        'status' => 'ACTIVE',
                        'pause_reason' => null
                    ]);

                    $resumedCount++;

                    Log::info('AD_RESUMED', [
                        'ad_id' => $ad->id,
                        'meta_ad_id' => $ad->meta_ad_id
                    ]);
                }

            } catch (\Throwable $e) {

                Log::error('AD_RESET_FAILED', [
                    'ad_id' => $ad->id,
                    'meta_ad_id' => $ad->meta_ad_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        /*
        |------------------------------------------------------------------
        | Summary
        |------------------------------------------------------------------
        */
        $this->info("Reset {$resetCount} ads | Resumed {$resumedCount} ads");

        Log::info('DAILY_AD_JOB_COMPLETED', [
            'ads_reset' => $resetCount,
            'ads_resumed' => $resumedCount,
            'total_ads' => $ads->count()
        ]);
    }
}