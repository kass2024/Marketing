<?php

namespace App\Services\Meta;

use App\Models\MetaApiLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MetaApiLogger
{
    public function log(
        string $method,
        string $endpoint,
        array $requestPayload,
        ?array $responseBody,
        int $httpStatus,
        bool $success,
        ?string $errorMessage = null,
        ?int $durationMs = null,
        ?string $resourceType = null,
        ?int $resourceId = null,
        ?string $correlationId = null
    ): MetaApiLog {
        $sanitizedRequest = $this->redactSecrets($requestPayload);
        $sanitizedResponse = $responseBody ? $this->redactSecrets($responseBody) : null;

        $metaErrorCode = data_get($responseBody, 'error.code');
        $metaErrorType = data_get($responseBody, 'error.type');
        $isRetryable = $this->isRetryableError($metaErrorCode, $httpStatus);

        $log = MetaApiLog::create([
            'method' => strtoupper($method),
            'endpoint' => $endpoint,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'http_status' => $httpStatus,
            'success' => $success,
            'is_retryable' => $isRetryable,
            'duration_ms' => $durationMs,
            'request_payload' => $sanitizedRequest,
            'response_body' => $sanitizedResponse,
            'error_message' => $errorMessage,
            'meta_error_code' => is_numeric($metaErrorCode) ? (int) $metaErrorCode : null,
            'meta_error_type' => is_string($metaErrorType) ? $metaErrorType : null,
            'correlation_id' => $correlationId ?? (string) Str::uuid(),
            'user_id' => Auth::id(),
        ]);

        if (! $success) {
            Log::channel('stack')->error('META_API_LOG_FAILURE', [
                'log_id' => $log->id,
                'endpoint' => $endpoint,
                'error' => $errorMessage,
                'meta_error_code' => $metaErrorCode,
            ]);
        }

        return $log;
    }

    public function isRetryableError(?int $metaErrorCode, int $httpStatus): bool
    {
        if (in_array($httpStatus, [429, 500, 502, 503, 504], true)) {
            return true;
        }

        return in_array($metaErrorCode, [4, 17, 32, 613, 80004], true);
    }

    public function redactSecrets(array $payload): array
    {
        $redacted = $payload;

        foreach (['access_token', 'appsecret_proof', 'client_secret'] as $key) {
            if (array_key_exists($key, $redacted) && $redacted[$key]) {
                $redacted[$key] = '[REDACTED]';
            }
        }

        return $redacted;
    }
}
