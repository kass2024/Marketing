<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Campaign extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | STATUS CONSTANTS
    |--------------------------------------------------------------------------
    */

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_PAUSED    = 'paused';
    public const STATUS_COMPLETED = 'completed';


    /*
    |--------------------------------------------------------------------------
    | Mass Assignment Protection
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'client_id',
        'name',
        'objective',
        'budget',
        'start_date',
        'end_date',
        'status',
        'activated_at',
    ];


    /*
    |--------------------------------------------------------------------------
    | Attribute Casting
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'budget'        => 'decimal:2',
        'start_date'    => 'date',
        'end_date'      => 'date',
        'activated_at'  => 'datetime',
    ];


    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function client()
    {
        return $this->belongsTo(Client::class);
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

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePaused(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAUSED);
    }

    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }


    /*
    |--------------------------------------------------------------------------
    | Accessors (Computed Attributes)
    |--------------------------------------------------------------------------
    */

    public function getFormattedBudgetAttribute(): string
    {
        return number_format($this->budget, 2);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getIsDraftAttribute(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function getIsPausedAttribute(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }


    /*
    |--------------------------------------------------------------------------
    | Business Logic Methods
    |--------------------------------------------------------------------------
    */

    public function activate(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);
    }

    public function pause(): void
    {
        $this->update([
            'status' => self::STATUS_PAUSED,
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
        ]);
    }
}