<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaApiLog extends Model
{
    protected $fillable = [
        'method',
        'endpoint',
        'resource_type',
        'resource_id',
        'http_status',
        'success',
        'is_retryable',
        'duration_ms',
        'request_payload',
        'response_body',
        'error_message',
        'meta_error_code',
        'meta_error_type',
        'correlation_id',
        'user_id',
    ];

    protected $casts = [
        'success' => 'boolean',
        'is_retryable' => 'boolean',
        'request_payload' => 'array',
        'response_body' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function readableError(): string
    {
        if ($this->success) {
            return 'OK';
        }

        return $this->error_message
            ?? data_get($this->response_body, 'error.message')
            ?? 'Unknown Meta API error';
    }
}
