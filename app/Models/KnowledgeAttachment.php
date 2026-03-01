<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeAttachment extends Model
{
    protected $table = 'knowledge_attachments';

    protected $fillable = [
        'knowledge_id', // ✅ match DB
        'type',
        'file_path',
        'url',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIP
    |--------------------------------------------------------------------------
    */

    public function knowledge()
    {
        return $this->belongsTo(KnowledgeBase::class, 'knowledge_id');
    }
}