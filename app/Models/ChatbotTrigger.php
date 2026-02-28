<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatbotTrigger extends Model
{
    use HasFactory;

    protected $fillable = [
        'chatbot_id',
        'trigger_type',
        'keyword',
    ];

    public function chatbot()
    {
        return $this->belongsTo(Chatbot::class);
    }
}