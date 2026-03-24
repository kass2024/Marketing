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

    protected $description = 'Enforce daily ad budgets, auto-pause and resume ads';

    protected MetaAdsService $meta;

    public function __construct(MetaAdsService $meta)
    {
        parent::__construct();
        $this->meta = $meta;
    }

    public function handle()
    {
        $today = Carbon::today()->toDateString();

        $ads = Ad::whereNotNull('meta_ad_id')->get();

        $reset = 0;
        $paused = 0;
        $resumed = 0;
        $skipped = 0;

        foreach ($ads as $ad) {

            try {

                /*
                |------------------------------------------------------------
                | 1️⃣ RESET DAILY SPEND (NEW DAY)
                |------------------------------------------------------------
                */
                if (!$ad->spend_date || $ad->spend_date < $today) {

                    $ad->update([
                        'daily_spend' => 0,
                        'spend_date' => $today
                    ]);

                    $reset++;

                    Log::info('RESET_OK', ['ad_id' => $ad->id]);
                }

                /*
                |------------------------------------------------------------
                | DEBUG (CRITICAL)
                |------------------------------------------------------------
                */
                Log::info('BUDGET_CHECK', [
                    'ad_id' => $ad->id,
                    'status' => $ad->status,
                    'spend' => $ad->daily_spend,
                    'budget' => $ad->daily_budget
                ]);

                /*
                |------------------------------------------------------------
                | 2️⃣ HARD STOP IF BUDGET EXCEEDED
                |------------------------------------------------------------
                */
                if (
                    $ad->status === 'ACTIVE' &&
                    $ad->daily_spend >= $ad->daily_budget
                ) {

                    Log::warning('PAUSE_TRIGGERED', [
                        'ad_id' => $ad->id
                    ]);

                    $response = $this->meta->updateAd(
                        $ad->meta_ad_id,
                        ['status' => 'PAUSED']
                    );

                    if (isset($response['error'])) {
                        throw new \Exception($response['error']['message'] ?? 'Pause failed');
                    }

                    $ad->update([
                        'status' => 'PAUSED',
                        'pause_reason' => 'budget'
                    ]);

                    $paused++;

                    continue;
                }

                /*
                |------------------------------------------------------------
                | 3️⃣ SKIP NON-PAUSED
                |------------------------------------------------------------
                */
                if ($ad->status !== 'PAUSED') {
                    continue;
                }

                /*
                |------------------------------------------------------------
                | 4️⃣ SKIP MANUAL PAUSE
                |------------------------------------------------------------
                */
                if ($ad->pause_reason === 'manual') {

                    $skipped++;

                    Log::info('SKIP_MANUAL', ['ad_id' => $ad->id]);

                    continue;
                }

                /*
                |------------------------------------------------------------
                | 5️⃣ RESUME IF BELOW BUDGET
                |------------------------------------------------------------
                */
                if ($ad->daily_spend < $ad->daily_budget) {

                    $response = $this->meta->updateAd(
                        $ad->meta_ad_id,
                        ['status' => 'ACTIVE']
                    );

                    if (isset($response['error'])) {
                        throw new \Exception($response['error']['message'] ?? 'Resume failed');
                    }

                    $ad->update([
                        'status' => 'ACTIVE',
                        'pause_reason' => null
                    ]);

                    $resumed++;
                }

            } catch (\Throwable $e) {

                Log::error('JOB_FAILED', [
                    'ad_id' => $ad->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Reset:$reset | Paused:$paused | Resumed:$resumed | Skipped:$skipped");

        return Command::SUCCESS;
    }
}