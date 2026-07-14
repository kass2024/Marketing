<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ad extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    protected $table = 'ads';


    /*
    |--------------------------------------------------------------------------
    | Status Constants
    |--------------------------------------------------------------------------
    */

    const STATUS_ACTIVE   = 'ACTIVE';
    const STATUS_PAUSED   = 'PAUSED';
    const STATUS_DRAFT    = 'DRAFT';
    const STATUS_ARCHIVED = 'ARCHIVED';


    /*
    |--------------------------------------------------------------------------
    | Status List
    |--------------------------------------------------------------------------
    */

    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_PAUSED,
            self::STATUS_DRAFT,
            self::STATUS_ARCHIVED
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    */
protected $fillable = [

    'adset_id',
    'creative_id',

    'meta_ad_id',

    'name',
    'status',
    'meta_effective_status',
    'meta_review_feedback',
    'meta_created_time',

    /* Budget control */
    'daily_budget',
    'daily_spend',
    'daily_spend_anchor',
    'pause_reason',
    'spend_date',

    /* Metrics */
    'impressions',
    'clicks',
    'spend',
    'ctr'
];


    /*
    |--------------------------------------------------------------------------
    | Default Values
    |--------------------------------------------------------------------------
    */

    protected $attributes = [

        'status' => self::STATUS_PAUSED,
        'impressions' => 0,
        'clicks' => 0,
        'spend' => 0,
        'ctr' => 0
    ];


    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */
