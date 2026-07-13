<?php

namespace App\Services\Meta;

use App\Models\PlatformMetaConnection;
use App\Services\MetaAdsService;
use App\Services\Tenant\TenantConnectionResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Instagram Business accounts via Meta Marketing / Business Management APIs.
 *
 * Docs:
 * - https://developers.facebook.com/docs/marketing-api/business-asset-management/guides/instagram-accounts/
 * - GET /{business-id}/owned_instagram_accounts
 * - GET /{business-id}/owned_instagram_assets
 * - GET /{business-id}/client_instagram_assets
 * - GET /{business-id}/instagram_accounts
 * - GET /act_{ad-account-id}/instagram_accounts
 */
class InstagramBusinessAccountService
{
    protected string $graphUrl;

    protected string $graphVersion;

    public function __construct(
        protected WhatsAppBusinessAccountService $whatsapp,
        protected MetaAdsService $meta
    ) {
        $this->graphVersion = config('platform.meta.graph_version', config('services.meta.graph_version', 'v22.0'));
        $this->graphUrl = rtrim(config('platform.meta.graph_url', config('services.meta.graph_url', 'https://graph.facebook.com')), '/');
    }

    public function connection(): ?PlatformMetaConnection
    {
        return app(TenantConnectionResolver::class)->forCurrentUser()
            ?? PlatformMetaConnection::query()->platformDefault()->active()->first();
    }

