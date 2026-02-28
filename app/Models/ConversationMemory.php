<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationMemory extends Model
{
    protected $table = 'conversation_memory';

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}