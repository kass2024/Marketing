<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'ad_account_id',
        'name',
        'currency',
        'account_status',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}