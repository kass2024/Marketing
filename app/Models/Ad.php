<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ad extends Model
{
    use HasFactory;

    protected $fillable = [
        'adset_id',
        'creative_id',
        'meta_ad_id',
        'name',
        'status',
        'tracking_data',
        'json_payload',
    ];

    protected $casts = [
        'tracking_data' => 'array',
        'json_payload'  => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function adSet()
    {
        return $this->belongsTo(AdSet::class);
    }

    public function creative()
    {
        return $this->belongsTo(Creative::class);
    }
}