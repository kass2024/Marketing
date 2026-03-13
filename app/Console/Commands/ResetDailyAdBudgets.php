<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Ad;
use App\Services\MetaAdsService;
use Illuminate\Support\Facades\Log;

class ResetDailyAdBudgets extends Command
{
    protected $signature = 'ads:reset-daily-budget';

    protected $description = 'Reset daily ad spend and reactivate ads paused by budget';

    protected MetaAdsService $meta;

    public function __construct(MetaAdsService $meta)
    {
        parent::__construct();
        $this->meta = $meta;
    }

    public function handle()
    {
        $today = now()->toDateString();

        $ads = Ad::where('status','PAUSED')
                 ->where('pause_reason','budget_limit')
                 ->get();

        $count = 0;

        foreach ($ads as $ad) {

            try {

                if ($ad->meta_ad_id) {

                    $this->meta->updateAd(
                        $ad->meta_ad_id,
                        ['status'=>'ACTIVE']
                    );

                }

                $ad->update([
                    'daily_spend' => 0,
                    'spend_date' => $today,
                    'status' => 'ACTIVE',
                    'pause_reason' => null
                ]);

                $count++;

            } catch (\Throwable $e) {

                Log::error('AD_RESET_FAILED',[
                    'ad_id'=>$ad->id,
                    'error'=>$e->getMessage()
                ]);

            }

        }

        $this->info("Reset {$count} ads");

        Log::info('DAILY_AD_RESET_COMPLETED',[
            'ads_reset'=>$count
        ]);
    }
}