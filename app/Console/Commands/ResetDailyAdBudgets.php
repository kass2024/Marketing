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

    protected $description = 'Sync Meta spend, enforce daily budget, pause/resume ads safely';

    protected MetaAdsService $meta;

    protected float $bufferPercent = 0.98; // 98% safety

    public function __construct(MetaAdsService $meta)
    {
        parent::__construct();
        $this->meta = $meta;
    }

    public function handle()
    {
        $today = Carbon::today()->toDateString();

        $ads = Ad::whereNotNull('meta_ad_id')->get();

        $paused = 0;
        $resumed = 0;
        $skipped = 0;
        $errors = 0;

        Log::info('DAILY_AD_JOB_STARTED', [
            'date' => $today,
            'ads_count' => $ads->count()
        ]);

        foreach ($ads as $ad) {

            try {

                /*
                |------------------------------------------------------------
                | 1️⃣ GET REAL-TIME META TODAY SPEND
                |------------------------------------------------------------
                */
                $insights = $this->meta->getInsights(
                    $ad->meta_ad_id,
                    'today'
                );

                $todaySpend = (float) ($insights['spend'] ?? 0);

                /*
                |------------------------------------------------------------
                | 2️⃣ UPDATE LOCAL CACHE (UI PURPOSE ONLY)
                |------------------------------------------------------------
                */
                $ad->update([
                    'daily_spend' => $todaySpend,
                    'spend_date'  => $today
                ]);

                /*
                |------------------------------------------------------------
                | 3️⃣ CALCULATE LIMIT
                |------------------------------------------------------------
                */
                $limit = $ad->daily_budget * $this->bufferPercent;

                Log::info('AD_BUDGET_CHECK', [
                    'ad_id' => $ad->id,
                    'meta_ad_id' => $ad->meta_ad_id,
                    'today_spend' => $todaySpend,
                    'budget' => $ad->daily_budget,
                    'limit' => $limit,
                    'status' => $ad->status
                ]);

                /*
                |------------------------------------------------------------
                | 4️⃣ AUTO PAUSE IF EXCEEDED
                |------------------------------------------------------------
                */
                if ($ad->status === 'ACTIVE' && $todaySpend >= $limit) {

                    $response = $this->meta->updateAd(
                        $ad->meta_ad_id,
                        ['status' => 'PAUSED']
                    );

                    if (isset($response['error'])) {
                        throw new \Exception($response['error']['message']);
                    }

                    $ad->update([
                        'status' => 'PAUSED',
                        'pause_reason' => 'budget'
                    ]);

                    $paused++;

                    Log::warning('AD_AUTO_PAUSED', [
                        'ad_id' => $ad->id,
                        'spend' => $todaySpend,
                        'limit' => $limit
                    ]);

                    continue;
                }

                /*
                |------------------------------------------------------------
                | 5️⃣ HANDLE RESUME LOGIC
                |------------------------------------------------------------
                */

                if ($ad->status !== 'PAUSED') {
                    continue;
                }

                if ($ad->pause_reason === 'manual') {

                    $skipped++;

                    Log::info('AD_MANUAL_SKIP', [
                        'ad_id' => $ad->id
                    ]);

                    continue;
                }

                /*
                |------------------------------------------------------------
                | 6️⃣ RESUME IF BELOW LIMIT
                |------------------------------------------------------------
                */
                if ($todaySpend < $limit) {

                    $response = $this->meta->updateAd(
                        $ad->meta_ad_id,
                        ['status' => 'ACTIVE']
                    );

                    if (isset($response['error'])) {
                        throw new \Exception($response['error']['message']);
                    }

                    $ad->update([
                        'status' => 'ACTIVE',
                        'pause_reason' => null
                    ]);

                    $resumed++;

                    Log::info('AD_RESUMED', [
                        'ad_id' => $ad->id,
                        'spend' => $todaySpend,
                        'limit' => $limit
                    ]);
                }

            } catch (\Throwable $e) {

                $errors++;

                Log::error('AD_JOB_ERROR', [
                    'ad_id' => $ad->id ?? null,
                    'meta_ad_id' => $ad->meta_ad_id ?? null,
                    'error' => $e->getMessage()
                ]);
            }
        }

        /*
        |------------------------------------------------------------
        | FINAL SUMMARY
        |------------------------------------------------------------
        */
        Log::info('DAILY_AD_JOB_COMPLETED', [
            'paused' => $paused,
            'resumed' => $resumed,
            'skipped' => $skipped,
            'errors' => $errors,
            'total_ads' => $ads->count()
        ]);

        $this->info(
            "Paused: {$paused} | Resumed: {$resumed} | Skipped: {$skipped} | Errors: {$errors}"
        );

        return Command::SUCCESS;
    }
}