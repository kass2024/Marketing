<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ConversationState extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'current_node_id',
        'last_interaction_at',
    ];

    protected $casts = [
        'last_interaction_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function currentNode()
    {
        return $this->belongsTo(ChatbotNode::class, 'current_node_id');
    }
}