<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetaWebhookEvent extends Model
{
    protected $fillable = [
        'object_type',
        'event_type',
        'field',
        'entry_id',
        'phone_number_id',
        'ad_id',
        'campaign_id',
        'signature_valid',
        'payload',
        'correlation_id',
        'processed',
        'processing_notes',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed' => 'boolean',
    ];
}
