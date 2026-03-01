<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'chatbot_id',
        'phone_number',
        'channel',               // whatsapp | web | telegram | etc
        'status',                // bot | human | closed | escalated
        'assigned_agent_id',
        'last_activity_at',
        'last_message_at',
        'escalation_reason',
        'metadata',              // JSON
        'conversation_score',    // optional AI scoring
        'is_active',
    ];

    protected $casts = [
        'metadata'            => 'array',
        'last_activity_at'    => 'datetime',
        'last_message_at'     => 'datetime',
        'conversation_score'  => 'float',
        'is_active'           => 'boolean',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

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

    /*
    |--------------------------------------------------------------------------
    | HELPER METHODS
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
}