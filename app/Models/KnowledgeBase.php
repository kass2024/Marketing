<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class KnowledgeBase extends Model
{
    protected $table = 'knowledge_base';

    protected $fillable = [
        'client_id',
        'question',
        'answer',
        'embedding',
        'intent_type',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'embedding'   => 'array',
        'is_active'   => 'boolean',
        'priority'    => 'integer',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function attachments()
    {
        return $this->hasMany(KnowledgeAttachment::class, 'knowledge_base_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForClient(Builder $query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeByIntent(Builder $query, string $intent)
    {
        return $query->where('intent_type', $intent);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function hasAttachments(): bool
    {
        return $this->attachments()->exists();
    }

    public function hasPdf(): bool
    {
        return $this->attachments()
            ->where('type', 'pdf')
            ->exists();
    }

    public function formattedResponse(): array
    {
        return [
            'text' => $this->answer,
            'attachments' => $this->attachments
                ->map(function ($att) {
                    return [
                        'type' => $att->type,
                        'file_path' => $att->file_path,
                        'url' => $att->resolved_url ?? $att->url,
                    ];
                })
                ->toArray()
        ];
    }
}