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
    | STATUS CONSTANTS
    |--------------------------------------------------------------------------
    */

    const STATUS_BOT = 'bot';
    const STATUS_HUMAN = 'human';
    const STATUS_ESCALATED = 'escalated';
    const STATUS_CLOSED = 'closed';


    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'client_id',
        'chatbot_id',
        'phone_number',
        'channel',

        'status',
        'assigned_agent_id',

        'customer_name',
        'customer_email',
        'is_profile_completed',
        'profile_step',

        'meta_campaign_id',
        'meta_adset_id',
        'meta_ad_id',
        'source',
        'first_contact_at',

        'last_activity_at',
        'last_message_at',

        'escalation_reason',
        'escalation_started_at',
        'escalation_level',

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
        'conversation_score'    => 'float',
        'is_active'             => 'boolean',
        'is_profile_completed'  => 'boolean',

        'first_contact_at'      => 'datetime',
        'last_activity_at'      => 'datetime',
        'last_message_at'       => 'datetime',
        'escalation_started_at' => 'datetime',

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
            $conversation->status ??= self::STATUS_BOT;
            $conversation->source ??= 'organic';
            $conversation->first_contact_at ??= now();

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

    /*
    |--------------------------------------------------------------------------
    | AGENT RELATIONS
    |--------------------------------------------------------------------------
    */

    public function assignedAgent()
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    /*
    | Alias for controller compatibility
    */

    public function agent()
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
        return $query->where('status', self::STATUS_BOT);
    }

    public function scopeHuman(Builder $query)
    {
        return $query->where('status', self::STATUS_HUMAN);
    }

    public function scopeEscalated(Builder $query)
    {
        return $query->where('status', self::STATUS_ESCALATED);
    }

    public function scopeClosed(Builder $query)
    {
        return $query->where('status', self::STATUS_CLOSED);
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
            'status' => self::STATUS_HUMAN,
            'assigned_agent_id' => $agentId,
            'escalation_started_at' => now(),
        ]);
    }

    public function markAsBot(): void
    {
        $this->update([
            'status' => self::STATUS_BOT,
            'assigned_agent_id' => null,
            'escalation_started_at' => null,
        ]);
    }

    public function escalate(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_ESCALATED,
            'escalation_reason' => $reason,
            'escalation_started_at' => now(),
        ]);
    }

    public function close(): void
    {
        $this->update([
            'status' => self::STATUS_CLOSED,
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
    | ESCALATION HELPERS
    |--------------------------------------------------------------------------
    */

    public function escalationTimeSeconds(): int
    {
        if (!$this->escalation_started_at) {
            return 0;
        }

        return now()->diffInSeconds($this->escalation_started_at);
    }

    public function isEscalated(): bool
    {
        return $this->status === self::STATUS_HUMAN
            || $this->status === self::STATUS_ESCALATED;
    }


    /*
    |--------------------------------------------------------------------------
    | ATTRIBUTION MANAGEMENT
    |--------------------------------------------------------------------------
    */

    public function assignAttribution(
        ?string $campaignId,
        ?string $adsetId,
        ?string $adId,
        string $source = 'organic'
    ): void {

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
        return $this->status === self::STATUS_BOT;
    }

    public function isHumanHandled(): bool
    {
        return $this->status === self::STATUS_HUMAN;
    }


    /*
    |--------------------------------------------------------------------------
    | UNREAD MESSAGES
    |--------------------------------------------------------------------------
    */

    public function unreadMessages()
    {
        return $this->messages()
            ->where('direction', 'incoming')
            ->where('is_read', false);
    }

    /**
     * Last message from the customer (for “online” / last seen).
     */
    public function lastCustomerMessage(): ?Message
    {
        if ($this->relationLoaded('messages')) {
            return $this->messages
                ->where('direction', 'incoming')
                ->sortByDesc('id')
                ->first();
        }

        return $this->messages()
            ->where('direction', 'incoming')
            ->latest('id')
            ->first();
    }

    public function getIsOnlineAttribute(): bool
    {
        $m = $this->lastCustomerMessage();

        return $m && $m->created_at && $m->created_at->gt(now()->subMinutes(3));
    }

    public function customerLastSeenLabel(): string
    {
        $m = $this->lastCustomerMessage();
        if (! $m || ! $m->created_at) {
            return 'No messages yet';
        }

        if ($m->created_at->gt(now()->subMinutes(3))) {
            return 'Online';
        }

        return 'Last seen '.$m->created_at->diffForHumans();
    }
}