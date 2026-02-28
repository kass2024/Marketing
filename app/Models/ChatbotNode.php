<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatbotNode extends Model
{
    use HasFactory;

    protected $fillable = [
        'chatbot_id',
        'type',
        'content',
        'options',
        'next_node_id',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    public function chatbot()
    {
        return $this->belongsTo(Chatbot::class);
    }

    public function nextNode()
    {
        return $this->belongsTo(ChatbotNode::class, 'next_node_id');
    }
}