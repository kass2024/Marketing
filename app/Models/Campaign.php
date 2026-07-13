<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    protected $table = 'campaigns';


    /*
    |--------------------------------------------------------------------------
    | STATUS CONSTANTS
    |--------------------------------------------------------------------------
    */

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_PAUSED    = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ARCHIVED  = 'archived';

    /**
     * Map Meta / mixed-case status strings to local campaign enum.
     */
    public static function normalizeStatus(?string $status): string
    {
        $s = strtoupper(trim((string) $status));

        return match ($s) {
            'ACTIVE', 'WITH_ISSUES', 'IN_PROCESS', 'PREAPPROVED' => self::STATUS_ACTIVE,
            'PAUSED', 'CAMPAIGN_PAUSED', 'ADSET_PAUSED', 'PENDING_BILLING_INFO' => self::STATUS_PAUSED,
            'COMPLETED' => self::STATUS_COMPLETED,
            'ARCHIVED', 'DELETED' => self::STATUS_ARCHIVED,
            'PENDING_REVIEW', 'PENDING', 'DISAPPROVED' => self::STATUS_PAUSED,
            'DRAFT', '' => self::STATUS_DRAFT,
            default => in_array(strtolower((string) $status), [
                self::STATUS_DRAFT,
                self::STATUS_ACTIVE,
                self::STATUS_PAUSED,
                self::STATUS_COMPLETED,
                self::STATUS_ARCHIVED,
            ], true) ? strtolower((string) $status) : self::STATUS_DRAFT,
        };
    }

    public function deliveryLabel(): string
    {
        $effective = strtoupper(trim((string) ($this->meta_effective_status ?? '')));

        if ($effective !== '') {
            return match ($effective) {
                'ACTIVE', 'WITH_ISSUES', 'IN_PROCESS', 'PREAPPROVED' => 'Active',
                'PAUSED', 'CAMPAIGN_PAUSED', 'ADSET_PAUSED', 'PENDING_BILLING_INFO' => 'Paused',
                'PENDING_REVIEW', 'PENDING' => 'In review',
                'DISAPPROVED' => 'Disapproved',
                'COMPLETED' => 'Completed',
                'ARCHIVED', 'DELETED' => 'Archived',
                default => ucwords(strtolower(str_replace('_', ' ', $effective))),
            };
        }

        return match ($this->normalizedStatus()) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_PAUSED => 'Paused',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_ARCHIVED => 'Archived',
            default => 'Draft',
        };
    }

    public function normalizedStatus(): string
    {
        return self::normalizeStatus($this->status);
    }

    public function isDelivering(): bool
    {
        return $this->normalizedStatus() === self::STATUS_ACTIVE;
    }


    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'ad_account_id',
        'client_id',
        'meta_page_id',

        // Meta API ID
        'meta_id',

        // basic info
        'name',
        'objective',

        // budgets
        'daily_budget',
        'budget',

        // metrics cache
        'spend',
        'impressions',
        'clicks',
        'leads',

        // status
        'status',

        // schedule
        'started_at',
        'ended_at',

        // marketing wizard
        'marketing_channel',
        'wizard_state',
        'meta_effective_status',
        'meta_review_feedback',
        'platform_meta_connection_id',
    ];


    /*
    |--------------------------------------------------------------------------
    | Attribute Casting
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'daily_budget' => 'float',
        'budget'       => 'float',
        'spend'        => 'float',

        'impressions'  => 'integer',
        'clicks'       => 'integer',
        'leads'        => 'integer',

        'started_at'   => 'datetime',
        'ended_at'     => 'datetime',
        'wizard_state' => 'array',
    ];


    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Campaign belongs to Ad Account
     */
    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class);
    }


    /**
     * Campaign has many AdSets
     */
    public function adsets(): HasMany
    {
        return $this->hasMany(AdSet::class);
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(Creative::class);
    }


    /**
     * Campaign has many Ads through AdSets
     */
    public function ads()
    {
        return $this->hasManyThrough(
            Ad::class,
            AdSet::class
        );
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

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('started_at')
                  ->orWhere('started_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ended_at')
                  ->orWhere('ended_at', '>=', now());
            });
    }


    /*
    |--------------------------------------------------------------------------
    | Computed Attributes
    |--------------------------------------------------------------------------
    */

    public function getFormattedBudgetAttribute(): string
    {
        return '$' . number_format($this->budget, 2);
    }


    public function getCtrAttribute(): float
    {
        if ($this->impressions == 0) {
            return 0;
        }

        return round(($this->clicks / $this->impressions) * 100, 2);
    }


    public function getCpcAttribute(): float
    {
        if ($this->clicks == 0) {
            return 0;
        }

        return round($this->spend / $this->clicks, 2);
    }


    public function getCpmAttribute(): float
    {
        if ($this->impressions == 0) {
            return 0;
        }

        return round(($this->spend / $this->impressions) * 1000, 2);
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
        return $this->normalizedStatus() === self::STATUS_DRAFT;
    }


    /*
    |--------------------------------------------------------------------------
    | Business Logic
    |--------------------------------------------------------------------------
    */

    public function activate(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'meta_effective_status' => 'ACTIVE',
        ]);
    }

    public function pause(): void
    {
        $this->update([
            'status' => self::STATUS_PAUSED,
            'meta_effective_status' => 'PAUSED',
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED
        ]);
    }
   /*
    |--------------------------------------------------------------------------
    | Metrics Aggregation
    |--------------------------------------------------------------------------
    */

    public function refreshMetrics(): void
    {
        $ads = $this->ads;

        $this->update([
            'impressions' => $ads->sum('impressions'),
            'clicks'      => $ads->sum('clicks'),
            'spend'       => $ads->sum('spend'),
        ]);
    }
    public function client()
{
    return $this->belongsTo(\App\Models\Client::class);
}
}