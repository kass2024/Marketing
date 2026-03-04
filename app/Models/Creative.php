<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Creative extends Model
{
    use HasFactory;

    protected $fillable = [
        'meta_creative_id',
        'title',
        'body',
        'image_url',
        'video_url',
        'call_to_action',
        'json_payload',
    ];

    protected $casts = [
        'json_payload' => 'array',
    ];

    public function ads()
    {
        return $this->hasMany(Ad::class);
    }
}