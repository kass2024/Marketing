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

    protected $description = 'Reset daily spend, enforce budget limits, and resume eligible ads';

    protected MetaAdsService $meta;

    // 🔧 Safety buffer (avoid Meta overspend)
    protected float $bufferPercent = 0.98; // 98%

    public function __construct(MetaAdsService $meta)
    {
        parent::__construct();
        $this->meta = $meta;
    }

    public function handle()
    {
        $today = Carbon::today()->toDateString();

        $ads = Ad::whereNotNull('meta_ad_id')->get();

        $resetCount   = 0;
        $pausedCount  = 0;
        $resumedCount = 0;
        $skippedCount = 0;

        foreach ($ads as $ad) {

            try {

                /*
                |------------------------------------------------------------------
                | 1️⃣ RESET DAILY SPEND (NEW DAY)
                |------------------------------------------------------------------
                */
                if (!$ad->spend_date || $ad->spend_date < $today) {

                    $ad->update([
                        'daily_spend' => 0,
                        'spend_date'  => $today
                    ]);

                    $resetCount++;

                    Log::info('AD_DAILY_RESET', [
                        'ad_id' => $ad->id
                    ]);
                }

                /*
                |------------------------------------------------------------------
                | 2️⃣ AUTO-PAUSE IF BUDGET EXCEEDED (🔥 CRITICAL FIX)
                |------------------------------------------------------------------
                */
                $limit = $ad->daily_budget * $this->bufferPercent;

                if (
                    $ad->status === 'ACTIVE' &&
                    $ad->daily_spend >= $limit
                ) {

                    $response = $this->meta->updateAd(
                        $ad->meta_ad_id,
                        ['status' => 'PAUSED']
                    );

                    Log::info('META_PAUSE_RESPONSE', [
                        'ad_id' => $ad->id,
                        'response' => $response
                    ]);

                    if (isset($response['error'])) {
                        throw new \Exception(
                            $response['error']['message'] ?? 'Meta pause error'
                        );
                    }

                    $ad->update([
                        'status' => 'PAUSED',
                        'pause_reason' => 'budget'
                    ]);

                    $pausedCount++;

                    Log::warning('AD_AUTO_PAUSED_BUDGET', [
                        'ad_id' => $ad->id,
                        'spend' => $ad->daily_spend,
                        'budget' => $ad->daily_budget
                    ]);

                    continue;
                }

                /*
                |------------------------------------------------------------------
                | 3️⃣ ONLY HANDLE PAUSED ADS FOR RESUME
                |------------------------------------------------------------------
                */
                if ($ad->status !== 'PAUSED') {
                    continue;
                }

                /*
                |------------------------------------------------------------------
                | 4️⃣ SKIP MANUAL PAUSE
                |------------------------------------------------------------------
                */
                if ($ad->pause_reason === 'manual') {

                    $skippedCount++;

                    Log::info('AD_SKIPPED_MANUAL', [
                        'ad_id' => $ad->id
                    ]);

                    continue;
                }

                /*
                |------------------------------------------------------------------
                | 5️⃣ RESUME IF BUDGET AVAILABLE
                |------------------------------------------------------------------
                */
                if ($ad->daily_spend < $limit) {

                    $response = $this->meta->updateAd(
                        $ad->meta_ad_id,
                        ['status' => 'ACTIVE']
                    );

                    Log::info('META_RESUME_RESPONSE', [
                        'ad_id' => $ad->id,
                        'response' => $response
                    ]);

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

                Log::error('AD_BUDGET_JOB_FAILED', [
                    'ad_id' => $ad->id,
                    'meta_ad_id' => $ad->meta_ad_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        /*
        |------------------------------------------------------------------
        | FINAL SUMMARY
        |------------------------------------------------------------------
        */
        $this->info(
            "Reset: {$resetCount} | Paused: {$pausedCount} | Resumed: {$resumedCount} | Skipped: {$skippedCount}"
        );

        Log::info('DAILY_AD_JOB_COMPLETED', [
            'ads_reset'   => $resetCount,
            'ads_paused'  => $pausedCount,
            'ads_resumed' => $resumedCount,
            'ads_skipped' => $skippedCount,
            'total_ads'   => $ads->count()
        ]);

        return Command::SUCCESS;
    }
}