<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Chatbot extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'status',
        'is_default',
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

    public function triggers()
    {
        return $this->hasMany(ChatbotTrigger::class);
    }

    public function nodes()
    {
        return $this->hasMany(ChatbotNode::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }
}