    /**
     * @return array<int, array{id:string,username:?string,name:?string,source:string,profile_picture_url:?string}>
     */
    public function listAccounts(?string $businessId = null): array
    {
        $token = $this->requireToken();
        $connection = $this->connection();
        $businessId = $businessId ?: $this->whatsapp->resolveBusinessManagerId($connection);
        $byId = [];

        $fields = 'id,username,name,profile_picture_url';

        if ($businessId) {
            foreach ([
                'owned_instagram_accounts',
                'owned_instagram_assets',
                'client_instagram_assets',
                'client_instagram_accounts',
                'instagram_accounts',
                'instagram_business_accounts',
            ] as $edge) {
                foreach ($this->paginateEdge(
                    "{$this->graphUrl}/{$this->graphVersion}/{$businessId}/{$edge}",
                    [
                        'access_token' => $token,
                        'fields' => $fields,
                        'limit' => 50,
                    ]
                ) as $row) {
                    $this->mergeAccountRow($byId, $row, $edge, $token);
                }
            }
        }

        // Ad account Instagram accounts (required for ads targeting)
        $adAccount = config('services.meta.ad_account_id')
            ?: $connection?->ad_account_id
            ?: config('platform.meta.ad_account_id');
        if ($adAccount) {
            $act = str_starts_with((string) $adAccount, 'act_') ? (string) $adAccount : 'act_'.$adAccount;
            foreach ($this->paginateEdge(
                "{$this->graphUrl}/{$this->graphVersion}/{$act}/instagram_accounts",
                [
                    'access_token' => $token,
                    'fields' => 'id,username,profile_pic',
                    'limit' => 50,
                ]
            ) as $row) {
                $this->mergeAccountRow($byId, $row, 'ad_account', $token);
            }
        }

        // Pages → connected / business Instagram
        try {
            foreach ($this->meta->listPagesWithInstagram() as $page) {
                if (empty($page['instagram_id'])) {
                    continue;
                }
                $id = (string) $page['instagram_id'];
                if (! isset($byId[$id])) {
                    $byId[$id] = [
                        'id' => $id,
                        'username' => $page['instagram_username'] ?? null,
                        'name' => $page['name'] ?? null,
                        'source' => 'page',
                        'profile_picture_url' => null,
                        'page_id' => $page['id'] ?? null,
                    ];
                } elseif (empty($byId[$id]['username']) && ! empty($page['instagram_username'])) {
                    $byId[$id]['username'] = $page['instagram_username'];
                    $byId[$id]['page_id'] = $page['id'] ?? null;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('IG_LIST_PAGES_FAILED', ['error' => $e->getMessage()]);
        }

        // Locally linked / env defaults
        foreach ($this->linkedInstagramIds($connection) as $linkedId) {
            $already = collect($byId)->contains(fn ($row) => ($row['id'] ?? '') === $linkedId || ($row['asset_id'] ?? '') === $linkedId);
            if ($already) {
                continue;
            }
            $detail = $this->getAccount($linkedId);
            if ($detail) {
                $resolvedId = (string) ($detail['id'] ?? $linkedId);
                if (! isset($byId[$resolvedId])) {
                    $byId[$resolvedId] = $detail;
                }
            } else {
                $byId[$linkedId] = [
                    'id' => $linkedId,
                    'username' => null,
                    'name' => null,
                    'source' => 'linked',
                    'profile_picture_url' => null,
                ];
            }
        }

        $envIg = preg_replace('/\D+/', '', (string) config('services.meta.instagram_user_id', '')) ?: '';
        if ($envIg !== '' && ! isset($byId[$envIg])) {
            $detail = $this->getAccount($envIg);
            $byId[$envIg] = $detail ?? [
                'id' => $envIg,
                'username' => null,
                'name' => null,
                'source' => 'env',
                'profile_picture_url' => null,
            ];
        }

        return array_values($byId);
    }

    /**
     * Persist discovered IG ids onto the platform connection for Ad Studio.
     *
     * @return array{accounts: array<int, array<string, mixed>>, linked_count: int}
     */
    public function syncToConnection(?PlatformMetaConnection $connection = null): array
    {
        $connection ??= $this->connection();
        $accounts = $this->listAccounts();

        if (! $connection) {
            return ['accounts' => $accounts, 'linked_count' => 0];
        }

        $linked = [];
        foreach ($accounts as $row) {
            $id = preg_replace('/\D+/', '', (string) ($row['id'] ?? '')) ?: '';
            if ($id !== '' && ! in_array($id, $linked, true)) {
                $linked[] = $id;
            }
        }

        // Prefer Ads Instagram user id (1784…) over BM asset ids when choosing default
        $preferred = preg_replace('/\D+/', '', (string) (
            config('services.meta.instagram_user_id')
            ?: config('platform.meta.instagram_user_id')
            ?: ''
        )) ?: '';

        $default = preg_replace('/\D+/', '', (string) ($connection->instagram_business_account_id ?? '')) ?: '';
        // Upgrade stale asset-id defaults to resolved ig_user_id when present in sync
        if ($default !== '') {
            foreach ($accounts as $row) {
                if (($row['asset_id'] ?? null) === $default && ! empty($row['id'])) {
                    $default = (string) $row['id'];
                    break;
                }
            }
        }
        if ($preferred !== '' && collect($accounts)->contains(fn ($r) => (string) ($r['id'] ?? '') === $preferred)) {
            $default = $preferred;
        }
        if ($default === '' && $linked !== []) {
            $default = $linked[0];
        } elseif ($default !== '' && ! in_array($default, $linked, true)) {
            $linked[] = $default;
        }

        $connection->forceFill([
            'linked_instagram_ids' => array_values($linked),
            'instagram_business_account_id' => $default !== '' ? $default : $connection->instagram_business_account_id,
        ])->saveQuietly();

        return [
            'accounts' => $accounts,
            'linked_count' => count($linked),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function linkedInstagramIds(?PlatformMetaConnection $connection = null): array
    {
        $connection ??= $this->connection();
        $ids = $connection?->linked_instagram_ids ?? [];
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($id) => preg_replace('/\D+/', '', (string) $id) ?: '',
            $ids
        ))));
    }

    /**
     * Link an Instagram account already in Meta BM (mirrors “Link a WhatsApp Business account”).
     *
     * @return array{account: array<string, mixed>, message: string}
     */
    public function importExistingAccount(string $instagramId): array
    {
        $instagramId = preg_replace('/\D+/', '', trim($instagramId)) ?: '';
        if ($instagramId === '' || strlen($instagramId) < 5) {
            throw ValidationException::withMessages([
                'instagram_id' => 'Enter a valid Instagram account ID from Meta Business Manager → Instagram accounts.',
            ]);
        }

        $connection = $this->connection();
        if (! $connection) {
            throw ValidationException::withMessages([
                'connection' => 'No Meta connection. Sync from .env or Connect Meta first.',
            ]);
        }

        $detail = $this->getAccount($instagramId);
        if (! $detail) {
            // Still allow link if the ID appears in BM list (token may not GET node directly)
            $listed = collect($this->listAccounts())->firstWhere('id', $instagramId);
            $detail = $listed ? [
                'id' => $instagramId,
                'username' => $listed['username'] ?? null,
                'name' => $listed['name'] ?? null,
                'source' => $listed['source'] ?? 'linked',
                'profile_picture_url' => $listed['profile_picture_url'] ?? null,
            ] : null;
        }

        if (! $detail) {
            throw ValidationException::withMessages([
                'instagram_id' => 'This Instagram account is not accessible with the current Meta token. In Meta Business Manager, open Accounts → Instagram accounts, confirm the ID, and assign your system user access (Ads).',
            ]);
        }

        $linked = $this->linkedInstagramIds($connection);
        if (! in_array($instagramId, $linked, true)) {
            $linked[] = $instagramId;
        }

        $connection->forceFill([
            'linked_instagram_ids' => array_values($linked),
            'instagram_business_account_id' => $connection->instagram_business_account_id ?: $instagramId,
        ])->saveQuietly();

        $label = $detail['username'] ? '@'.$detail['username'] : $instagramId;

        return [
            'account' => $detail,
            'message' => "Instagram account {$label} linked for Ad Studio.",
        ];
    }

