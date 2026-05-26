<?php

namespace App\Support;

use App\Models\Ad;
use App\Services\MetaAdsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AdBudgetGuard
{
    protected static ?bool $hasAnchorColumn = null;

    public static function hasAnchorColumn(): bool
    {
        if (static::$hasAnchorColumn === null) {
            static::$hasAnchorColumn = Schema::hasColumn('ads', 'daily_spend_anchor');
        }

        return static::$hasAnchorColumn;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function filterPersistablePayload(array $payload): array
    {
        if (! static::hasAnchorColumn()) {
            unset($payload['daily_spend_anchor']);
        }

        return $payload;
    }

    public static function isBudgetLimitPaused(Ad $ad): bool
    {
        return $ad->pause_reason === 'budget_limit'
            && $ad->status === Ad::STATUS_PAUSED;
    }

    /**
     * Session spend for today = Meta today spend minus anchor (resets after budget pause / publish).
     */
    public static function sessionSpend(Ad $ad, float $metaTodaySpend): float
    {
        if (static::isBudgetLimitPaused($ad)) {
            return 0;
        }

        $today = Carbon::today()->toDateString();

        if ($ad->spend_date && $ad->spend_date->toDateString() !== $today) {
            return max(0, $metaTodaySpend);
        }

        $anchor = (float) ($ad->daily_spend_anchor ?? 0);

        return max(0, $metaTodaySpend - $anchor);
    }

    /**
     * Capped session spend used for UI and budget checks (never above daily_budget).
     */
    public static function cappedSessionSpend(Ad $ad, float $metaTodaySpend): float
    {
        $session = static::sessionSpend($ad, $metaTodaySpend);
        $budget = (float) $ad->daily_budget;

        if ($budget <= 0) {
            return $session;
        }

        return min($session, $budget);
    }

    /**
     * @return array{daily_spend: float, spend_date: string, daily_spend_anchor?: float}
     */
    public static function metricsPayloadFromMetaToday(Ad $ad, float $metaTodaySpend): array
    {
        $today = Carbon::today()->toDateString();

        if (static::isBudgetLimitPaused($ad)) {
            $payload = [
                'daily_spend' => 0,
                'spend_date' => $today,
            ];

            if (static::hasAnchorColumn() && (float) ($ad->daily_spend_anchor ?? 0) < $metaTodaySpend) {
                $payload['daily_spend_anchor'] = max(0, $metaTodaySpend);
            }

            return static::filterPersistablePayload($payload);
        }

        $payload = [
            'daily_spend' => static::cappedSessionSpend($ad, $metaTodaySpend),
            'spend_date' => $today,
        ];

        if ($ad->spend_date && $ad->spend_date->toDateString() !== $today) {
            if (static::hasAnchorColumn()) {
                $payload['daily_spend_anchor'] = 0;
            }

            $payload['daily_spend'] = static::cappedSessionSpend($ad, $metaTodaySpend);
        }

        return static::filterPersistablePayload($payload);
    }

    /**
     * Fix legacy rows: budget-paused ads with Meta today spend above cap get a reset anchor.
     */
    public static function reconcileBudgetLimitPause(Ad $ad, float $metaTodaySpend): void
    {
        if (! static::isBudgetLimitPaused($ad)) {
            return;
        }

        if ((float) $ad->daily_budget <= 0) {
            return;
        }

        if (static::sessionSpend($ad, $metaTodaySpend) < (float) $ad->daily_budget) {
            return;
        }

        if (static::hasAnchorColumn() && (float) ($ad->daily_spend_anchor ?? 0) >= $metaTodaySpend) {
            return;
        }

        $today = Carbon::today()->toDateString();

        $payload = static::filterPersistablePayload([
            'daily_spend' => 0,
            'spend_date' => $today,
            'daily_spend_anchor' => max(0, $metaTodaySpend),
        ]);

        $ad->update($payload);

        $ad->daily_spend = 0;
        $ad->spend_date = $today;

        if (static::hasAnchorColumn()) {
            $ad->daily_spend_anchor = max(0, $metaTodaySpend);
        }
    }

    /**
     * Start a new spend session (daily counter at $0; lifetime spend unchanged).
     */
    public static function beginNewSpendSession(Ad $ad, float $metaTodaySpend): void
    {
        $today = Carbon::today()->toDateString();

        $payload = static::filterPersistablePayload([
            'daily_spend_anchor' => max(0, $metaTodaySpend),
            'daily_spend' => 0,
            'spend_date' => $today,
        ]);

        $ad->update($payload);

        $ad->daily_spend = 0;
        $ad->spend_date = $today;

        if (static::hasAnchorColumn()) {
            $ad->daily_spend_anchor = max(0, $metaTodaySpend);
        }
    }

    /**
     * Align Meta ad set daily budget (cents) with the ad's daily limit.
     */
    public static function syncMetaAdSetBudget(Ad $ad, MetaAdsService $meta): void
    {
        $budget = (float) $ad->daily_budget;

        if ($budget <= 0) {
            return;
        }

        $ad->loadMissing('adSet');

        if (! $ad->adSet?->meta_id) {
            return;
        }

        try {
            $cents = (int) round($budget * 100);
            $response = $meta->updateAdSet($ad->adSet->meta_id, [
                'daily_budget' => $cents,
            ]);

            if (isset($response['error'])) {
                throw new \Exception($response['error']['message'] ?? 'Ad set budget sync failed');
            }

            $ad->adSet->update(['daily_budget' => $cents]);
        } catch (Throwable $e) {
            Log::warning('AD_META_ADSET_BUDGET_SYNC_FAILED', [
                'ad_id' => $ad->id,
                'adset_meta_id' => $ad->adSet->meta_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Pause on Meta when session spend reaches the daily budget; reset daily counter for republish.
     */
    public static function enforce(Ad $ad, MetaAdsService $meta, ?float $metaTodaySpend = null): void
    {
        if (! $ad->meta_ad_id || ! $ad->daily_budget || $ad->daily_budget <= 0) {
            return;
        }

        if ($metaTodaySpend !== null) {
            $session = static::sessionSpend($ad, $metaTodaySpend);
        } else {
            $session = (float) $ad->daily_spend;
        }

        if ($session < (float) $ad->daily_budget) {
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

            $anchorSpend = $metaTodaySpend ?? (float) ($ad->daily_spend_anchor ?? 0) + (float) $ad->daily_spend;

            if ($metaTodaySpend !== null) {
                $anchorSpend = $metaTodaySpend;
            }

            $today = Carbon::today()->toDateString();

            $payload = static::filterPersistablePayload([
                'status' => Ad::STATUS_PAUSED,
                'pause_reason' => 'budget_limit',
                'daily_spend' => 0,
                'daily_spend_anchor' => max(0, $anchorSpend),
                'spend_date' => $today,
            ]);

            $ad->update($payload);

            $ad->status = Ad::STATUS_PAUSED;
            $ad->pause_reason = 'budget_limit';
            $ad->daily_spend = 0;
            $ad->spend_date = $today;

            if (static::hasAnchorColumn()) {
                $ad->daily_spend_anchor = max(0, $anchorSpend);
            }

            Log::info('AD_AUTO_PAUSED_BUDGET', [
                'ad_id' => $ad->id,
                'meta_ad_id' => $ad->meta_ad_id,
                'daily_budget' => $ad->daily_budget,
                'anchor' => $anchorSpend,
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
        if ((float) $ad->daily_budget <= 0) {
            return true;
        }

        if (static::isBudgetLimitPaused($ad)) {
            return true;
        }

        return ! $ad->hasReachedDailyBudget();
    }

    public static function publishBlockedMessage(Ad $ad): string
    {
        $spent = static::isBudgetLimitPaused($ad)
            ? (float) $ad->daily_budget
            : (float) $ad->daily_spend;

        return sprintf(
            'Daily budget reached ($%s of $%s). Increase the daily budget in Edit, or use Publish again after a budget pause.',
            number_format($spent, 2),
            number_format((float) $ad->daily_budget, 2)
        );
    }
}
