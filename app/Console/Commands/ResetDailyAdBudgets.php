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

    protected $description = 'Auto resume paused ads (manual or budget) when budget allows or a new day begins';

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
        |--------------------------------------------------------------------------
        | Get ALL paused ads
        |--------------------------------------------------------------------------
        */

        $ads = Ad::where('status', 'PAUSED')
            ->whereNotNull('meta_ad_id')
            ->get();

        $count = 0;

        foreach ($ads as $ad) {

            try {

                $resume = false;

                /*
                |--------------------------------------------------------------------------
                | CASE 1 — New Day Reset
                |--------------------------------------------------------------------------
                */

                if (!$ad->spend_date || $ad->spend_date < $today) {

                    Log::info('AD_NEW_DAY_RESET', [
                        'ad_id' => $ad->id,
                        'previous_spend' => $ad->daily_spend
                    ]);

                    $ad->daily_spend = 0;
                    $ad->spend_date = $today;

                    $resume = true;
                }

                /*
                |--------------------------------------------------------------------------
                | CASE 2 — Budget Available
                |--------------------------------------------------------------------------
                */

                if ($ad->daily_budget > $ad->daily_spend) {

                    Log::info('AD_BUDGET_AVAILABLE', [
                        'ad_id' => $ad->id,
                        'daily_budget' => $ad->daily_budget,
                        'daily_spend' => $ad->daily_spend
                    ]);

                    $resume = true;
                }

                /*
                |--------------------------------------------------------------------------
                | Resume Ad
                |--------------------------------------------------------------------------
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