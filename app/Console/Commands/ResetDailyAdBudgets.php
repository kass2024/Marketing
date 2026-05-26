<?php

namespace App\Console\Commands;

use App\Models\Ad;
use App\Services\MetaAdsService;
use App\Support\AdBudgetGuard;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetDailyAdBudgets extends Command
{
    protected $signature = 'ads:reset-daily-budget';

    protected $description = 'Reset daily spend counters and auto-pause ads that reached their daily budget';

    public function __construct(protected MetaAdsService $meta)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = Carbon::today()->toDateString();

        $reset = 0;
        $paused = 0;

        foreach (Ad::whereNotNull('meta_ad_id')->get() as $ad) {
            try {
                if (! $ad->spend_date || $ad->spend_date < $today) {
                    $payload = AdBudgetGuard::filterPersistablePayload([
                        'daily_spend' => 0,
                        'daily_spend_anchor' => 0,
                        'spend_date' => $today,
                    ]);

                    $ad->update($payload);

                    $ad->daily_spend = 0;
                    $ad->spend_date = $today;

                    if (AdBudgetGuard::hasAnchorColumn()) {
                        $ad->daily_spend_anchor = 0;
                    }

                    $reset++;
                }

                $wasActive = $ad->status === Ad::STATUS_ACTIVE;

                AdBudgetGuard::enforce($ad, $this->meta);

                if ($wasActive && $ad->status === Ad::STATUS_PAUSED && $ad->pause_reason === 'budget_limit') {
                    $paused++;
                }
            } catch (\Throwable $e) {
                Log::error('ADS_RESET_DAILY_BUDGET_FAILED', [
                    'ad_id' => $ad->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Reset: {$reset} | Auto-paused for budget: {$paused}");

        return self::SUCCESS;
    }
}
