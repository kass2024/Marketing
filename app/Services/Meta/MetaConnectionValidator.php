<?php

namespace App\Services\Meta;

use App\Models\PlatformMetaConnection;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaConnectionValidator
{
    protected string $graphUrl;
    protected string $graphVersion;

    public function __construct()
    {
        $this->graphVersion = config('services.meta.graph_version', 'v19.0');
        $this->graphUrl = rtrim(config('services.meta.graph_url', 'https://graph.facebook.com'), '/');
    }

    /**
     * @return array{valid: bool, errors: array<int, array{code: string, message: string, fix: string}>, connection: ?PlatformMetaConnection}
     */
    public function validate(?PlatformMetaConnection $connection = null): array
    {
        $errors = [];
        $connection = $connection ?? PlatformMetaConnection::query()->latest()->first();

        if (! $connection) {
            return [
                'valid' => false,
                'errors' => [[
                    'code' => 'no_connection',
                    'message' => 'No Meta platform connection found.',
                    'fix' => 'Go to Admin → Meta Connection and connect your Business Manager account.',
                ]],
                'connection' => null,
            ];
        }

        $token = $connection->plainAccessToken();
        if (! $token) {
            $errors[] = [
                'code' => 'invalid_token',
                'message' => 'Access token is missing or cannot be decrypted.',
                'fix' => 'Reconnect Meta in Admin → Meta Connection.',
            ];
        }

        if ($connection->token_expires_at && $connection->token_expires_at->isPast()) {
            $errors[] = [
                'code' => 'token_expired',
                'message' => 'Meta access token has expired.',
                'fix' => 'Reconnect Meta to refresh the token.',
            ];
        }

        $required = [
            'business_id' => ['code' => 'missing_business', 'label' => 'Business ID'],
            'ad_account_id' => ['code' => 'missing_ad_account', 'label' => 'Ad Account ID'],
            'page_id' => ['code' => 'missing_page', 'label' => 'Facebook Page ID'],
            'whatsapp_business_id' => ['code' => 'missing_waba', 'label' => 'WhatsApp Business Account ID'],
            'whatsapp_phone_number_id' => ['code' => 'missing_phone_number_id', 'label' => 'WhatsApp Phone Number ID'],
        ];

        foreach ($required as $field => $meta) {
            if (empty($connection->{$field})) {
                $errors[] = [
                    'code' => $meta['code'],
                    'message' => "{$meta['label']} is not configured.",
                    'fix' => 'Reconnect Meta or complete onboarding to link Page, Instagram, and WhatsApp.',
                ];
            }
        }

        $requiredPermissions = config('services.meta.required_permissions', []);
        $granted = $connection->granted_permissions ?? [];

        foreach ($requiredPermissions as $permission) {
            if (! in_array($permission, $granted, true)) {
                $errors[] = [
                    'code' => 'permission_missing',
                    'message' => "Missing permission: {$permission}",
                    'fix' => 'Reconnect Meta and grant all requested permissions in the OAuth dialog.',
                ];
            }
        }

        if ($token && empty($errors)) {
            try {
                $response = Http::timeout(20)->get("{$this->graphUrl}/{$this->graphVersion}/me", [
                    'access_token' => $token,
                    'fields' => 'id,name',
                ]);

                if (! $response->ok()) {
                    $message = $response->json('error.message') ?? 'Token validation failed.';
                    $errors[] = [
                        'code' => 'token_invalid',
                        'message' => $message,
                        'fix' => 'Reconnect Meta. If the issue persists, check app mode and user roles in Business Manager.',
                    ];
                }
            } catch (Exception $e) {
                Log::warning('META_CONNECTION_VALIDATE_FAILED', ['error' => $e->getMessage()]);
                $errors[] = [
                    'code' => 'network_error',
                    'message' => 'Could not reach Meta API to validate token.',
                    'fix' => 'Check server network/DNS and try again.',
                ];
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'connection' => $connection,
        ];
    }

    public function assertValid(?PlatformMetaConnection $connection = null): PlatformMetaConnection
    {
        $result = $this->validate($connection);

        if (! $result['valid']) {
            $messages = collect($result['errors'])->pluck('message')->implode(' ');
            throw new Exception($messages);
        }

        return $result['connection'];
    }
}
