<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'meta_adset_id',
        'name',
        'daily_budget',
        'targeting',
        'status',
    ];

    protected $casts = [
        'targeting' => 'array',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function ads()
    {
        return $this->hasMany(Ad::class);
    }
}