    public function setAsPlatformDefault(string $instagramId): void
    {
        $instagramId = preg_replace('/\D+/', '', trim($instagramId)) ?: '';
        if ($instagramId === '') {
            throw ValidationException::withMessages([
                'instagram_id' => 'Select an Instagram account.',
            ]);
        }

        $connection = $this->connection();
        if (! $connection) {
            throw ValidationException::withMessages([
                'connection' => 'No Meta connection.',
            ]);
        }

        $linked = $this->linkedInstagramIds($connection);
        if (! in_array($instagramId, $linked, true)) {
            $linked[] = $instagramId;
        }

        $connection->forceFill([
            'instagram_business_account_id' => $instagramId,
            'linked_instagram_ids' => array_values($linked),
        ])->save();
    }

    /**
     * @return array{id:string,username:?string,name:?string,source:string,profile_picture_url:?string}|null
     */
    public function getAccount(string $instagramId): ?array
    {
        $instagramId = preg_replace('/\D+/', '', $instagramId) ?: '';
        if ($instagramId === '') {
            return null;
        }

        $token = $this->requireToken();

        // Instagram Business Asset → resolves to ig_user_id used by Ads
        $assetMeta = Http::timeout(30)->get(
            "{$this->graphUrl}/{$this->graphVersion}/{$instagramId}",
            [
                'access_token' => $token,
                'metadata' => 1,
            ]
        );
        if ($assetMeta->ok()) {
            $json = $assetMeta->json();
            $igUserId = preg_replace('/\D+/', '', (string) ($json['ig_user_id'] ?? '')) ?: '';
            $username = $json['ig_username'] ?? $json['username'] ?? null;
            if ($igUserId !== '' || $username) {
                return [
                    'id' => $igUserId !== '' ? $igUserId : $instagramId,
                    'username' => $username,
                    'name' => $json['name'] ?? null,
                    'source' => 'asset_metadata',
                    'profile_picture_url' => $json['profile_picture_url'] ?? $json['profile_pic'] ?? null,
                    'asset_id' => $igUserId !== '' && $igUserId !== $instagramId ? $instagramId : null,
                ];
            }
        }

        $response = Http::timeout(30)->get(
            "{$this->graphUrl}/{$this->graphVersion}/{$instagramId}",
            [
                'access_token' => $token,
                'fields' => 'id,username,name,profile_picture_url',
            ]
        );

        if (! $response->ok()) {
            $alt = Http::timeout(30)->get(
                "{$this->graphUrl}/{$this->graphVersion}/{$instagramId}",
                [
                    'access_token' => $token,
                    'fields' => 'id,username,profile_pic',
                ]
            );
            if (! $alt->ok()) {
                Log::info('IG_GET_ACCOUNT_FAILED', [
                    'id' => $instagramId,
                    'error' => data_get($response->json(), 'error.message'),
                ]);

                return null;
            }
            $json = $alt->json();
        } else {
            $json = $response->json();
        }

        if (! is_array($json) || empty($json['id'])) {
            return null;
        }

        return [
            'id' => (string) $json['id'],
            'username' => $json['username'] ?? null,
            'name' => $json['name'] ?? null,
            'source' => 'graph',
            'profile_picture_url' => $json['profile_picture_url'] ?? $json['profile_pic'] ?? null,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $byId
     * @param  array<string, mixed>  $row
     */
    protected function mergeAccountRow(array &$byId, mixed $row, string $source, ?string $token = null): void
    {
        if (! is_array($row)) {
            return;
        }

        // Some assets nest IG user under ig_user / instagram_user
        $nested = $row['ig_user'] ?? $row['instagram_user'] ?? $row['instagram_business_account'] ?? null;
        if (is_array($nested) && ! empty($nested['id'])) {
            $row = array_merge($row, $nested);
        }

        $assetId = preg_replace('/\D+/', '', (string) ($row['id'] ?? '')) ?: '';
        $username = $row['username'] ?? $row['ig_username'] ?? null;
        $igUserId = preg_replace('/\D+/', '', (string) (
            $row['ig_user_id']
            ?? $row['legacy_instagram_user_id']
            ?? $row['ig_id']
            ?? ''
        )) ?: '';

        // Instagram Business Asset nodes often only return {id} on list edges —
        // resolve ig_user_id + username via GET /{asset-id}?metadata=1
        if (
            $assetId !== ''
            && ($igUserId === '' || $username === null)
            && str_contains($source, 'instagram_asset')
            && $token
        ) {
            $enriched = $this->enrichInstagramAsset($assetId, $token);
            if ($enriched) {
                $igUserId = $igUserId !== '' ? $igUserId : ($enriched['ig_user_id'] ?? '');
                $username = $username ?? ($enriched['ig_username'] ?? $enriched['username'] ?? null);
            }
        }

        // Prefer the Instagram user id used by Ads / Ad Studio (1784…), not the BM asset id
        $id = $igUserId !== '' ? $igUserId : $assetId;
        if ($id === '') {
            return;
        }

        if (! isset($byId[$id])) {
            $byId[$id] = [
                'id' => $id,
                'username' => $username,
                'name' => $row['name'] ?? null,
                'source' => $source,
                'profile_picture_url' => $row['profile_picture_url'] ?? $row['profile_pic'] ?? null,
                'asset_id' => ($igUserId !== '' && $assetId !== '' && $assetId !== $igUserId) ? $assetId : null,
            ];
        } elseif ($username && empty($byId[$id]['username'])) {
            $byId[$id]['username'] = $username;
        }
    }

    /**
     * @return array{ig_user_id?:string,ig_username?:string,username?:string}|null
     */
    protected function enrichInstagramAsset(string $assetId, string $token): ?array
    {
        try {
            $response = Http::timeout(30)->get(
                "{$this->graphUrl}/{$this->graphVersion}/{$assetId}",
                [
                    'access_token' => $token,
                    'metadata' => 1,
                ]
            );
            if (! $response->ok()) {
                return null;
            }
            $json = $response->json();
            if (! is_array($json)) {
                return null;
            }

            return [
                'ig_user_id' => preg_replace('/\D+/', '', (string) ($json['ig_user_id'] ?? '')) ?: null,
                'ig_username' => $json['ig_username'] ?? null,
                'username' => $json['username'] ?? $json['ig_username'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::info('IG_ASSET_ENRICH_FAILED', ['id' => $assetId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @return \Generator<int, array<string, mixed>>
     */
    protected function paginateEdge(string $url, array $query): \Generator
    {
        $next = $url;
        $params = $query;
        $guard = 0;

        while ($next && $guard < 10) {
            $guard++;
            try {
                $response = Http::timeout(45)->get($next, $params);
            } catch (\Throwable $e) {
                Log::warning('IG_EDGE_HTTP_FAILED', ['url' => $url, 'error' => $e->getMessage()]);
                break;
            }

            if (! $response->ok()) {
                Log::info('IG_EDGE_NOT_OK', [
                    'url' => $url,
                    'status' => $response->status(),
                    'error' => data_get($response->json(), 'error.message'),
                ]);
                break;
            }

            foreach ($response->json('data', []) as $row) {
                if (is_array($row)) {
                    yield $row;
                }
            }

            $next = data_get($response->json(), 'paging.next');
            $params = []; // next URL already has query string
        }
    }

    protected function requireToken(): string
    {
        $token = $this->connection()?->plainAccessToken()
            ?: config('platform.meta.system_user_token')
            ?: config('platform.whatsapp.access_token')
            ?: config('services.meta.token');

        if (! $token) {
            throw ValidationException::withMessages([
                'connection' => 'Platform Meta token is not configured. Connect Meta or sync from .env.',
            ]);
        }

        return $token;
    }
}
