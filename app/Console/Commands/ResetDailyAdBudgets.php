<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Ad;
use App\Services\MetaAdsService;
use Illuminate\Support\Facades\Log;

class ResetDailyAdBudgets extends Command
{
    protected $signature = 'ads:reset-daily-budget';

    protected $description = 'Resume ads paused by budget when budget increases or new day begins';

    protected MetaAdsService $meta;

    public function __construct(MetaAdsService $meta)
    {
        parent::__construct();
        $this->meta = $meta;
    }

    public function handle()
    {
        $today = now()->toDateString();

        $ads = Ad::where('status', 'PAUSED')
            ->where('pause_reason', 'budget_limit')
            ->get();

        $count = 0;

        foreach ($ads as $ad) {

            try {

                $resume = false;

                /*
                |--------------------------------------------------------------------------
                | CASE 1 — Budget Increased
                |--------------------------------------------------------------------------
                */

                if ($ad->daily_budget > $ad->daily_spend) {

                    Log::info('AD_RESUME_BUDGET_INCREASE', [
                        'ad_id' => $ad->id,
                        'daily_spend' => $ad->daily_spend,
                        'new_budget' => $ad->daily_budget
                    ]);

                    $resume = true;
                }

                /*
                |--------------------------------------------------------------------------
                | CASE 2 — New Day
                |--------------------------------------------------------------------------
                */

                if ($ad->spend_date && $ad->spend_date < $today) {

                    Log::info('AD_RESUME_NEW_DAY', [
                        'ad_id' => $ad->id,
                        'yesterday_spend' => $ad->daily_spend
                    ]);

                    $resume = true;

                    // reset daily spend
                    $ad->daily_spend = 0;
                    $ad->spend_date = $today;
                }

                /*
                |--------------------------------------------------------------------------
                | Resume Ad
                |--------------------------------------------------------------------------
                */

                if ($resume) {

                    if ($ad->meta_ad_id) {

                        $this->meta->updateAd(
                            $ad->meta_ad_id,
                            ['status' => 'ACTIVE']
                        );

                    }

                    $ad->status = 'ACTIVE';
                    $ad->pause_reason = null;

                    $ad->save();

                    $count++;
                }

            } catch (\Throwable $e) {

                Log::error('AD_RESET_FAILED', [
                    'ad_id' => $ad->id,
                    'error' => $e->getMessage()
                ]);

            }
        }

        $this->info("Resumed {$count} ads");

        Log::info('DAILY_AD_RESET_COMPLETED', [
            'ads_resumed' => $count
        ]);
    }
}