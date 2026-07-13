<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformMetaConnection extends Model
{
    protected $fillable = [
        'client_id',
        'is_platform_default',
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
        'linked_waba_ids',
        'linked_instagram_ids',
        'linked_instagram_directory',
        'whatsapp_phone_number_id',
        'whatsapp_phone_number',
        'access_token',
        'token_expires_at',
        'granted_permissions',
        'is_active',
    ];

    protected $casts = [
        'granted_permissions' => 'array',
        'linked_waba_ids' => 'array',
        'linked_instagram_ids' => 'array',
        'linked_instagram_directory' => 'array',
        'token_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'is_platform_default' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function connectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePlatformDefault(Builder $query): Builder
    {
        return $query->where('is_platform_default', true);
    }

    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId)->where('is_platform_default', false);
    }

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

    /**
     * Platform API credentials with per-tenant page + WhatsApp destination overlaid.
     */
    public function withTenantProfile(?Client $client): self
    {
        if (! $client || $client->is_platform || config('platform.tenants_share_platform_meta', false)) {
            return $this;
        }

        $merged = $this->replicate();
        $merged->id = $this->id;
        $merged->exists = true;

        if ($client->meta_page_id) {
            $merged->page_id = $client->meta_page_id;
            $merged->page_name = $client->meta_page_name ?: $merged->page_name;
        }

        if ($client->whatsapp_phone_number) {
            $merged->whatsapp_phone_number = $client->whatsapp_phone_number;
        }

        if ($client->whatsapp_phone_number_id) {
            $merged->whatsapp_phone_number_id = $client->whatsapp_phone_number_id;
        }

        return $merged;
    }
}