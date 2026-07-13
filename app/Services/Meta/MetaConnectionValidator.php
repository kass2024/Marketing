<?php

namespace App\Services\Meta;

use App\Models\PlatformMetaConnection;
use App\Services\Tenant\TenantConnectionResolver;
use App\Support\TenantScope;
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
        $connection = $connection ?? app(TenantConnectionResolver::class)->forCurrentUser();

        if (! $connection) {
            $isClient = TenantScope::isScoped();

            return [
                'valid' => false,
                'errors' => [[
                    'code' => 'no_connection',
                    'message' => 'No Meta platform connection found.',
                    'fix' => $isClient
                        ? 'The platform Meta account is not configured yet. Contact your administrator.'
                        : 'Go to Admin → Tenant monitor and sync the main account from .env, or Connect Meta.',
                ]],
                'connection' => null,
            ];
        }

        $token = $connection->plainAccessToken();
        if (! $token) {
            $errors[] = [
                'code' => 'invalid_token',
                'message' => 'Access token is missing or cannot be decrypted.',
                'fix' => 'Reconnect Meta in Admin → Business Manager, or sync from .env.',
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
            'ad_account_id' => ['code' => 'missing_ad_account', 'label' => 'Ad Account ID'],
            'page_id' => ['code' => 'missing_page', 'label' => 'Facebook Page ID'],
            'whatsapp_business_id' => ['code' => 'missing_waba', 'label' => 'WhatsApp Business Account ID'],
            'whatsapp_phone_number_id' => ['code' => 'missing_phone_number_id', 'label' => 'WhatsApp Phone Number ID'],
        ];

        // business_id is nice-to-have for BM UI; ads publish only needs ad account + page + WABA phone
        if (empty($connection->business_id) && empty($connection->whatsapp_business_id)) {
            $errors[] = [
                'code' => 'missing_business',
                'message' => 'Business ID is not configured.',
                'fix' => 'Reconnect Meta or set WHATSAPP_BUSINESS_ID in .env and sync.',
            ];
        }

        foreach ($required as $field => $meta) {
            if (empty($connection->{$field})) {
                $errors[] = [
                    'code' => $meta['code'],
                    'message' => "{$meta['label']} is not configured.",
                    'fix' => 'Reconnect Meta or complete Business Manager setup to link Page, ad account, and WhatsApp.',
                ];
            }
        }

        $this->assertPermissions($connection, $token, $errors);

        if ($token && ! collect($errors)->contains('code', 'invalid_token') && ! collect($errors)->contains('code', 'token_expired')) {
            try {
                $response = Http::timeout(20)->get("{$this->graphUrl}/{$this->graphVersion}/me", [
                    'access_token' => $token,
                    'fields' => 'id,name',
                ]);

                if (! $response->ok()) {
                    $code = (int) data_get($response->json(), 'error.code');
                    $message = (string) ($response->json('error.message') ?? 'Token validation failed.');
                    // Transient Meta throttling must not block Ad Studio when IDs are already configured
                    if (in_array($code, [4, 17, 32, 613, 80004, 80008], true)
                        || str_contains(strtolower($message), 'request limit')
                        || str_contains(strtolower($message), 'rate limit')) {
                        Log::warning('META_CONNECTION_VALIDATE_RATE_LIMITED', [
                            'code' => $code,
                            'message' => $message,
                        ]);
                    } else {
                        $errors[] = [
                            'code' => 'token_invalid',
                            'message' => $message,
                            'fix' => 'Reconnect Meta. If the issue persists, check app mode and system user roles in Business Manager.',
                        ];
                    }
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

    /**
     * @param  array<int, array{code: string, message: string, fix: string}>  $errors
     */
    protected function assertPermissions(PlatformMetaConnection $connection, ?string $token, array &$errors): void
    {
        $requiredPermissions = config('services.meta.required_permissions', []);
        if ($requiredPermissions === []) {
            return;
        }

        $granted = array_values(array_filter($connection->granted_permissions ?? []));

        // System-user / .env-synced connections often have no OAuth grant list stored.
        // Trust the token when platform_controls_api is enabled and grants are empty —
        // the live /me check still proves the token works.
        if ($granted === [] && config('platform.platform_controls_api', true)) {
            return;
        }

        // Prefer live Graph permissions when we have a token and stored grants look incomplete.
        if ($token && (count(array_intersect($requiredPermissions, $granted)) < count($requiredPermissions))) {
            $live = $this->fetchGrantedPermissions($token);
            if ($live !== null) {
                $granted = $live;
                if ($connection->exists && $connection->granted_permissions !== $live) {
                    $connection->forceFill(['granted_permissions' => $live])->saveQuietly();
                }
            }
        }

        if ($granted === [] && config('platform.platform_controls_api', true)) {
            return;
        }

        foreach ($requiredPermissions as $permission) {
            if (! in_array($permission, $granted, true)) {
                $errors[] = [
                    'code' => 'permission_missing',
                    'message' => "Missing permission: {$permission}",
                    'fix' => 'Reconnect Meta and grant all requested permissions, or assign them to your system user in Business Manager.',
                ];
            }
        }
    }

    /**
     * @return array<int, string>|null
     */
    protected function fetchGrantedPermissions(string $token): ?array
    {
        try {
            $response = Http::timeout(20)->get("{$this->graphUrl}/{$this->graphVersion}/me/permissions", [
                'access_token' => $token,
            ]);

            if (! $response->ok()) {
                return null;
            }

            return collect($response->json('data', []))
                ->where('status', 'granted')
                ->pluck('permission')
                ->values()
                ->all();
        } catch (Exception $e) {
            Log::warning('META_PERMISSIONS_FETCH_FAILED', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