protected $casts = [

    'daily_budget' => 'float',
    'daily_spend' => 'float',
    'daily_spend_anchor' => 'float',
    'spend_date' => 'date',

    'impressions' => 'integer',
    'clicks' => 'integer',
    'spend' => 'float',
    'ctr' => 'float'
];


    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Ad belongs to AdSet
     */
    public function adSet(): BelongsTo
    {
        return $this->belongsTo(AdSet::class, 'adset_id', 'id');
    }


    /**
     * Ad belongs to Creative
     */
    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class, 'creative_id', 'id');
    }


    /**
     * Access campaign through adset
     */
    public function campaign()
    {
        return $this->adSet?->campaign;
    }


    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePaused(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAUSED);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }


    /*
    |--------------------------------------------------------------------------
    | Metrics Calculations
    |--------------------------------------------------------------------------
    */

    public function getCtrAttribute($value): float
    {
        if ($value !== null) {
            return (float) $value;
        }

        if ($this->impressions <= 0) {
            return 0;
        }

        return round(($this->clicks / $this->impressions) * 100, 2);
    }


    public function getCpcAttribute(): float
    {
        if ($this->clicks <= 0) {
            return 0;
        }

        return round($this->spend / $this->clicks, 2);
    }


    public function getCpmAttribute(): float
    {
        if ($this->impressions <= 0) {
            return 0;
        }

        return round(($this->spend / $this->impressions) * 1000, 2);
    }


    /*
    |--------------------------------------------------------------------------
    | Metrics Updaters
    |--------------------------------------------------------------------------
    */

    public function increaseImpressions(int $count = 1): void
    {
        $this->increment('impressions', $count);
    }

    public function increaseClicks(int $count = 1): void
    {
        $this->increment('clicks', $count);
    }

    public function addSpend(float $amount): void
    {
        $this->increment('spend', $amount);
    }


    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }


    /*
    |--------------------------------------------------------------------------
    | Meta Helpers
    |--------------------------------------------------------------------------
    */

    public function isSynced(): bool
    {
        return !empty($this->meta_ad_id);
    }

    public function hasReachedDailyBudget(): bool
    {
        if ($this->pause_reason === 'budget_limit' && $this->status === self::STATUS_PAUSED) {
            return false;
        }

        return (float) $this->daily_budget > 0
            && (float) $this->daily_spend >= (float) $this->daily_budget;
    }

    public function displayDailySpend(): float
    {
        if ($this->pause_reason === 'budget_limit' && $this->status === self::STATUS_PAUSED) {
            return 0;
        }

        return (float) ($this->daily_spend ?? 0);
    }

    /**
     * Daily budget in USD for Ads Manager UI / AdBudgetGuard.
     * Prefer ad set (and campaign) cents from Ad Studio → Meta; fall back to ads.daily_budget dollars.
     */
    public function resolvedDailyBudgetDollars(): float
    {
        $this->loadMissing(['adSet.campaign']);

        $adSetCents = (float) ($this->adSet?->daily_budget ?? 0);
        if ($adSetCents >= 100) {
            return round($adSetCents / 100, 2);
        }

        $campaignCents = (float) ($this->adSet?->campaign?->daily_budget ?? 0);
        if ($campaignCents >= 100) {
            return round($campaignCents / 100, 2);
        }

        $local = (float) ($this->daily_budget ?? 0);
        if ($local >= 100) {
            // Legacy rows accidentally stored cents on the ad.
            return round($local / 100, 2);
        }

        return round(max(0, $local), 2);
    }

    /**
     * Copy ad-set budget onto the ad row when Studio published cents but Ad.daily_budget stayed empty/stale.
     */
    public function syncDailyBudgetFromAdSet(bool $persist = true): float
    {
        $dollars = $this->resolvedDailyBudgetDollars();
        if ($dollars <= 0) {
            return (float) ($this->daily_budget ?? 0);
        }

        if (abs((float) ($this->daily_budget ?? 0) - $dollars) >= 0.005) {
            $this->daily_budget = $dollars;
            if ($persist && $this->exists) {
                $this->forceFill(['daily_budget' => $dollars])->saveQuietly();
            }
        }

        return $dollars;
    }

    /**
     * Meta Ads Manager–style Delivery column (label, tip, key).
     *
     * @return array{label: string, key: string, tip_title: string, tip_body: string, effective: string}
     */
    public function deliveryPresentation(): array
    {
        $effective = strtoupper(trim((string) ($this->meta_effective_status ?: $this->status ?: '')));
        $impressions = (int) ($this->impressions ?? 0);

        // Meta shows "Preparing" while the auction warms up after approval (often still ACTIVE, 0 impr).
        if (in_array($effective, ['IN_PROCESS', 'PREAPPROVED'], true)
            || ($effective === 'ACTIVE' && $impressions === 0 && $this->pause_reason !== 'budget_limit')) {
            return [
                'key' => 'preparing',
                'label' => 'Preparing',
                'tip_title' => 'Preparing to deliver',
                'tip_body' => 'Your ad successfully passed review. Now our delivery system is matching it to the right bid and audience so you can start seeing impressions. Typically up to a few hours (can take up to 12 hours).',
                'effective' => $effective !== '' ? $effective : 'IN_PROCESS',
            ];
        }

        return match ($effective) {
            'ACTIVE', 'WITH_ISSUES' => [
                'key' => 'active',
                'label' => $effective === 'WITH_ISSUES' ? 'Active (issues)' : 'Active',
                'tip_title' => $effective === 'WITH_ISSUES' ? 'Delivering with issues' : 'Active',
                'tip_body' => $effective === 'WITH_ISSUES'
                    ? 'This ad is delivering but Meta reported issues. Check Ads Manager recommendations.'
                    : 'This ad is on and eligible to deliver impressions.',
                'effective' => $effective,
            ],
            'PENDING_REVIEW', 'PENDING' => [
                'key' => 'in_review',
                'label' => 'In review',
                'tip_title' => 'In review',
                'tip_body' => 'Meta is reviewing this ad before it can deliver.',
                'effective' => $effective,
            ],
            'DISAPPROVED' => [
                'key' => 'disapproved',
                'label' => 'Disapproved',
                'tip_title' => 'Disapproved',
                'tip_body' => 'Meta disapproved this ad. Edit the creative or check policy feedback.',
                'effective' => $effective,
            ],
            'PAUSED', 'CAMPAIGN_PAUSED', 'ADSET_PAUSED', 'PENDING_BILLING_INFO' => [
                'key' => 'off',
                'label' => 'Off',
                'tip_title' => 'Off',
                'tip_body' => match ($effective) {
                    'CAMPAIGN_PAUSED' => 'The campaign is paused, so this ad is not delivering.',
                    'ADSET_PAUSED' => 'The ad set is paused, so this ad is not delivering.',
                    'PENDING_BILLING_INFO' => 'Billing info is required before this ad can deliver.',
                    default => 'This ad is paused and not delivering.',
                },
                'effective' => $effective,
            ],
            'COMPLETED' => [
                'key' => 'completed',
                'label' => 'Completed',
                'tip_title' => 'Completed',
                'tip_body' => 'This ad finished its schedule and is no longer delivering.',
                'effective' => $effective,
            ],
            'ARCHIVED', 'DELETED' => [
                'key' => 'archived',
                'label' => 'Archived',
                'tip_title' => 'Archived',
                'tip_body' => 'This ad is archived or deleted on Meta.',
                'effective' => $effective,
            ],
            'DRAFT', '' => [
                'key' => 'draft',
                'label' => 'Draft',
                'tip_title' => 'Draft',
                'tip_body' => 'This ad has not been published to Meta yet.',
                'effective' => $effective !== '' ? $effective : 'DRAFT',
            ],
            default => [
                'key' => 'other',
                'label' => ucwords(strtolower(str_replace('_', ' ', $effective))),
                'tip_title' => 'Delivery status',
                'tip_body' => 'Meta effective status: '.$effective,
                'effective' => $effective,
            ],
        };
    }

    public function deliveryLabel(): string
    {
        return $this->deliveryPresentation()['label'];
    }

    /**
     * Link to Meta Ads Manager
     */
    public function getMetaUrlAttribute(): ?string
    {
        if (!$this->meta_ad_id) {
            return null;
        }

        return "https://www.facebook.com/adsmanager/manage/ads?selected_ad_ids={$this->meta_ad_id}";
    }
}