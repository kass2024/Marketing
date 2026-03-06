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

    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_PAUSED = 'PAUSED';
    const STATUS_DRAFT  = 'DRAFT';
    const STATUS_ARCHIVED = 'ARCHIVED';


    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'adset_id',
        'creative_id',

        // Meta identifiers
        'meta_ad_id',

        // basic info
        'name',
        'status',

        // metrics
        'impressions',
        'clicks',
        'spend',

        // payloads
        'tracking_data',
        'json_payload'
    ];


    /*
    |--------------------------------------------------------------------------
    | Casting
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'tracking_data' => 'array',
        'json_payload'  => 'array',

        'impressions' => 'integer',
        'clicks'      => 'integer',
        'spend'       => 'float'
    ];


    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Ad belongs to an AdSet
     */
    public function adSet(): BelongsTo
    {
        return $this->belongsTo(AdSet::class);
    }


    /**
     * Ad belongs to a Creative
     */
    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class);
    }


    /**
     * Shortcut to Campaign through AdSet
     */
    public function campaign()
    {
        return $this->adSet?->campaign();
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
    | Metrics
    |--------------------------------------------------------------------------
    */

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
        return $this->status === self::STATUS_DRAFT;
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


    /*
    |--------------------------------------------------------------------------
    | Meta Payload Helpers
    |--------------------------------------------------------------------------
    */

    public function getMetaPayload(): array
    {
        return $this->json_payload ?? [];
    }

    public function setMetaPayload(array $payload): void
    {
        $this->json_payload = $payload;
        $this->save();
    }
}