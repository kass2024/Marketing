<?php

namespace App\Models;

use App\Support\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | SUBSCRIPTION PLANS
    |--------------------------------------------------------------------------
    */

    public const PLAN_FREE       = 'free';
    public const PLAN_PRO        = 'pro';
    public const PLAN_ENTERPRISE = 'enterprise';

    /*
    |--------------------------------------------------------------------------
    | SUBSCRIPTION STATUS
    |--------------------------------------------------------------------------
    */

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_INACTIVE  = 'inactive';
    public const STATUS_CANCELLED = 'cancelled';

    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'user_id',
        'company_name',
        'business_email',
        'phone',
        'subscription_plan',
        'subscription_status',
        'meta_page_id',
        'meta_page_name',
        'meta_ad_account_id',
        'meta_ad_account_name',
        'whatsapp_phone_number',
        'whatsapp_phone_number_id',
        'whatsapp_verified_name',
        'whatsapp_verification_status',
        'whatsapp_verified_at',
        'whatsapp_meta_synced_at',
        'is_platform',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_platform' => 'boolean',
        'whatsapp_verified_at' => 'datetime',
        'whatsapp_meta_synced_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    /**
     * Client owner user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Meta connection (ads + whatsapp)
     */
    public function metaConnection(): HasOne
    {
        return $this->hasOne(MetaConnection::class);
    }

    /**
     * Per-tenant Meta/WhatsApp connection (not the platform default from .env).
     */
    public function platformMetaConnection(): HasOne
    {
        return $this->hasOne(PlatformMetaConnection::class)
            ->where('is_platform_default', false);
    }

    public function platformMetaConnections(): HasMany
    {
        return $this->hasMany(PlatformMetaConnection::class);
    }

    /**
     * Campaigns
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * Chatbots
     */
    public function chatbots(): HasMany
    {
        return $this->hasMany(Chatbot::class);
    }

    /**
     * WhatsApp templates
     */
    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    /**
     * Conversations
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('subscription_status', self::STATUS_ACTIVE);
    }

    /*
    |--------------------------------------------------------------------------
    | SUBSCRIPTION HELPERS
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->subscription_status === self::STATUS_ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->subscription_status === self::STATUS_INACTIVE;
    }

    public function isCancelled(): bool
    {
        return $this->subscription_status === self::STATUS_CANCELLED;
    }

    public function isFree(): bool
    {
        return $this->subscription_plan === self::PLAN_FREE;
    }

    public function isPro(): bool
    {
        return $this->subscription_plan === self::PLAN_PRO;
    }

    public function isEnterprise(): bool
    {
        return $this->subscription_plan === self::PLAN_ENTERPRISE;
    }

    /*
    |--------------------------------------------------------------------------
    | PLAN LIMITS
    |--------------------------------------------------------------------------
    */

    public function campaignLimit(): int
    {
        return match ($this->subscription_plan) {
            self::PLAN_FREE       => 1,
            self::PLAN_PRO        => 10,
            self::PLAN_ENTERPRISE => 9999,
            default               => 1,
        };
    }

    public function chatbotLimit(): int
    {
        return match ($this->subscription_plan) {
            self::PLAN_FREE       => 1,
            self::PLAN_PRO        => 5,
            self::PLAN_ENTERPRISE => 9999,
            default               => 1,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | USAGE CHECKS
    |--------------------------------------------------------------------------
    */

    public function canCreateCampaign(): bool
    {
        return $this->campaigns()->count() < $this->campaignLimit();
    }

    public function canCreateChatbot(): bool
    {
        return $this->chatbots()->count() < $this->chatbotLimit();
    }

    /*
    |--------------------------------------------------------------------------
    | ANALYTICS HELPERS
    |--------------------------------------------------------------------------
    */

    public function activeCampaignsCount(): int
    {
        return $this->campaigns()
            ->where('status', 'active')
            ->count();
    }

    public function totalLeads(): int
    {
        return (int) $this->campaigns()->sum('leads_generated');
    }

    public function totalSpend(): float
    {
        return (float) $this->campaigns()->sum('spend');
    }

    /*
    |--------------------------------------------------------------------------
    | PLATFORM HEALTH
    |--------------------------------------------------------------------------
    */

    public function hasMetaConnected(): bool
    {
        if ($this->is_platform) {
            return app(\App\Services\Tenant\TenantConnectionResolver::class)->platformDefault() !== null;
        }

        if (TenantScope::tenantsSharePlatformMeta()) {
            return app(\App\Services\Tenant\TenantConnectionResolver::class)->platformDefault() !== null;
        }

        return filled($this->meta_page_id) && $this->isWhatsAppVerified();
    }

    public function hasPublishingProfile(): bool
    {
        if ($this->is_platform || TenantScope::tenantsSharePlatformMeta()) {
            return true;
        }

        return filled($this->meta_page_id) && $this->isWhatsAppVerified();
    }

    public function isWhatsAppVerified(): bool
    {
        if (TenantScope::tenantsSharePlatformMeta() || $this->is_platform) {
            return true;
        }

        return $this->whatsapp_verification_status === 'verified'
            && filled($this->whatsapp_phone_number);
    }

    public function needsWhatsAppVerification(): bool
    {
        if ($this->is_platform || TenantScope::tenantsSharePlatformMeta()) {
            return false;
        }

        return filled($this->whatsapp_phone_number)
            && ! $this->isWhatsAppVerified();
    }

}