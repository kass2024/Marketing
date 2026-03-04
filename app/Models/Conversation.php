<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Conversation extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'client_id',
        'chatbot_id',
        'phone_number',
        'channel',               // whatsapp | web | telegram | etc
        'status',                // bot | human | closed | escalated
        'assigned_agent_id',

        // Onboarding
        'customer_name',
        'customer_email',
        'is_profile_completed',
        'profile_step',

        // Ads Attribution (Enterprise)
        'meta_campaign_id',
        'meta_adset_id',
        'meta_ad_id',
        'source',                // organic | paid
        'first_contact_at',

        // System
        'last_activity_at',
        'last_message_at',
        'escalation_reason',
        'metadata',
        'conversation_score',
        'is_active',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'metadata'              => 'array',
        'last_activity_at'      => 'datetime',
        'last_message_at'       => 'datetime',
        'first_contact_at'      => 'datetime',
        'conversation_score'    => 'float',
        'is_active'             => 'boolean',
        'is_profile_completed'  => 'boolean',
        'created_at'            => 'datetime',
        'updated_at'            => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | DEFAULT VALUES
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        static::creating(function ($conversation) {
            $conversation->is_active ??= true;
            $conversation->status ??= 'bot';
            $conversation->source ??= 'organic';
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function chatbot()
    {
        return $this->belongsTo(Chatbot::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function state()
    {
        return $this->hasOne(ConversationState::class);
    }

    public function assignedAgent()
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBot(Builder $query)
    {
        return $query->where('status', 'bot');
    }

    public function scopeHuman(Builder $query)
    {
        return $query->where('status', 'human');
    }

    public function scopeEscalated(Builder $query)
    {
        return $query->where('status', 'escalated');
    }

    public function scopeClosed(Builder $query)
    {
        return $query->where('status', 'closed');
    }

    public function scopePaid(Builder $query)
    {
        return $query->where('source', 'paid');
    }

    public function scopeOrganic(Builder $query)
    {
        return $query->where('source', 'organic');
    }

    public function scopeProfileCompleted(Builder $query)
    {
        return $query->where('is_profile_completed', true);
    }

    public function scopeProfileIncomplete(Builder $query)
    {
        return $query->where('is_profile_completed', false);
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS MANAGEMENT
    |--------------------------------------------------------------------------
    */

    public function markAsHuman(?int $agentId = null): void
    {
        $this->update([
            'status' => 'human',
            'assigned_agent_id' => $agentId,
        ]);
    }

    public function markAsBot(): void
    {
        $this->update([
            'status' => 'bot',
            'assigned_agent_id' => null,
        ]);
    }

    public function escalate(string $reason): void
    {
        $this->update([
            'status' => 'escalated',
            'escalation_reason' => $reason,
        ]);
    }

    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'is_active' => false,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVITY MANAGEMENT
    |--------------------------------------------------------------------------
    */

    public function updateActivity(): void
    {
        $this->update([
            'last_activity_at' => now(),
            'last_message_at'  => now(),
        ]);
    }

    public function incrementScore(float $value): void
    {
        $this->update([
            'conversation_score' => ($this->conversation_score ?? 0) + $value
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ATTRIBUTION MANAGEMENT (Enterprise)
    |--------------------------------------------------------------------------
    */

    public function assignAttribution(
        ?string $campaignId,
        ?string $adsetId,
        ?string $adId,
        string $source = 'organic'
    ): void {
        // First-touch protection
        if ($this->source === 'organic' && $source === 'paid') {
            $this->update([
                'meta_campaign_id' => $campaignId,
                'meta_adset_id'    => $adsetId,
                'meta_ad_id'       => $adId,
                'source'           => 'paid',
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ONBOARDING HELPERS
    |--------------------------------------------------------------------------
    */

    public function completeProfile(string $name, string $email): void
    {
        $this->update([
            'customer_name'        => $name,
            'customer_email'       => strtolower($email),
            'is_profile_completed' => true,
            'profile_step'         => 'completed',
        ]);
    }

    public function startOnboarding(): void
    {
        if (!$this->profile_step) {
            $this->update([
                'profile_step' => 'ask_name'
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ANALYTICS HELPERS
    |--------------------------------------------------------------------------
    */

    public function isPaid(): bool
    {
        return $this->source === 'paid';
    }

    public function isBotHandled(): bool
    {
        return $this->status === 'bot';
    }

    public function isHumanHandled(): bool
    {
        return $this->status === 'human';
    }
}