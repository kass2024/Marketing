<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformMetaConnection extends Model
{
    protected $fillable = [
        'connected_by',
        'facebook_user_id',
        'business_id',
        'business_name',
        'ad_account_id',
        'ad_account_name',
        'whatsapp_business_id',
        'whatsapp_phone_number_id',
        'access_token',
        'token_expires_at',
        'granted_permissions',
    ];

    protected $casts = [
        'granted_permissions' => 'array',
        'token_expires_at' => 'datetime',
    ];
}