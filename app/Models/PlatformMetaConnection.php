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
        'page_id',
        'page_name',
        'instagram_business_account_id',
        'whatsapp_business_id',
        'whatsapp_phone_number_id',
        'whatsapp_phone_number',
        'access_token',
        'token_expires_at',
        'granted_permissions',
        'is_active',
    ];

    protected $casts = [
        'granted_permissions' => 'array',
        'token_expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function plainAccessToken(): ?string
    {
        if (! $this->access_token) {
            return null;
        }

        try {
            return decrypt($this->access_token);
        } catch (\Throwable) {
            return $this->access_token;
        }
    }

    public function storeAccessToken(string $token, ?\DateTimeInterface $expiresAt = null): void
    {
        $this->access_token = encrypt($token);
        $this->token_expires_at = $expiresAt;
        $this->save();
    }
}