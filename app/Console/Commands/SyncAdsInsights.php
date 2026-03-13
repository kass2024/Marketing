<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Ad;
use App\Services\MetaAdsService;

class SyncAdsInsights extends Command
{
    protected $signature = 'ads:sync';

    protected $description = 'Sync ads insights and enforce daily budget';

    public function handle(MetaAdsService $meta)
    {
        $ads = Ad::whereNotNull('meta_ad_id')->get();

        foreach ($ads as $ad) {

            try {

                $metaAd = $meta->getAd($ad->meta_ad_id);
                $insights = $meta->getInsights($ad->meta_ad_id);

                $impressions = 0;
                $clicks = 0;
                $spend = 0;

                if(isset($insights['data'][0])){

                    $row = $insights['data'][0];

                    $impressions = $row['impressions'] ?? 0;
                    $clicks = $row['clicks'] ?? 0;
                    $spend = $row['spend'] ?? 0;
                }

                $today = now()->toDateString();

                if ($ad->spend_date !== $today) {

                    $ad->daily_spend = 0;
                    $ad->spend_date = $today;
                }

                $spentToday = $spend - $ad->spend;

                if ($spentToday < 0) {
                    $spentToday = 0;
                }

                $ad->daily_spend += $spentToday;

                if ($ad->daily_spend >= $ad->daily_budget) {

                    $meta->updateAd(
                        $ad->meta_ad_id,
                        ['status'=>'PAUSED']
                    );

                    $ad->status = 'PAUSED';
                }

                $ctr = $impressions > 0
                    ? ($clicks / $impressions) * 100
                    : 0;

                $ad->update([

                    'status' => $ad->status ?? ($metaAd['status'] ?? $ad->status),

                    'impressions' => $impressions,

                    'clicks' => $clicks,

                    'spend' => $spend,

                    'ctr' => $ctr,

                    'daily_spend' => $ad->daily_spend,

                    'spend_date' => $ad->spend_date

                ]);

            } catch (\Throwable $e) {

                \Log::error('AUTO_SYNC_FAILED',[
                    'ad'=>$ad->id,
                    'error'=>$e->getMessage()
                ]);
            }
        }
    }
}