<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdSet extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    protected $table = 'ad_sets';


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
    | Mass Assignment (Editable via UI)
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        /* Relations */
        'campaign_id',

        /* Meta identifiers */
        'meta_id',

        /* Basic Info */
        'name',
        'status',

        /* Budget */
        'daily_budget',

        /* Bidding */
        'bid_strategy',
        'bid_amount',

        /* Optimization */
        'optimization_goal',
        'billing_event',

        /* Targeting */
        'targeting',

        /* Schedule */
        'start_time',
        'end_time',

        /* Cached metrics */
        'impressions',
        'clicks',
        'spend'
    ];


    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    protected $casts = [

        'targeting' => 'array',

        'daily_budget' => 'float',
        'bid_amount'   => 'float',

        'impressions'  => 'integer',
        'clicks'       => 'integer',
        'spend'        => 'float',

        'start_time'   => 'datetime',
        'end_time'     => 'datetime'
    ];


    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }


    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePaused($query)
    {
        return $query->where('status', self::STATUS_PAUSED);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeRunning($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('start_time')
                  ->orWhere('start_time', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_time')
                  ->orWhere('end_time', '>=', now());
            });
    }


    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getBudgetFormattedAttribute(): string
    {
        return '$'.number_format($this->daily_budget,2);
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
    | Helpers
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

    public function hasEnded(): bool
    {
        if (!$this->end_time) {
            return false;
        }

        return now()->greaterThan($this->end_time);
    }


    /*
    |--------------------------------------------------------------------------
    | Targeting Helpers
    |--------------------------------------------------------------------------
    */

    public function getCountries(): array
    {
        return $this->targeting['geo_locations']['countries'] ?? [];
    }

    public function getAgeRange(): array
    {
        return [
            'min' => $this->targeting['age_min'] ?? null,
            'max' => $this->targeting['age_max'] ?? null,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Metrics Helpers
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
}