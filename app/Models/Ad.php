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

    /* Budget control */
    'daily_budget',
    'daily_spend',
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