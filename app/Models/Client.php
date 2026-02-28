<?php

namespace App\Models;

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
    public const STATUS_SUSPENDED = 'suspended';
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
        'stripe_customer_id',
        'trial_ends_at',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'trial_ends_at'  => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    /**
     * Client Owner
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Meta Connection (WhatsApp / Ads)
     */
    public function metaConnection(): HasOne
    {
        return $this->hasOne(MetaConnection::class);
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
     * WhatsApp Templates
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

    public function isSuspended(): bool
    {
        return $this->subscription_status === self::STATUS_SUSPENDED;
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
    | PLAN LIMITS (Enterprise Ready)
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
        return $this->metaConnection()->exists();
    }

    public function isTrialExpired(): bool
    {
        if (!$this->trial_ends_at) {
            return false;
        }

        return now()->greaterThan($this->trial_ends_at);
    }
}