<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdAccount extends Model
{
    use HasFactory;

    protected $table = 'ad_accounts';

    /**
     * Mass assignable fields
     */
    protected $fillable = [
        'client_id',
        'meta_id',
        'ad_account_id',
        'name',
        'currency',
        'account_status',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'client_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: AdAccount belongs to Client
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * UI label: replace legacy Parrot Canada branding from Meta sync.
     */
    public function getDisplayNameAttribute(): string
    {
        return self::normalizeSyncedName($this->name);
    }

    /**
     * Normalize ad account name when persisting from Meta API (or reading).
     */
    public static function normalizeSyncedName(?string $metaName): string
    {
        $name = ($metaName !== null && $metaName !== '') ? $metaName : 'Unknown';

        if (stripos($name, 'Parrot Canada') !== false) {
            return (string) config('app.name', 'Xander Global Scholars');
        }

        return $name;
    }
}