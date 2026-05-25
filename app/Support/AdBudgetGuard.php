<?php

namespace App\Support;

use App\Models\Ad;
use App\Services\MetaAdsService;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdBudgetGuard
{
    /**
     * Pause an active ad on Meta when today's spend reaches the daily budget.
     * Does not auto-resume — use manual Publish after increasing budget or on a new day.
     */
    public static function enforce(Ad $ad, MetaAdsService $meta): void
    {
        if (! $ad->meta_ad_id || ! $ad->daily_budget || $ad->daily_budget <= 0) {
            return;
        }

        if (! $ad->hasReachedDailyBudget()) {
            return;
        }

        if ($ad->status !== Ad::STATUS_ACTIVE) {
            return;
        }

        if ($ad->pause_reason === 'manual') {
            return;
        }

        try {
            $response = $meta->updateAd($ad->meta_ad_id, ['status' => 'PAUSED']);

            if (isset($response['error'])) {
                throw new \Exception($response['error']['message'] ?? 'Pause failed');
            }

            $today = now()->toDateString();

            $ad->update([
                'status' => Ad::STATUS_PAUSED,
                'pause_reason' => 'budget_limit',
                'spend_date' => $today,
            ]);

            $ad->status = Ad::STATUS_PAUSED;
            $ad->pause_reason = 'budget_limit';

            Log::info('AD_AUTO_PAUSED_BUDGET', [
                'ad_id' => $ad->id,
                'meta_ad_id' => $ad->meta_ad_id,
                'daily_spend' => $ad->daily_spend,
                'daily_budget' => $ad->daily_budget,
            ]);
        } catch (Throwable $e) {
            Log::warning('AD_AUTO_PAUSE_BUDGET_FAILED', [
                'ad_id' => $ad->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function canManualPublish(Ad $ad): bool
    {
        return ! $ad->hasReachedDailyBudget();
    }

    public static function publishBlockedMessage(Ad $ad): string
    {
        return sprintf(
            'Daily budget reached ($%s of $%s). Increase the daily budget in Edit, or try again tomorrow.',
            number_format((float) $ad->daily_spend, 2),
            number_format((float) $ad->daily_budget, 2)
        );
    }
}
