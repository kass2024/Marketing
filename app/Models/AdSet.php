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
    | Mass Assignment
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'campaign_id',
        'meta_id',          // Meta AdSet ID
        'name',
        'daily_budget',
        'optimization_goal',
        'billing_event',
        'targeting',
        'status',
        'impressions',
        'clicks',
        'spend'
    ];


    /*
    |--------------------------------------------------------------------------
    | Casting
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'targeting' => 'array',
        'daily_budget' => 'integer',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'spend' => 'float',
    ];


    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Campaign this AdSet belongs to
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }


    /**
     * Ads inside this AdSet
     */
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
        return $query->where('status', 'ACTIVE');
    }


    public function scopePaused($query)
    {
        return $query->where('status', 'PAUSED');
    }


    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getBudgetFormattedAttribute(): string
    {
        return '$' . number_format($this->daily_budget / 100, 2);
    }


    public function getCtrAttribute(): string
    {
        if ($this->impressions == 0) {
            return '0%';
        }

        return number_format(($this->clicks / $this->impressions) * 100, 2) . '%';
    }


    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }


    public function isPaused(): bool
    {
        return $this->status === 'PAUSED';
    }
}