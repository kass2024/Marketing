<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCache extends Model
{
    protected $table = 'ai_cache';

    protected $fillable = [
        'client_id',
        'message_hash',
        'response',
    ];
}