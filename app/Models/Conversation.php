<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'chatbot_id',
        'phone_number',
        'status',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
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
}