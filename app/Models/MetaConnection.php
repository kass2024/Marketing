<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MetaConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'meta_user_id',
        'access_token',
        'token_expires_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}