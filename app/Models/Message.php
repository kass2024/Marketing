<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'direction',            // incoming | outgoing | system
        'type',                 // text | pdf | image | audio | system
        'content',              // main message text
        'meta',                 // structured JSON data
        'status',               // pending | sent | delivered | failed | read
        'external_message_id',  // WhatsApp / Twilio ID
        'confidence',           // AI confidence score
        'source',               // faq | grounded_ai | ai | system
    ];

    protected $casts = [
        'meta'       => 'array',
        'confidence' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SCOPES (Enterprise Filtering)
    |--------------------------------------------------------------------------
    */

    public function scopeIncoming(Builder $query)
    {
        return $query->where('direction', 'incoming');
    }

    public function scopeOutgoing(Builder $query)
    {
        return $query->where('direction', 'outgoing');
    }

    public function scopeDelivered(Builder $query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeFailed(Builder $query)
    {
        return $query->where('status', 'failed');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER METHODS
    |--------------------------------------------------------------------------
    */

    public function isIncoming(): bool
    {
        return $this->direction === 'incoming';
    }

    public function isOutgoing(): bool
    {
        return $this->direction === 'outgoing';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function markAsSent(?string $externalId = null): void
    {
        $this->update([
            'status' => 'sent',
            'external_message_id' => $externalId,
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update(['status' => 'delivered']);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    /*
    |--------------------------------------------------------------------------
    | FACTORY HELPERS (Optional for Testing)
    |--------------------------------------------------------------------------
    */

    public static function createIncoming(int $conversationId, string $text): self
    {
        return self::create([
            'conversation_id' => $conversationId,
            'direction'       => 'incoming',
            'type'            => 'text',
            'content'         => $text,
            'status'          => 'received',
        ]);
    }

    public static function createOutgoing(
        int $conversationId,
        string $text,
        ?float $confidence = null,
        ?string $source = null,
        array $meta = []
    ): self {
        return self::create([
            'conversation_id' => $conversationId,
            'direction'       => 'outgoing',
            'type'            => 'text',
            'content'         => $text,
            'confidence'      => $confidence,
            'source'          => $source,
            'meta'            => $meta,
            'status'          => 'pending',
        ]);
    }
}