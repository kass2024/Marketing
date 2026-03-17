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

    protected $description = 'Auto resume ONLY budget-limited ads when budget allows or new day starts';

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
        | Get ONLY paused ads with meta ID
        |------------------------------------------------------------------
        */
        $ads = Ad::where('status', 'PAUSED')
            ->whereNotNull('meta_ad_id')
            ->get();

        $count = 0;

        foreach ($ads as $ad) {

            try {

                /*
                |------------------------------------------------------------------
                | 🚫 NEVER RESUME MANUAL PAUSED ADS
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
                | ✅ ONLY HANDLE BUDGET LIMITED ADS
                |------------------------------------------------------------------
                */
                if ($ad->pause_reason !== 'budget_limit') {

                    Log::info('AD_SKIPPED_NOT_BUDGET', [
                        'ad_id' => $ad->id,
                        'reason' => $ad->pause_reason
                    ]);

                    continue;
                }

                $resume = false;

                /*
                |------------------------------------------------------------------
                | CASE 1 — New Day → Reset spend
                |------------------------------------------------------------------
                */
                if (!$ad->spend_date || $ad->spend_date < $today) {

                    $ad->daily_spend = 0;
                    $ad->spend_date = $today;

                    $resume = true;

                    Log::info('AD_NEW_DAY_RESET', [
                        'ad_id' => $ad->id
                    ]);
                }

                /*
                |------------------------------------------------------------------
                | CASE 2 — Budget available
                |------------------------------------------------------------------
                elseif ($ad->daily_budget > $ad->daily_spend) {

                    $resume = true;

                    Log::info('AD_BUDGET_AVAILABLE', [
                        'ad_id' => $ad->id,
                        'daily_budget' => $ad->daily_budget,
                        'daily_spend' => $ad->daily_spend
                    ]);
                }

                /*
                |------------------------------------------------------------------
                | RESUME AD
                |------------------------------------------------------------------
                */
                if ($resume) {

                    $this->meta->updateAd(
                        $ad->meta_ad_id,
                        ['status' => 'ACTIVE']
                    );

                    $ad->status = 'ACTIVE';
                    $ad->pause_reason = null;

                    $ad->save();

                    $count++;

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

        $this->info("Resumed {$count} ads");

        Log::info('DAILY_AD_RESET_COMPLETED', [
            'ads_resumed' => $count,
            'checked_ads' => $ads->count()
        ]);
    }
}