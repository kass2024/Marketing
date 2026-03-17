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

    protected $description = 'Auto resume paused ads when budget allows or new day starts (excluding manual pauses)';

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
        | Fetch paused ads
        |------------------------------------------------------------------
        */
        $ads = Ad::where('status', 'PAUSED')
            ->whereNotNull('meta_ad_id')
            ->get();

        $resumedCount = 0;

        foreach ($ads as $ad) {

            try {

                /*
                |------------------------------------------------------------------
                | 🚫 Skip manually paused ads
                |------------------------------------------------------------------
                */
                if ($ad->pause_reason === 'manual') {

                    Log::info('AD_SKIPPED_MANUAL', [
                        'ad_id' => $ad->id
                    ]);

                    continue;
                }

                $shouldResume = false;

                /*
                |------------------------------------------------------------------
                | Case 1: New day → reset spend
                |------------------------------------------------------------------
                */
                if (!$ad->spend_date || $ad->spend_date < $today) {

                    Log::info('AD_NEW_DAY_RESET', [
                        'ad_id' => $ad->id,
                        'previous_spend' => $ad->daily_spend
                    ]);

                    $ad->daily_spend = 0;
                    $ad->spend_date = $today;

                    $shouldResume = true;
                }

                /*
                |------------------------------------------------------------------
                | Case 2: Budget still available
                |------------------------------------------------------------------
                */
                if ($ad->daily_budget > $ad->daily_spend) {

                    Log::info('AD_BUDGET_AVAILABLE', [
                        'ad_id' => $ad->id,
                        'daily_budget' => $ad->daily_budget,
                        'daily_spend' => $ad->daily_spend
                    ]);

                    $shouldResume = true;
                }

                /*
                |------------------------------------------------------------------
                | Resume ad if eligible
                |------------------------------------------------------------------
                */
                if ($shouldResume) {

                    $this->meta->updateAd(
                        $ad->meta_ad_id,
                        ['status' => 'ACTIVE']
                    );

                    $ad->status = 'ACTIVE';
                    $ad->pause_reason = null;

                    $ad->save();

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
        $this->info("Resumed {$resumedCount} ads");

        Log::info('DAILY_AD_RESET_COMPLETED', [
            'ads_resumed' => $resumedCount,
            'checked_ads' => $ads->count()
        ]);
    }
}