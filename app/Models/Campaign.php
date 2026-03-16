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


    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'ad_account_id',

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
        'ended_at'
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
        'ended_at'     => 'datetime'
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
        return $this->status === self::STATUS_DRAFT;
    }


    /*
    |--------------------------------------------------------------------------
    | Business Logic
    |--------------------------------------------------------------------------
    */

    public function activate(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE
        ]);
    }

    public function pause(): void
    {
        $this->update([
            'status' => self::STATUS_PAUSED
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