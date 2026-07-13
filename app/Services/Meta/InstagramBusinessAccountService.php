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
        // Keep Graph version aligned with MetaAdsService (ads + pages lookups)
        $this->graphVersion = config('services.meta.graph_version', config('platform.meta.graph_version', 'v22.0'));
        $this->graphUrl = rtrim(config('services.meta.graph_url', config('platform.meta.graph_url', 'https://graph.facebook.com')), '/');
    }

    public function connection(): ?PlatformMetaConnection
    {
        return app(TenantConnectionResolver::class)->forCurrentUser()
            ?? PlatformMetaConnection::query()->platformDefault()->active()->first();
    }

    /**
     * Discover Instagram accounts for ads (Marketing API / Business Asset Management).
     *
     * @see https://developers.facebook.com/docs/marketing-api/business-asset-management/guides/instagram-accounts/
     * @see https://developers.facebook.com/documentation/ads-commerce/marketing-api
     *
     * @return array<int, array{id:string,username:?string,name:?string,source:string,profile_picture_url:?string}>
     */
    public function listAccounts(?string $businessId = null): array
    {
        $token = $this->requireToken();
        $connection = $this->connection();
        $businessId = $businessId ?: $this->whatsapp->resolveBusinessManagerId($connection);
        $byId = [];

        // 1) Business-owned Instagram assets (Meta BM docs — primary)
        if ($businessId) {
            foreach (['owned_instagram_assets', 'client_instagram_assets'] as $edge) {
                foreach ($this->paginateEdge(
                    "{$this->graphUrl}/{$this->graphVersion}/{$businessId}/{$edge}",
                    [
                        'access_token' => $token,
                        'metadata' => 1,
                        'fields' => 'id,ig_user_id,ig_username,username,name,profile_picture_url',
                        'limit' => 50,
                    ]
                ) as $row) {
                    $this->mergeAccountRow($byId, $row, $edge, $token);
                }
            }

            // Legacy account edges (still used by some BM setups)
            foreach (['owned_instagram_accounts', 'instagram_accounts', 'client_instagram_accounts'] as $edge) {
                foreach ($this->paginateEdge(
                    "{$this->graphUrl}/{$this->graphVersion}/{$businessId}/{$edge}",
                    [
                        'access_token' => $token,
                        'fields' => 'id,username,name,profile_picture_url',
                        'limit' => 50,
                    ]
                ) as $row) {
                    $this->mergeAccountRow($byId, $row, $edge, $token);
                }
            }
        }

        // 2) Ad account Instagram accounts (Marketing API — required for ads)
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

        // 3) Page → Instagram business / connected account (most reliable for @username)
        $pageId = (string) (
            config('services.meta.page_id')
            ?: config('platform.meta.page_id')
            ?: $connection?->page_id
            ?: ''
        );
        $pageIg = $this->fetchPageInstagram($pageId, $token);
        if ($pageIg) {
            $id = (string) $pageIg['id'];
            $byId[$id] = isset($byId[$id])
                ? $this->preferRicherRow($byId[$id], $pageIg)
                : $pageIg;
        }

        try {
            foreach ($this->meta->listPagesWithInstagram() as $page) {
                if (empty($page['instagram_id'])) {
                    continue;
                }
                $id = (string) $page['instagram_id'];
                $row = [
                    'id' => $id,
                    'username' => ! empty($page['instagram_username'])
                        ? ltrim((string) $page['instagram_username'], '@')
                        : null,
                    'name' => $page['name'] ?? null,
                    'source' => 'page',
                    'profile_picture_url' => null,
                    'page_id' => $page['id'] ?? null,
                ];
                $byId[$id] = isset($byId[$id]) ? $this->preferRicherRow($byId[$id], $row) : $row;
            }
        } catch (\Throwable $e) {
            Log::warning('IG_LIST_PAGES_FAILED', ['error' => $e->getMessage()]);
        }

        // 4) Guaranteed seeds (.env + connection) — never allow a total empty directory
        foreach ($this->knownInstagramIds($connection) as $seedId) {
            if (isset($byId[$seedId])) {
                continue;
            }
            $already = collect($byId)->contains(
                fn ($row) => ($row['asset_id'] ?? '') === $seedId
            );
            if ($already) {
                continue;
            }
            $detail = $this->getAccount($seedId);
            $byId[$detail['id'] ?? $seedId] = $detail ?? [
                'id' => $seedId,
                'username' => $this->configuredUsernameFor($seedId),
                'name' => null,
                'source' => 'seed',
                'profile_picture_url' => null,
            ];
        }

        $byId = $this->finalizeAccounts($byId, $token);
        $byId = $this->applyConfiguredUsernameFallbacks($byId);

        // Last resort: if Graph returned nothing usable, still show env/connection IG
        if ($byId === []) {
            foreach ($this->knownInstagramIds($connection) as $seedId) {
                $byId[$seedId] = [
                    'id' => $seedId,
                    'username' => $this->configuredUsernameFor($seedId),
                    'name' => null,
                    'source' => 'seed',
                    'profile_picture_url' => null,
                ];
            }
            if ($pageIg) {
                $byId[(string) $pageIg['id']] = $pageIg;
            }
        }

        foreach ($byId as $id => $row) {
            $byId[$id] = $this->sanitizeAccountLabels(is_array($row) ? $row : []);
        }

        Log::info('IG_LIST_ACCOUNTS_RESULT', [
            'count' => count($byId),
            'ids' => array_keys($byId),
            'usernames' => collect($byId)->pluck('username')->filter()->values()->all(),
        ]);

        return array_values($byId);
    }

    /**
     * @return array<int, string>
     */
    protected function knownInstagramIds(?PlatformMetaConnection $connection): array
    {
        $ids = [
            config('services.meta.instagram_user_id'),
            config('platform.meta.instagram_user_id'),
            $connection?->instagram_business_account_id,
        ];
        foreach ($this->linkedInstagramIds($connection) as $id) {
            $ids[] = $id;
        }

        $out = [];
        foreach ($ids as $id) {
            $id = preg_replace('/\D+/', '', (string) $id) ?: '';
            if ($id !== '' && ! in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * GET /{page-id}?fields=instagram_business_account{id,username},connected_instagram_account{id,username}
     *
     * @return array{id:string,username:?string,name:?string,source:string,profile_picture_url:?string,page_id:?string}|null
     */
    protected function fetchPageInstagram(string $pageId, string $token): ?array
    {
        $pageId = preg_replace('/\D+/', '', $pageId) ?: '';
        if ($pageId === '') {
            return null;
        }

        try {
            $response = Http::timeout(30)->get(
                "{$this->graphUrl}/{$this->graphVersion}/{$pageId}",
                [
                    'access_token' => $token,
                    'fields' => 'id,name,instagram_business_account{id,username},connected_instagram_account{id,username}',
                ]
            );
            if (! $response->ok()) {
                Log::info('IG_PAGE_LOOKUP_FAILED', [
                    'page_id' => $pageId,
                    'error' => data_get($response->json(), 'error.message'),
                ]);

                return null;
            }

            $json = $response->json();
            $ig = $json['instagram_business_account'] ?? $json['connected_instagram_account'] ?? null;
            if (! is_array($ig) || empty($ig['id'])) {
                return null;
            }

            return [
                'id' => (string) $ig['id'],
                'username' => ! empty($ig['username']) ? ltrim((string) $ig['username'], '@') : null,
                'name' => $json['name'] ?? null,
                'source' => 'page',
                'profile_picture_url' => null,
                'page_id' => (string) ($json['id'] ?? $pageId),
            ];
        } catch (\Throwable $e) {
            Log::warning('IG_PAGE_LOOKUP_EXCEPTION', ['page_id' => $pageId, 'error' => $e->getMessage()]);

            return null;
        }
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

        // Never wipe previously linked accounts if Graph temporarily returns nothing
        if ($accounts === []) {
            Log::warning('IG_SYNC_EMPTY_PRESERVING_LINKED', [
                'connection_id' => $connection->id,
                'linked' => $connection->linked_instagram_ids,
            ]);

            return [
                'accounts' => $this->seedDirectoryFromConnection($connection),
                'linked_count' => count($this->linkedInstagramIds($connection)),
            ];
        }

        $linked = [];
        foreach ($accounts as $row) {
            $id = preg_replace('/\D+/', '', (string) ($row['id'] ?? '')) ?: '';
            if ($id !== '' && ! in_array($id, $linked, true)) {
                $linked[] = $id;
            }
        }

        $preferred = preg_replace('/\D+/', '', (string) (
            config('services.meta.instagram_user_id')
            ?: config('platform.meta.instagram_user_id')
            ?: ''
        )) ?: '';

        $default = preg_replace('/\D+/', '', (string) ($connection->instagram_business_account_id ?? '')) ?: '';
        if ($default !== '') {
            foreach ($accounts as $row) {
                if (($row['asset_id'] ?? null) === $default && ! empty($row['id'])) {
                    $default = (string) $row['id'];
                    break;
                }
            }
            if ($default !== '' && ! collect($accounts)->contains(fn ($r) => (string) ($r['id'] ?? '') === $default)) {
                $default = $preferred !== '' ? $preferred : ($linked[0] ?? '');
            }
        }
        if ($preferred !== '' && in_array($preferred, $linked, true)) {
            $default = $preferred;
        }
        if ($default === '' && $linked !== []) {
            $default = $linked[0];
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
     * @return array<int, array<string, mixed>>
     */
    protected function seedDirectoryFromConnection(?PlatformMetaConnection $connection): array
    {
        $items = [];
        foreach ($this->knownInstagramIds($connection) as $id) {
            $items[] = [
                'id' => $id,
                'username' => $this->configuredUsernameFor($id),
                'name' => null,
                'source' => 'seed',
                'profile_picture_url' => null,
            ];
        }

        return array_map(fn ($row) => $this->sanitizeAccountLabels($row), $items);
    }

    /**
     * Optional .env fallback when Graph omits username (system-user tokens often do).
     */
    protected function configuredUsernameFor(?string $instagramId = null): ?string
    {
        $configured = config('services.meta.instagram_username')
            ?: config('platform.meta.instagram_username')
            ?: null;
        if (! $configured) {
            return null;
        }
        $configured = ltrim((string) $configured, '@');
        if ($configured === '') {
            return null;
        }

        $expectedId = preg_replace('/\D+/', '', (string) (
            config('services.meta.instagram_user_id')
            ?: config('platform.meta.instagram_user_id')
            ?: ''
        )) ?: '';

        if ($instagramId && $expectedId !== '' && $instagramId !== $expectedId) {
            return null;
        }

        return $configured;
    }

    /**
     * @param  array<string, array<string, mixed>>  $byId
     * @return array<string, array<string, mixed>>
     */
    protected function applyConfiguredUsernameFallbacks(array $byId): array
    {
        foreach ($byId as $id => $row) {
            if (! empty($row['username'])) {
                continue;
            }
            $fallback = $this->configuredUsernameFor((string) $id);
            if ($fallback) {
                $byId[$id]['username'] = $fallback;
                $byId[$id]['source'] = ($row['source'] ?? 'seed').'+env';
            }
        }

        return $byId;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function sanitizeAccountLabels(array $row): array
    {
        $banned = [
            'facebook page',
            'platform page',
            'your page',
            strtolower((string) (config('services.meta.page_name') ?: '')),
            strtolower((string) (config('platform.meta.page_name') ?: '')),
        ];
        $banned = array_values(array_filter(array_unique($banned)));

        $name = trim((string) ($row['name'] ?? ''));
        if ($name !== '' && in_array(strtolower($name), $banned, true)) {
            $row['name'] = null;
        }

        if (! empty($row['username'])) {
            $row['username'] = ltrim((string) $row['username'], '@');
        } else {
            $fallback = $this->configuredUsernameFor((string) ($row['id'] ?? ''));
            if ($fallback) {
                $row['username'] = $fallback;
            }
        }

        // Prefer IG handle as the human label Meta shows in Business Manager
        $row['label'] = ! empty($row['username'])
            ? '@'.$row['username']
            : ('IG '.$row['id']);

        return $row;
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
                $resolvedId = (string) ($igUserId !== '' ? $igUserId : $instagramId);
                // Metadata often returns ig_user_id without username — fetch it from the IG user node
                if (! $username && $resolvedId !== '') {
                    $userRes = Http::timeout(30)->get(
                        "{$this->graphUrl}/{$this->graphVersion}/{$resolvedId}",
                        [
                            'access_token' => $token,
                            'fields' => 'id,username,name,profile_picture_url',
                        ]
                    );
                    if ($userRes->ok()) {
                        $userJson = $userRes->json();
                        $username = $userJson['username'] ?? $userJson['ig_username'] ?? null;
                        $json['name'] = $json['name'] ?? ($userJson['name'] ?? null);
                        $json['profile_picture_url'] = $json['profile_picture_url']
                            ?? ($userJson['profile_picture_url'] ?? $userJson['profile_pic'] ?? null);
                    }
                }
                if (! $username) {
                    try {
                        foreach ($this->meta->listPagesWithInstagram() as $page) {
                            if ((string) ($page['instagram_id'] ?? '') === $resolvedId && ! empty($page['instagram_username'])) {
                                $username = $page['instagram_username'];
                                break;
                            }
                        }
                    } catch (\Throwable) {
                        // ignore
                    }
                }

                return $this->sanitizeAccountLabels([
                    'id' => $resolvedId,
                    'username' => $username ? ltrim((string) $username, '@') : null,
                    'name' => $json['name'] ?? null,
                    'source' => 'asset_metadata',
                    'profile_picture_url' => $json['profile_picture_url'] ?? $json['profile_pic'] ?? null,
                    'asset_id' => $igUserId !== '' && $igUserId !== $instagramId ? $instagramId : null,
                ]);
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

        $username = $json['username'] ?? $json['ig_username'] ?? null;

        return $this->sanitizeAccountLabels([
            'id' => (string) $json['id'],
            'username' => $username ? ltrim((string) $username, '@') : null,
            'name' => $json['name'] ?? null,
            'source' => 'graph',
            'profile_picture_url' => $json['profile_picture_url'] ?? $json['profile_pic'] ?? null,
        ]);
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

        // Prefer the Instagram user id used by Ads / Ad Studio (1784…), not the BM asset id
        $id = $igUserId !== '' ? $igUserId : $assetId;
        if ($id === '') {
            return;
        }

        // Resolve username when missing (list edges often return id only)
        if (
            $assetId !== ''
            && ($igUserId === '' || $username === null || $username === '')
            && $token
        ) {
            $enriched = $this->enrichInstagramAsset($assetId, $token);
            if ($enriched) {
                $igUserId = $igUserId !== '' ? $igUserId : (string) ($enriched['ig_user_id'] ?? '');
                $username = $username ?: ($enriched['ig_username'] ?? $enriched['username'] ?? null);
                if ($igUserId !== '') {
                    $id = $igUserId;
                }
            }
        }

        if (! isset($byId[$id])) {
            $byId[$id] = [
                'id' => $id,
                'username' => $username ? ltrim((string) $username, '@') : null,
                'name' => $row['name'] ?? null,
                'source' => $source,
                'profile_picture_url' => $row['profile_picture_url'] ?? $row['profile_pic'] ?? null,
                'asset_id' => ($igUserId !== '' && $assetId !== '' && $assetId !== $igUserId) ? $assetId : null,
            ];
        } else {
            if ($username && empty($byId[$id]['username'])) {
                $byId[$id]['username'] = ltrim((string) $username, '@');
            }
            if (! empty($row['name']) && empty($byId[$id]['name'])) {
                $byId[$id]['name'] = $row['name'];
            }
            if (($igUserId !== '' && $assetId !== '' && $assetId !== $igUserId) && empty($byId[$id]['asset_id'])) {
                $byId[$id]['asset_id'] = $assetId;
            }
        }
    }

    /**
     * Hydrate usernames, collapse BM asset IDs into IG user IDs, drop duplicates.
     *
     * @param  array<string, array<string, mixed>>  $byId
     * @return array<string, array<string, mixed>>
     */
    protected function finalizeAccounts(array $byId, string $token): array
    {
        $byId = $this->hydrateMissingUsernames($byId, $token);

        // Collapse any row that is only a BM asset pointing at another IG user row
        $assetOwners = [];
        foreach ($byId as $id => $row) {
            $assetId = preg_replace('/\D+/', '', (string) ($row['asset_id'] ?? '')) ?: '';
            if ($assetId !== '' && $assetId !== $id) {
                $assetOwners[$assetId] = (string) $id;
            }
        }
        foreach (array_keys($byId) as $id) {
            if (isset($assetOwners[$id]) && $assetOwners[$id] !== $id) {
                $ownerId = $assetOwners[$id];
                if (isset($byId[$ownerId])) {
                    $byId[$ownerId] = $this->preferRicherRow($byId[$ownerId], array_merge($byId[$id], [
                        'id' => $ownerId,
                        'asset_id' => $id,
                    ]));
                }
                unset($byId[$id]);
            }
        }

        // Resolve leftover non-IG-user ids (BM assets like 629…) into 1784… user ids
        foreach (array_keys($byId) as $id) {
            if (! isset($byId[$id])) {
                continue;
            }
            if ($this->looksLikeIgUserId($id) && ! empty($byId[$id]['username'])) {
                continue;
            }
            $detail = $this->getAccount((string) $id);
            if (! $detail || empty($detail['id'])) {
                // Drop nameless unresolved asset clones when we already have a real IG user
                if (! $this->looksLikeIgUserId($id) && $this->hasNamedIgUser($byId)) {
                    unset($byId[$id]);
                }
                continue;
            }
            $resolvedId = (string) $detail['id'];
            if ($resolvedId === $id) {
                $byId[$id] = $this->preferRicherRow($byId[$id], $detail);
                continue;
            }
            $merged = $this->preferRicherRow($byId[$id], $detail);
            $merged['id'] = $resolvedId;
            $merged['asset_id'] = $merged['asset_id'] ?? $id;
            unset($byId[$id]);
            $byId[$resolvedId] = isset($byId[$resolvedId])
                ? $this->preferRicherRow($byId[$resolvedId], $merged)
                : $merged;
        }

        // Page-connected Instagram is the most reliable username source
        try {
            foreach ($this->meta->listPagesWithInstagram() as $page) {
                $igId = preg_replace('/\D+/', '', (string) ($page['instagram_id'] ?? '')) ?: '';
                $username = $page['instagram_username'] ?? null;
                if ($igId === '') {
                    continue;
                }
                if (! isset($byId[$igId])) {
                    $byId[$igId] = [
                        'id' => $igId,
                        'username' => $username ? ltrim((string) $username, '@') : null,
                        'name' => $page['name'] ?? null,
                        'source' => 'page',
                        'profile_picture_url' => null,
                        'page_id' => $page['id'] ?? null,
                    ];
                } elseif ($username && empty($byId[$igId]['username'])) {
                    $byId[$igId]['username'] = ltrim((string) $username, '@');
                    $byId[$igId]['page_id'] = $page['id'] ?? $byId[$igId]['page_id'] ?? null;
                    if (empty($byId[$igId]['name']) && ! empty($page['name'])) {
                        $byId[$igId]['name'] = $page['name'];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('IG_FINALIZE_PAGES_FAILED', ['error' => $e->getMessage()]);
        }

        // Final cleanup: drop unresolved asset rows when a named IG user exists
        if ($this->hasNamedIgUser($byId)) {
            foreach (array_keys($byId) as $id) {
                if (! $this->looksLikeIgUserId($id) && empty($byId[$id]['username'])) {
                    unset($byId[$id]);
                }
            }
        }

        // Normalize username display
        foreach ($byId as $id => $row) {
            if (! empty($row['username'])) {
                $byId[$id]['username'] = ltrim((string) $row['username'], '@');
            }
        }

        return $byId;
    }

    /**
     * @param  array<string, array<string, mixed>>  $byId
     */
    protected function hasNamedIgUser(array $byId): bool
    {
        foreach ($byId as $id => $row) {
            if ($this->looksLikeIgUserId((string) $id) && ! empty($row['username'])) {
                return true;
            }
        }

        return false;
    }

    protected function looksLikeIgUserId(string $id): bool
    {
        // Ads / Instagram Graph user ids are typically 17+ digits starting with 1784
        return str_starts_with($id, '1784') || strlen($id) >= 16;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    protected function preferRicherRow(array $a, array $b): array
    {
        $out = $a;
        foreach (['username', 'name', 'profile_picture_url', 'page_id', 'asset_id', 'source'] as $key) {
            if (empty($out[$key]) && ! empty($b[$key])) {
                $out[$key] = $b[$key];
            }
        }
        if (! empty($b['username']) && empty($a['username'])) {
            $out['username'] = ltrim((string) $b['username'], '@');
        }

        return $out;
    }

    /**
     * Fill @username / name for any rows that still only have numeric IDs.
     *
     * @param  array<string, array<string, mixed>>  $byId
     * @return array<string, array<string, mixed>>
     */
    protected function hydrateMissingUsernames(array $byId, string $token): array
    {
        foreach (array_keys($byId) as $id) {
            $row = $byId[$id] ?? null;
            if (! is_array($row)) {
                continue;
            }
            if (! empty($row['username'])) {
                $byId[$id]['username'] = ltrim((string) $row['username'], '@');
                continue;
            }

            $lookupId = (string) ($row['asset_id'] ?? $id);
            $detail = $this->getAccount($lookupId);
            if (! $detail && $lookupId !== $id) {
                $detail = $this->getAccount((string) $id);
            }
            if (! $detail) {
                continue;
            }

            $resolvedId = (string) ($detail['id'] ?? $id);
            $username = $detail['username'] ?? null;
            if ($username) {
                $username = ltrim((string) $username, '@');
            }

            $merged = array_merge($row, [
                'id' => $resolvedId,
                'username' => $username ?: ($row['username'] ?? null),
                'name' => $detail['name'] ?? ($row['name'] ?? null),
                'profile_picture_url' => $detail['profile_picture_url'] ?? ($row['profile_picture_url'] ?? null),
                'source' => ! empty($detail['username']) ? ($detail['source'] ?? $row['source'] ?? 'graph') : ($row['source'] ?? 'graph'),
            ]);
            if (! empty($detail['asset_id'])) {
                $merged['asset_id'] = $detail['asset_id'];
            } elseif ($resolvedId !== $id) {
                $merged['asset_id'] = $id;
            }

            if ($resolvedId !== $id) {
                unset($byId[$id]);
            }
            $byId[$resolvedId] = isset($byId[$resolvedId])
                ? $this->preferRicherRow($byId[$resolvedId], $merged)
                : $merged;
        }

        return $byId;
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
                // Fall back to standard IG user fields
                $response = Http::timeout(30)->get(
                    "{$this->graphUrl}/{$this->graphVersion}/{$assetId}",
                    [
                        'access_token' => $token,
                        'fields' => 'id,username,name,profile_picture_url',
                    ]
                );
            }
            if (! $response->ok()) {
                return null;
            }
            $json = $response->json();
            if (! is_array($json)) {
                return null;
            }

            $username = $json['ig_username'] ?? $json['username'] ?? null;

            return [
                'ig_user_id' => preg_replace('/\D+/', '', (string) ($json['ig_user_id'] ?? ''))
                    ?: (preg_replace('/\D+/', '', (string) ($json['id'] ?? '')) ?: null),
                'ig_username' => $username,
                'username' => $username,
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
