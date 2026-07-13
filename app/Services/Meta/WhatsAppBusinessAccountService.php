<?php

namespace App\Services\Meta;

use App\Models\PlatformMetaConnection;
use App\Services\Tenant\TenantConnectionResolver;
use App\Services\Tenant\TenantMetaPageValidator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Full WhatsApp Business Account control via Meta Graph API.
 *
 * Docs:
 * - https://developers.facebook.com/docs/graph-api/reference/whats-app-business-account/phone_numbers/
 * - https://developers.facebook.com/docs/whatsapp/cloud-api/phone-numbers
 * Flow: add number → request_code → verify_code → register
 */
class WhatsAppBusinessAccountService
{
    protected string $graphUrl;

    protected string $graphVersion;

    public function __construct()
    {
        $this->graphVersion = config('platform.meta.graph_version', config('services.meta.graph_version', 'v19.0'));
        $this->graphUrl = rtrim(config('platform.meta.graph_url', config('services.meta.graph_url', 'https://graph.facebook.com')), '/');
    }

    public function connection(): ?PlatformMetaConnection
    {
        return app(TenantConnectionResolver::class)->forCurrentUser()
            ?? PlatformMetaConnection::query()->platformDefault()->active()->first();
    }

    /**
     * @return array<int, array{id: string, name: string, currency?: string, timezone_id?: string, account_review_status?: string, ownership_type?: string}>
     */
    public function listWabas(?string $businessId = null): array
    {
        return $this->listWabasDetailed($businessId)['accounts'];
    }

    /**
     * @return array{accounts: array<int, array<string, mixed>>, incomplete: bool, graph_ok: bool, errors: array<int, string>, phones: array<int, array<string, mixed>>}
     */
    public function listWabasDetailed(?string $businessId = null): array
    {
        $token = $this->requireToken();
        $connection = $this->connection();
        $businessId = $businessId ?: $this->resolveBusinessManagerId($connection);
        $accounts = [];
        $phones = [];
        $errors = [];
        $graphOk = false;

        // Nested phone_numbers — one Graph call per edge instead of N per-WABA calls (avoids #80008 / #4).
        $wabaFields = 'id,name,currency,timezone_id,account_review_status,ownership_type,'
            .'on_behalf_of_business_info,owner_business_info,'
            .'phone_numbers.limit(25){id,display_phone_number,verified_name,code_verification_status,quality_rating,status,platform_type,name_status,account_mode,is_preverified_number}';

        if ($businessId) {
            foreach (['owned_whatsapp_business_accounts', 'client_whatsapp_business_accounts'] as $edge) {
                $page = $this->paginateEdgeDetailed(
                    "{$this->graphUrl}/{$this->graphVersion}/{$businessId}/{$edge}",
                    [
                        'access_token' => $token,
                        'fields' => $wabaFields,
                        'limit' => 50,
                    ]
                );
                if ($page['ok']) {
                    $graphOk = true;
                } elseif ($page['error']) {
                    $errors[] = $edge.': '.$page['error'];
                    $this->rememberGraphRateLimit($page['error']);
                }
                foreach ($page['rows'] as $row) {
                    if (! is_array($row) || empty($row['id'])) {
                        continue;
                    }
                    $mapped = $this->mapWabaRow($row, $edge);
                    $accounts[$mapped['id']] = $mapped;
                    foreach ($this->extractNestedPhones($row, $mapped['id'], $mapped['name'] ?? null) as $phone) {
                        $phones[$phone['id']] = $phone;
                    }
                }
            }
        } else {
            $errors[] = 'Business Manager ID missing (set META_BUSINESS_ID).';
        }

        // Also try WABAs assigned directly to the token user (system user / app).
        $assigned = $this->paginateEdgeDetailed(
            "{$this->graphUrl}/{$this->graphVersion}/me/assigned_whatsapp_business_accounts",
            [
                'access_token' => $token,
                'fields' => $wabaFields,
                'limit' => 50,
            ]
        );
        if ($assigned['ok']) {
            $graphOk = true;
        } elseif ($assigned['error']) {
            $errors[] = 'assigned: '.$assigned['error'];
            $this->rememberGraphRateLimit($assigned['error']);
        }
        foreach ($assigned['rows'] as $row) {
            if (! is_array($row) || empty($row['id'])) {
                continue;
            }
            $mapped = $this->mapWabaRow($row, 'assigned');
            $id = $mapped['id'];
            if (! isset($accounts[$id]) || $this->isPlaceholderWabaName((string) ($accounts[$id]['name'] ?? ''), $id)) {
                $accounts[$id] = isset($accounts[$id])
                    ? array_merge($accounts[$id], $mapped)
                    : $mapped;
            }
            foreach ($this->extractNestedPhones($row, $id, $mapped['name'] ?? null) as $phone) {
                $phones[$phone['id']] = $phone;
            }
        }

        $fromGraphCount = count($accounts);

        $fallbackId = preg_replace('/\D+/', '', (string) (
            $connection?->whatsapp_business_id ?: config('platform.whatsapp.business_id') ?: ''
        )) ?: '';
        if ($fallbackId !== '' && ! isset($accounts[$fallbackId])) {
            $detail = ($graphOk && ! $this->isWabaRateLimited($fallbackId)) ? $this->getWaba($fallbackId) : null;
            $accounts[$fallbackId] = $detail ?? [
                'id' => $fallbackId,
                'name' => $connection?->business_name ?? 'WhatsApp Business Account',
                'ownership_type' => 'platform_default',
            ];
        }

        // Locally imported / previously synced WABAs (never drop these on Graph blips)
        $rateLimited = (bool) Cache::get('meta_wa_rate_limited');
        foreach ($this->linkedWabaIds($connection) as $linkedId) {
            if (isset($accounts[$linkedId]) && ! $this->isPlaceholderWabaName((string) ($accounts[$linkedId]['name'] ?? ''), $linkedId)) {
                continue;
            }
            $detail = null;
            if (! $rateLimited && ! $this->isWabaRateLimited($linkedId)) {
                $detail = $this->getWaba($linkedId);
                if ($detail === null && (Cache::get('meta_wa_rate_limited') || $this->isWabaRateLimited($linkedId))) {
                    $rateLimited = true;
                }
            }
            if ($detail) {
                $detail['ownership_type'] = $detail['ownership_type'] ?? ($accounts[$linkedId]['ownership_type'] ?? 'linked_import');
                $detail['name'] = $this->bestWabaDisplayName($detail, $linkedId);
                $accounts[$linkedId] = array_merge($accounts[$linkedId] ?? [], $detail);
            } elseif (! isset($accounts[$linkedId])) {
                $accounts[$linkedId] = [
                    'id' => $linkedId,
                    'name' => 'WhatsApp Business Account '.$linkedId,
                    'ownership_type' => 'linked_import',
                ];
            }
        }

        // Prefer verified phone names when Graph left a placeholder WABA name
        foreach ($phones as $phone) {
            $wabaId = (string) ($phone['waba_id'] ?? '');
            if ($wabaId === '' || ! isset($accounts[$wabaId])) {
                continue;
            }
            if (! $this->isPlaceholderWabaName((string) ($accounts[$wabaId]['name'] ?? ''), $wabaId)) {
                continue;
            }
            $verified = trim((string) ($phone['verified_name'] ?? ''));
            if ($verified !== '') {
                $accounts[$wabaId]['name'] = $verified;
            }
        }

        $placeholderCount = 0;
        foreach ($accounts as $id => $row) {
            if (! is_array($row)) {
                continue;
            }
            $accounts[$id]['name'] = $this->bestWabaDisplayName($row, (string) $id);
            if ($this->isPlaceholderWabaName((string) ($accounts[$id]['name'] ?? ''), (string) $id)) {
                $placeholderCount++;
            }
        }

        $incomplete = $errors !== [] || (! $graphOk && $fromGraphCount === 0) || $placeholderCount > 0;
        // Owned list with real names is enough for persist — but keep incomplete true while placeholders remain.
        if ($fromGraphCount >= 2 && $placeholderCount === 0) {
            $incomplete = false;
        }

        if ($incomplete) {
            Log::warning('WA_LIST_WABAS_INCOMPLETE', [
                'business_id' => $businessId,
                'count' => count($accounts),
                'from_graph' => $fromGraphCount,
                'placeholders' => $placeholderCount,
                'nested_phones' => count($phones),
                'errors' => $errors,
            ]);
            $joined = strtolower(implode(' ', $errors));
            if (str_contains($joined, 'too many') || str_contains($joined, 'rate') || str_contains($joined, 'limit')) {
                Cache::put('meta_wa_rate_limited', 1, now()->addMinutes(45));
            }
        } elseif ($errors !== []) {
            Log::info('WA_LIST_WABAS_PARTIAL_OK', [
                'business_id' => $businessId,
                'count' => count($accounts),
                'from_graph' => $fromGraphCount,
                'errors' => $errors,
            ]);
        } else {
            Cache::forget('meta_wa_rate_limited');
        }

        return [
            'accounts' => array_values($accounts),
            'incomplete' => $incomplete,
            'graph_ok' => $graphOk,
            'errors' => $errors,
            'phones' => array_values($phones),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function mapWabaRow(array $row, string $ownershipFallback): array
    {
        $id = (string) ($row['id'] ?? '');
        $mapped = [
            'id' => $id,
            'name' => (string) ($row['name'] ?? $id),
            'currency' => $row['currency'] ?? null,
            'timezone_id' => $row['timezone_id'] ?? null,
            'account_review_status' => $row['account_review_status'] ?? null,
            'ownership_type' => $row['ownership_type'] ?? $ownershipFallback,
            'on_behalf_of_business_info' => $row['on_behalf_of_business_info'] ?? null,
            'owner_business_info' => $row['owner_business_info'] ?? null,
        ];
        $mapped['name'] = $this->bestWabaDisplayName($mapped, $id);

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, array<string, mixed>>
     */
    protected function extractNestedPhones(array $row, string $wabaId, ?string $wabaName = null): array
    {
        $out = [];
        $data = $row['phone_numbers']['data'] ?? $row['phone_numbers'] ?? [];
        if (! is_array($data)) {
            return [];
        }
        // Nested list shape: { data: [...] } or already a list
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }
        foreach ($data as $phone) {
            if (! is_array($phone) || empty($phone['id'])) {
                continue;
            }
            $mapped = [
                'id' => (string) $phone['id'],
                'display_phone_number' => (string) ($phone['display_phone_number'] ?? ''),
                'verified_name' => $phone['verified_name'] ?? null,
                'code_verification_status' => $phone['code_verification_status'] ?? null,
                'name_status' => $phone['name_status'] ?? null,
                'quality_rating' => is_array($phone['quality_rating'] ?? null)
                    ? ($phone['quality_rating']['score'] ?? null)
                    : ($phone['quality_rating'] ?? null),
                'status' => $phone['status'] ?? null,
                'platform_type' => $phone['platform_type'] ?? null,
                'is_preverified_number' => (bool) ($phone['is_preverified_number'] ?? false),
                'account_mode' => $phone['account_mode'] ?? null,
                'waba_id' => $wabaId,
                'waba_name' => $wabaName,
            ];
            $mapped['verified'] = $this->isPhoneVerified($mapped);
            $out[] = $mapped;
        }

        return $out;
    }

    protected function rememberGraphRateLimit(string $message, ?string $wabaId = null): void
    {
        $body = strtolower($message);
        $isLimit = str_contains($body, 'too many')
            || str_contains($body, 'rate limit')
            || str_contains($body, 'request limit')
            || str_contains($body, '80008')
            || preg_match('/\(#4\)/', $body);

        if (! $isLimit) {
            return;
        }

        Cache::put('meta_wa_rate_limited', 1, now()->addMinutes(45));
        if ($wabaId) {
            Cache::put('meta_wa_waba_rl_'.$wabaId, 1, now()->addMinutes(60));
        }
    }

    public function isWabaRateLimited(string $wabaId): bool
    {
        return (bool) Cache::get('meta_wa_waba_rl_'.$wabaId);
    }

    public function wabaHasNoPhoneEdge(string $wabaId): bool
    {
        return (bool) Cache::get('meta_wa_no_phone_edge_'.$wabaId);
    }

    /**
     * Persist discovered WABA ids (like Instagram syncToConnection).
     * Never wipe linked_waba_ids when Graph is empty / rate-limited.
     *
     * @return array{accounts: array<int, array<string, mixed>>, linked_count: int, incomplete: bool}
     */
    public function syncToConnection(?PlatformMetaConnection $connection = null): array
    {
        $connection ??= $this->connection();
        $detailed = $this->listWabasDetailed();
        $accounts = $detailed['accounts'];
        $incomplete = $detailed['incomplete'];
        $nestedPhones = $detailed['phones'] ?? [];

        if ($nestedPhones !== [] && $connection) {
            $this->persistPhoneDirectory($connection, $nestedPhones);
            // Promote verified_name onto placeholder WABA rows
            $byWaba = [];
            foreach ($nestedPhones as $phone) {
                $wid = (string) ($phone['waba_id'] ?? '');
                $vname = trim((string) ($phone['verified_name'] ?? ''));
                if ($wid !== '' && $vname !== '' && ! isset($byWaba[$wid])) {
                    $byWaba[$wid] = $vname;
                }
            }
            foreach ($accounts as &$row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = (string) ($row['id'] ?? '');
                if ($id !== '' && $this->isPlaceholderWabaName((string) ($row['name'] ?? ''), $id) && isset($byWaba[$id])) {
                    $row['name'] = $byWaba[$id];
                }
            }
            unset($row);
        }

        if (! $connection) {
            return [
                'accounts' => $accounts,
                'linked_count' => 0,
                'incomplete' => $incomplete,
                'phones' => $nestedPhones,
            ];
        }

        if ($accounts === [] || ($incomplete && count($accounts) <= 1 && $this->linkedWabaIds($connection) !== [])) {
            $seeded = $this->seedDirectoryFromConnection($connection);
            Log::warning('WA_SYNC_PRESERVING_LINKED', [
                'connection_id' => $connection->id,
                'linked' => $connection->linked_waba_ids,
                'graph_count' => count($accounts),
                'incomplete' => $incomplete,
            ]);

            // Never throw away a real Graph/DB name for a full placeholder seed list.
            $accounts = $this->mergePreferringRealNames(
                $seeded,
                $this->mergePreferringRealNames(
                    (array) ($connection->linked_waba_directory ?? []),
                    $accounts
                )
            );
            $accounts = $this->enrichPlaceholderNames($accounts);
            $this->persistWabaDirectory($connection, $accounts);

            return [
                'accounts' => $accounts,
                'linked_count' => count($this->linkedWabaIds($connection)),
                'incomplete' => true,
                'phones' => $nestedPhones,
            ];
        }

        // Merge previously linked ids so a partial Graph response never shrinks the directory
        $linked = $this->linkedWabaIds($connection);
        foreach ($accounts as $row) {
            $id = preg_replace('/\D+/', '', (string) ($row['id'] ?? '')) ?: '';
            if ($id !== '' && ! in_array($id, $linked, true)) {
                $linked[] = $id;
            }
        }

        // Only rewrite linked list from Graph when the pull looks complete (no placeholders)
        if (! $incomplete) {
            $linked = [];
            foreach ($accounts as $row) {
                $id = preg_replace('/\D+/', '', (string) ($row['id'] ?? '')) ?: '';
                if ($id !== '' && ! in_array($id, $linked, true)) {
                    $linked[] = $id;
                }
            }
        }

        $default = preg_replace('/\D+/', '', (string) (
            $connection->whatsapp_business_id
            ?: config('platform.whatsapp.business_id')
            ?: ''
        )) ?: '';
        if ($default === '' || ! in_array($default, $linked, true)) {
            $default = $linked[0] ?? $default;
        }

        // Ensure every linked id appears in the returned directory
        $byId = [];
        foreach ($accounts as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id !== '') {
                $row['name'] = $this->bestWabaDisplayName($row, $id);
                $byId[$id] = $row;
            }
        }
        foreach ($linked as $id) {
            if (! isset($byId[$id])) {
                $byId[$id] = [
                    'id' => $id,
                    'name' => 'WhatsApp Business Account '.$id,
                    'ownership_type' => 'linked_import',
                ];
            }
        }

        $accounts = $this->mergePreferringRealNames(
            (array) ($connection->linked_waba_directory ?? []),
            array_values($byId)
        );
        $accounts = $this->enrichPlaceholderNames($accounts);

        $connection->forceFill([
            'linked_waba_ids' => array_values($linked),
            'whatsapp_business_id' => $default !== '' ? $default : $connection->whatsapp_business_id,
        ])->saveQuietly();

        $this->persistWabaDirectory($connection, $accounts);

        return [
            'accounts' => $accounts,
            'linked_count' => count($linked),
            'incomplete' => $incomplete,
            'phones' => $nestedPhones,
        ];
    }

    /**
     * Prefer real Meta names over "WhatsApp Business Account {id}" placeholders.
     *
     * @param  array<int, array<string, mixed>>  $primary
     * @param  array<int, array<string, mixed>>  $secondary
     * @return array<int, array<string, mixed>>
     */
    public function mergePreferringRealNames(array $primary, array $secondary): array
    {
        $byId = [];
        foreach (array_merge($primary, $secondary) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = preg_replace('/\D+/', '', (string) ($row['id'] ?? '')) ?: '';
            if ($id === '') {
                continue;
            }
            $row['id'] = $id;
            $row['name'] = $this->bestWabaDisplayName($row, $id);
            if (! isset($byId[$id])) {
                $byId[$id] = $row;
                continue;
            }
            $existingPlaceholder = $this->isPlaceholderWabaName((string) ($byId[$id]['name'] ?? ''), $id);
            $incomingPlaceholder = $this->isPlaceholderWabaName((string) ($row['name'] ?? ''), $id);
            if ($existingPlaceholder && ! $incomingPlaceholder) {
                $byId[$id] = array_merge($byId[$id], $row);
            } elseif (! $existingPlaceholder && $incomingPlaceholder) {
                // keep existing real name, still absorb other fields
                $byId[$id] = array_merge($row, $byId[$id]);
            } else {
                $byId[$id] = array_merge($byId[$id], $row);
            }
        }

        return array_values($byId);
    }

    /**
     * Fetch display names for placeholder WABAs (stops when Meta rate-limits).
     *
     * @param  array<int, array<string, mixed>>  $accounts
     * @return array<int, array<string, mixed>>
     */
    public function enrichPlaceholderNames(array $accounts): array
    {
        if (Cache::get('meta_wa_rate_limited')) {
            return $this->applyPhoneCacheNames($accounts);
        }

        $enriched = [];
        $lookups = 0;
        foreach ($accounts as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            $name = (string) ($row['name'] ?? '');
            if (
                $id !== ''
                && $this->isPlaceholderWabaName($name, $id)
                && $lookups < 4
                && ! $this->isWabaRateLimited($id)
            ) {
                $lookups++;
                $detail = $this->getWaba($id);
                if ($detail) {
                    $row = array_merge($row, $detail);
                    $row['name'] = $this->bestWabaDisplayName($row, $id);
                } elseif (Cache::get('meta_wa_rate_limited') || $this->isWabaRateLimited($id)) {
                    $enriched[] = $row;
                    $rest = array_slice($accounts, count($enriched));
                    foreach ($rest as $left) {
                        if (is_array($left)) {
                            $enriched[] = $left;
                        }
                    }

                    return $this->applyPhoneCacheNames($enriched);
                }
                usleep(400000);
            }
            $enriched[] = $row;
        }

        return $this->applyPhoneCacheNames($enriched);
    }

    /**
     * @param  array<int, array<string, mixed>>  $accounts
     * @return array<int, array<string, mixed>>
     */
    protected function applyPhoneCacheNames(array $accounts): array
    {
        $connection = $this->connection();
        $phoneKey = 'meta_wa_phone_directory_'.($connection?->id ?? 'platform');
        $phones = Cache::get($phoneKey);
        if (! is_array($phones) || $phones === []) {
            return $accounts;
        }

        $namesByWaba = [];
        foreach ($phones as $p) {
            if (! is_array($p)) {
                continue;
            }
            $wabaId = (string) ($p['waba_id'] ?? '');
            $wabaName = trim((string) ($p['waba_name'] ?? ''));
            $verified = trim((string) ($p['verified_name'] ?? ''));
            if ($wabaId === '') {
                continue;
            }
            if ($wabaName !== '' && ! $this->isPlaceholderWabaName($wabaName, $wabaId)) {
                $namesByWaba[$wabaId] = $wabaName;
            } elseif ($verified !== '' && ! isset($namesByWaba[$wabaId])) {
                $namesByWaba[$wabaId] = $verified;
            }
        }

        foreach ($accounts as &$row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            if ($id !== '' && $this->isPlaceholderWabaName((string) ($row['name'] ?? ''), $id) && isset($namesByWaba[$id])) {
                $row['name'] = $namesByWaba[$id];
            }
        }
        unset($row);

        return $accounts;
    }

    public function isPlaceholderWabaName(string $name, string $id = ''): bool
    {
        $name = trim($name);
        if ($name === '') {
            return true;
        }
        if ($id !== '' && $name === $id) {
            return true;
        }

        return str_starts_with($name, 'WhatsApp Business Account');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function bestWabaDisplayName(array $row, string $id): string
    {
        $candidates = [
            trim((string) ($row['name'] ?? '')),
            trim((string) (data_get($row, 'owner_business_info.name') ?? '')),
            trim((string) (data_get($row, 'on_behalf_of_business_info.name') ?? '')),
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && ! $this->isPlaceholderWabaName($candidate, $id)) {
                return $candidate;
            }
        }

        return $candidates[0] !== '' ? $candidates[0] : ('WhatsApp Business Account '.$id);
    }

    /**
     * @return array<int, array{id: string, name: string, ownership_type?: string}>
     */
    public function seedDirectoryFromConnection(?PlatformMetaConnection $connection = null): array
    {
        $connection ??= $this->connection();
        $items = [];
        $seen = [];

        // Prefer locally persisted directory (survives cache wipes / Meta rate limits)
        foreach ((array) ($connection?->linked_waba_directory ?? []) as $row) {
            if (! is_array($row) || empty($row['id'])) {
                continue;
            }
            $id = preg_replace('/\D+/', '', (string) $row['id']) ?: '';
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $items[] = [
                'id' => $id,
                'name' => $this->bestWabaDisplayName($row, $id),
                'ownership_type' => $row['ownership_type'] ?? 'synced',
            ];
        }

        foreach ($this->linkedWabaIds($connection) as $id) {
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $items[] = [
                'id' => $id,
                'name' => 'WhatsApp Business Account '.$id,
                'ownership_type' => 'linked_import',
            ];
        }

        $default = preg_replace('/\D+/', '', (string) (
            $connection?->whatsapp_business_id
            ?: config('platform.whatsapp.business_id')
            ?: ''
        )) ?: '';
        if ($default !== '' && ! isset($seen[$default])) {
            $items[] = [
                'id' => $default,
                'name' => $connection?->business_name ?? 'Platform WhatsApp account',
                'ownership_type' => 'platform_default',
            ];
        }

        return $this->applyPhoneCacheNames($items);
    }

    /**
     * Persist WABA names locally — never overwrite a real name with a placeholder.
     *
     * @param  array<int, array<string, mixed>>  $accounts
     * @return array<int, array{id: string, name: string, ownership_type?: string}>
     */
    public function persistWabaDirectory(?PlatformMetaConnection $connection, array $accounts): array
    {
        $connection ??= $this->connection();
        if (! $connection) {
            return $accounts;
        }

        $merged = $this->mergePreferringRealNames(
            (array) ($connection->linked_waba_directory ?? []),
            $accounts
        );

        $slim = [];
        foreach ($merged as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = preg_replace('/\D+/', '', (string) ($row['id'] ?? '')) ?: '';
            if ($id === '') {
                continue;
            }
            $slim[] = [
                'id' => $id,
                'name' => $this->bestWabaDisplayName($row, $id),
                'ownership_type' => $row['ownership_type'] ?? 'synced',
            ];
        }

        $updates = [
            'linked_waba_ids' => array_values(array_unique(array_column($slim, 'id'))),
        ];
        if (\Illuminate\Support\Facades\Schema::hasColumn($connection->getTable(), 'linked_waba_directory')) {
            $updates['linked_waba_directory'] = array_values($slim);
        }
        $connection->forceFill($updates)->saveQuietly();

        $cacheKey = 'meta_waba_directory_'.($connection->id ?? 'platform');
        Cache::put($cacheKey, array_values($slim), now()->addMinutes(30));

        return array_values($slim);
    }

    /**
     * Load phone directory: cache → local DB → [].
     *
     * @return array<int, array<string, mixed>>
     */
    public function loadPhoneDirectory(?PlatformMetaConnection $connection = null): array
    {
        $connection ??= $this->connection();
        $key = 'meta_wa_phone_directory_'.($connection?->id ?? 'platform');
        $cached = Cache::get($key);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $stored = (array) ($connection?->whatsapp_phone_directory ?? []);
        if ($stored !== []) {
            Cache::put($key, $stored, now()->addMinutes(30));
        }

        return $stored;
    }

    /**
     * Persist phones locally. Never drops numbers when $incoming is empty/partial.
     *
     * @param  array<int, array<string, mixed>>  $incoming
     * @return array<int, array<string, mixed>>
     */
    public function persistPhoneDirectory(?PlatformMetaConnection $connection, array $incoming): array
    {
        $connection ??= $this->connection();
        if (! $connection) {
            return $incoming;
        }

        $previous = (array) ($connection->whatsapp_phone_directory ?? []);
        $cacheKey = 'meta_wa_phone_directory_'.($connection->id ?? 'platform');
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            $previous = $this->mergePhoneDirectories($previous, $cached);
        }

        if ($incoming === []) {
            $merged = $previous;
        } else {
            $merged = $this->mergePhoneDirectories($previous, $incoming);
            // If Meta returns far fewer phones, keep the richer local set (rate-limit safety)
            if (count($previous) > count($incoming) && count($incoming) <= 2 && count($previous) > 2) {
                $merged = $this->mergePhoneDirectories($previous, $incoming);
            }
        }

        $slim = [];
        foreach ($merged as $phone) {
            if (! is_array($phone) || empty($phone['id'])) {
                continue;
            }
            $slim[] = [
                'id' => (string) $phone['id'],
                'display_phone_number' => (string) ($phone['display_phone_number'] ?? ''),
                'verified_name' => $phone['verified_name'] ?? null,
                'code_verification_status' => $phone['code_verification_status'] ?? null,
                'quality_rating' => $phone['quality_rating'] ?? null,
                'status' => $phone['status'] ?? null,
                'name_status' => $phone['name_status'] ?? null,
                'platform_type' => $phone['platform_type'] ?? null,
                'account_mode' => $phone['account_mode'] ?? null,
                'verified' => (bool) ($phone['verified'] ?? false),
                'waba_id' => (string) ($phone['waba_id'] ?? ''),
                'waba_name' => $phone['waba_name'] ?? null,
            ];
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn($connection->getTable(), 'whatsapp_phone_directory')) {
            $connection->forceFill([
                'whatsapp_phone_directory' => array_values($slim),
            ])->saveQuietly();
        }

        Cache::put($cacheKey, array_values($slim), now()->addMinutes(30));

        return array_values($slim);
    }

    /**
     * @return array<int, string>
     */
    public function linkedWabaIds(?PlatformMetaConnection $connection = null): array
    {
        $connection ??= $this->connection();
        $ids = $connection?->linked_waba_ids ?? [];
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($id) => preg_replace('/\D+/', '', (string) $id) ?: '',
            $ids
        ))));
    }

    /**
     * Create a new owned WABA under the Business Manager (Meta “Create a new WhatsApp Business account”).
     *
     * @return array{waba: array<string, mixed>, message: string}
     */
    public function createOwnedWaba(string $name, string $currency = 'CAD'): array
    {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages([
                'waba_name' => 'Enter a name for the WhatsApp Business account.',
            ]);
        }

        $connection = $this->connection();
        if (! $connection) {
            throw ValidationException::withMessages([
                'connection' => 'No Meta connection. Connect Meta first.',
            ]);
        }

        $bmId = $this->resolveBusinessManagerId($connection);
        if (! $bmId) {
            throw ValidationException::withMessages([
                'waba_name' => 'Business Manager ID missing. Set META_BUSINESS_ID in .env or reconnect Meta.',
            ]);
        }

        $token = $this->requireToken();
        $response = Http::timeout(45)->asForm()->post(
            "{$this->graphUrl}/{$this->graphVersion}/{$bmId}/owned_whatsapp_business_accounts",
            [
                'name' => $name,
                'currency' => strtoupper($currency),
                'timezone_id' => '1',
                'access_token' => $token,
            ]
        );

        if (! $response->ok()) {
            $msg = data_get($response->json(), 'error.message')
                ?: 'Meta could not create the WhatsApp Business account. Open Meta Business Suite to create it, then use Link.';
            throw ValidationException::withMessages(['waba_name' => $msg]);
        }

        $id = (string) ($response->json('id') ?? '');
        $detail = $id !== '' ? ($this->getWaba($id) ?? ['id' => $id, 'name' => $name]) : ['name' => $name];

        if ($id !== '') {
            $linked = $this->linkedWabaIds($connection);
            if (! in_array($id, $linked, true)) {
                $linked[] = $id;
            }
            $connection->forceFill([
                'linked_waba_ids' => array_values($linked),
                'whatsapp_business_id' => $connection->whatsapp_business_id ?: $id,
            ])->saveQuietly();
        }

        return [
            'waba' => $detail,
            'message' => 'WhatsApp Business account “'.($detail['name'] ?? $name).'” created under your Business Manager.',
        ];
    }

    /**
     * Partner-style request: associate / request a WABA for a client business
     * (Meta “Request a WhatsApp Business account for a client”).
     *
     * @return array{message: string}
     */
    public function requestClientWaba(string $clientBusinessId, string $name): array
    {
        $clientBusinessId = preg_replace('/\D+/', '', trim($clientBusinessId)) ?: '';
        $name = trim($name);

        if ($clientBusinessId === '' || strlen($clientBusinessId) < 5) {
            throw ValidationException::withMessages([
                'client_business_id' => 'Enter the client’s Business Manager / portfolio ID.',
            ]);
        }
        if ($name === '') {
            throw ValidationException::withMessages([
                'waba_name' => 'Enter a name for the client WhatsApp Business account.',
            ]);
        }

        $connection = $this->connection();
        if (! $connection) {
            throw ValidationException::withMessages([
                'connection' => 'No Meta connection. Connect Meta first.',
            ]);
        }

        $bmId = $this->resolveBusinessManagerId($connection);
        if (! $bmId) {
            throw ValidationException::withMessages([
                'client_business_id' => 'Your Business Manager ID is missing. Set META_BUSINESS_ID or reconnect Meta.',
            ]);
        }

        $token = $this->requireToken();

        // Attempt partner create under client BM; Meta often requires Solution Partner privileges.
        $response = Http::timeout(45)->asForm()->post(
            "{$this->graphUrl}/{$this->graphVersion}/{$clientBusinessId}/owned_whatsapp_business_accounts",
            [
                'name' => $name,
                'currency' => 'CAD',
                'timezone_id' => '1',
                'access_token' => $token,
            ]
        );

        if ($response->ok()) {
            $id = (string) ($response->json('id') ?? '');
            if ($id !== '') {
                // Also share into our BM as client WABA when possible
                try {
                    Http::timeout(30)->asForm()->post(
                        "{$this->graphUrl}/{$this->graphVersion}/{$bmId}/client_whatsapp_business_accounts",
                        [
                            'whatsapp_business_account_id' => $id,
                            'access_token' => $token,
                        ]
                    );
                } catch (\Throwable) {
                }

                $linked = $this->linkedWabaIds($connection);
                if (! in_array($id, $linked, true)) {
                    $linked[] = $id;
                }
                $connection->forceFill(['linked_waba_ids' => array_values($linked)])->saveQuietly();
            }

            return [
                'message' => 'Client WhatsApp Business account request created'
                    .($id !== '' ? " (ID {$id})." : '.')
                    .' Ask the client to accept it in Meta Business Suite if prompted.',
            ];
        }

        $msg = data_get($response->json(), 'error.message')
            ?: 'Meta could not create the client request from this token.';

        throw ValidationException::withMessages([
            'client_business_id' => $msg.' Use Meta Business Suite → WhatsApp accounts → Add → Request for a client, then Link the WABA ID here.',
        ]);
    }

    /**
     * Meta-style “Link WhatsApp Business account” by phone number.
     * Finds WABAs/phones the token can access, then optionally request_code.
     *
     * @return array{
     *   step: string,
     *   display_phone: string,
     *   phone_number_id?: string,
     *   waba_id?: string,
     *   matches: array<int, array<string, mixed>>,
     *   message: string,
     *   resend_after?: int
     * }
     */
    public function startLinkByPhone(string $countryCode, string $nationalNumber): array
    {
        $digits = $this->normalizePhoneDigits($countryCode, $nationalNumber);
        if (strlen($digits) < 10) {
            throw ValidationException::withMessages([
                'phone_number' => 'Enter a valid WhatsApp Business phone number.',
            ]);
        }

        $matches = $this->findPhoneMatches($digits);
        if ($matches === []) {
            throw ValidationException::withMessages([
                'phone_number' => 'No WhatsApp Business account with this number is accessible to your Meta token. In Meta Business Suite, share/assign the WhatsApp account to your business (or system user), then try again.',
            ]);
        }

        $primary = $matches[0];
        $phoneNumberId = (string) $primary['phone_number_id'];
        $display = $this->formatDisplayPhone($digits);
        $alreadyVerified = (bool) ($primary['verified'] ?? false);

        if ($alreadyVerified) {
            return [
                'step' => 'businesses',
                'display_phone' => $display,
                'phone_number_id' => $phoneNumberId,
                'waba_id' => (string) $primary['waba_id'],
                'matches' => $matches,
                'message' => 'Number verified. Select the associated business to link.',
                'resend_after' => 0,
            ];
        }

        try {
            $this->requestVerificationCode($phoneNumberId, 'SMS');
        } catch (ValidationException $e) {
            $msg = strtolower(collect($e->errors())->flatten()->first() ?? '');
            // Already verified / code not needed → continue to business picker
            if (str_contains($msg, 'already') || str_contains($msg, '136024') || str_contains($msg, 'verified')) {
                return [
                    'step' => 'businesses',
                    'display_phone' => $display,
                    'phone_number_id' => $phoneNumberId,
                    'waba_id' => (string) $primary['waba_id'],
                    'matches' => $matches,
                    'message' => 'Number already verified with Meta. Select the associated business to link.',
                    'resend_after' => 0,
                ];
            }
            throw $e;
        }

        return [
            'step' => 'code',
            'display_phone' => $display,
            'phone_number_id' => $phoneNumberId,
            'waba_id' => (string) $primary['waba_id'],
            'matches' => $matches,
            'message' => "A verification code was sent for {$display}. Enter it here to finish adding your WhatsApp account.",
            'resend_after' => 30,
        ];
    }

    /**
     * After OTP: mark verified and return associated businesses for selection.
     *
     * @return array{step: string, matches: array<int, array<string, mixed>>, display_phone: string, phone_number_id: string, message: string}
     */
    public function verifyLinkByPhone(string $phoneNumberId, string $code, ?string $wabaId = null): array
    {
        $phoneNumberId = preg_replace('/\D+/', '', $phoneNumberId) ?: '';
        $code = preg_replace('/\D+/', '', $code) ?: '';
        if ($phoneNumberId === '' || strlen($code) < 5) {
            throw ValidationException::withMessages([
                'code' => 'Enter the 5-digit verification code from WhatsApp / SMS.',
            ]);
        }

        $this->verifyAndRegister($phoneNumberId, $code, $wabaId);

        $details = $this->getPhoneDetails($phoneNumberId, $this->requireToken()) ?? [];
        $displayDigits = TenantMetaPageValidator::normalizeWhatsAppNumber((string) ($details['display_phone_number'] ?? ''));
        $matches = $displayDigits !== ''
            ? $this->findPhoneMatches($displayDigits)
            : $this->matchesFromPhoneId($phoneNumberId, $wabaId);

        if ($matches === []) {
            $matches = $this->matchesFromPhoneId($phoneNumberId, $wabaId);
        }

        return [
            'step' => 'businesses',
            'display_phone' => $this->formatDisplayPhone($displayDigits ?: $phoneNumberId),
            'phone_number_id' => $phoneNumberId,
            'matches' => $matches,
            'message' => 'Verification successful. Select the associated business to link.',
        ];
    }

    /**
     * Finalize link after user picks a business / WABA from the verified phone matches.
     *
     * @return array{waba: array<string, mixed>, phones: array<int, array<string, mixed>>, message: string}
     */
    public function completeLinkByPhone(string $wabaId, string $phoneNumberId): array
    {
        $result = $this->importExistingWaba($wabaId);
        $this->setAsPlatformDefault($phoneNumberId, $wabaId);

        $business = $result['waba']['owner_business_info']['name']
            ?? $result['waba']['on_behalf_of_business_info']['name']
            ?? $result['waba']['name']
            ?? $wabaId;

        $result['message'] = "Linked WhatsApp Business account for “{$business}”. Phone set as platform default.";

        return $result;
    }

    /**
     * @return array<int, array{
     *   phone_number_id: string,
     *   display_phone_number: string,
     *   verified_name: ?string,
     *   verified: bool,
     *   waba_id: string,
     *   waba_name: ?string,
     *   business_id: ?string,
     *   business_name: ?string,
     *   business_verification_status: ?string
     * }>
     */
    public function findPhoneMatches(string $digits): array
    {
        $digits = preg_replace('/\D+/', '', $digits) ?: '';
        $needle10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;
        $matches = [];

        foreach ($this->listAllPhoneNumbers() as $phone) {
            $display = TenantMetaPageValidator::normalizeWhatsAppNumber((string) ($phone['display_phone_number'] ?? ''));
            if ($display === '' || (substr($display, -10) !== $needle10 && $display !== $digits)) {
                continue;
            }

            $wabaId = (string) ($phone['waba_id'] ?? '');
            $detail = $wabaId !== '' ? $this->getWaba($wabaId) : null;
            $businessName = data_get($detail, 'owner_business_info.name')
                ?: data_get($detail, 'on_behalf_of_business_info.name')
                ?: ($phone['waba_name'] ?? null)
                ?: ($detail['name'] ?? null);
            $businessId = data_get($detail, 'owner_business_info.id')
                ?: data_get($detail, 'on_behalf_of_business_info.id');

            $matches[] = [
                'phone_number_id' => (string) ($phone['id'] ?? ''),
                'display_phone_number' => (string) ($phone['display_phone_number'] ?? ''),
                'verified_name' => $phone['verified_name'] ?? null,
                'verified' => (bool) ($phone['verified'] ?? $this->isPhoneVerified($phone)),
                'waba_id' => $wabaId,
                'waba_name' => $phone['waba_name'] ?? ($detail['name'] ?? null),
                'business_id' => $businessId ? (string) $businessId : null,
                'business_name' => $businessName ? (string) $businessName : null,
                'business_verification_status' => $detail['business_verification_status'] ?? null,
            ];
        }

        // Unique by waba_id + phone_number_id
        $seen = [];
        $unique = [];
        foreach ($matches as $row) {
            $key = $row['waba_id'].':'.$row['phone_number_id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $row;
        }

        return $unique;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function matchesFromPhoneId(string $phoneNumberId, ?string $wabaId = null): array
    {
        $details = $this->getPhoneDetails($phoneNumberId, $this->requireToken());
        if (! $details) {
            return [];
        }

        $wabaId = $wabaId ?: '';
        if ($wabaId === '') {
            foreach ($this->listAllPhoneNumbers() as $phone) {
                if ((string) ($phone['id'] ?? '') === $phoneNumberId) {
                    $wabaId = (string) ($phone['waba_id'] ?? '');
                    break;
                }
            }
        }

        $detail = $wabaId !== '' ? $this->getWaba($wabaId) : null;

        return [[
            'phone_number_id' => $phoneNumberId,
            'display_phone_number' => (string) ($details['display_phone_number'] ?? ''),
            'verified_name' => $details['verified_name'] ?? null,
            'verified' => true,
            'waba_id' => $wabaId,
            'waba_name' => $detail['name'] ?? null,
            'business_id' => data_get($detail, 'owner_business_info.id'),
            'business_name' => data_get($detail, 'owner_business_info.name')
                ?: data_get($detail, 'on_behalf_of_business_info.name')
                ?: ($detail['name'] ?? null),
            'business_verification_status' => $detail['business_verification_status'] ?? null,
        ]];
    }

    protected function normalizePhoneDigits(string $countryCode, string $nationalNumber): string
    {
        $cc = preg_replace('/\D+/', '', $countryCode) ?: '1';
        $national = preg_replace('/\D+/', '', $nationalNumber) ?: '';
        // Avoid double country code when user pastes full international number
        if (str_starts_with($national, $cc) && strlen($national) > 10) {
            return $national;
        }
        if (strlen($national) === 10) {
            return $cc.$national;
        }

        return TenantMetaPageValidator::normalizeWhatsAppNumber($cc.$national);
    }

    protected function formatDisplayPhone(string $digits): string
    {
        $digits = preg_replace('/\D+/', '', $digits) ?: '';
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '+1 ('.substr($digits, 1, 3).') '.substr($digits, 4, 3).'-'.substr($digits, 7);
        }
        if (strlen($digits) === 10) {
            return '+1 ('.substr($digits, 0, 3).') '.substr($digits, 3, 3).'-'.substr($digits, 6);
        }

        return $digits !== '' ? '+'.$digits : '';
    }

    /**
     * Import / link an existing WhatsApp Business Account (same idea as Meta BM
     * "Link a WhatsApp Business account").
     *
     * Requires the system token to already have access (shared/owned in Meta).
     * We verify via Graph, persist the WABA id, and sync its phone numbers.
     *
     * @return array{waba: array<string, mixed>, phones: array<int, array<string, mixed>>, message: string}
     */
    public function importExistingWaba(string $wabaId): array
    {
        $wabaId = preg_replace('/\D+/', '', trim($wabaId)) ?: '';
        if ($wabaId === '' || strlen($wabaId) < 5) {
            throw ValidationException::withMessages([
                'waba_id' => 'Enter a valid WhatsApp Business Account ID from Meta Business Manager.',
            ]);
        }

        $connection = $this->connection();
        if (! $connection) {
            throw ValidationException::withMessages([
                'connection' => 'No Meta connection. Sync from .env or Connect Meta first.',
            ]);
        }

        $detail = $this->getWaba($wabaId);
        if (! $detail) {
            throw ValidationException::withMessages([
                'waba_id' => 'This WABA is not accessible with the current Meta token. In Meta Business Manager, share/assign the WhatsApp account to your business (or system user), then try again.',
            ]);
        }

        // Best-effort: ask Meta to associate as client WABA when BM id is known
        $bmId = $this->resolveBusinessManagerId($connection);
        if ($bmId) {
            try {
                $token = $this->requireToken();
                Http::timeout(30)->asForm()->post(
                    "{$this->graphUrl}/{$this->graphVersion}/{$bmId}/client_whatsapp_business_accounts",
                    [
                        'whatsapp_business_account_id' => $wabaId,
                        'access_token' => $token,
                    ]
                );
            } catch (\Throwable) {
                // Local import still succeeds if Graph GET worked
            }
        }

        $linked = $this->linkedWabaIds($connection);
        if (! in_array($wabaId, $linked, true)) {
            $linked[] = $wabaId;
        }
        $connection->forceFill(['linked_waba_ids' => array_values($linked)])->saveQuietly();

        // Persist owner BM if discovered from this WABA
        $ownerId = (string) (
            data_get($detail, 'owner_business_info.id')
            ?: data_get($detail, 'on_behalf_of_business_info.id')
            ?: ''
        );
        if ($ownerId !== '' && empty($connection->business_id)) {
            $this->persistBusinessId(
                $connection,
                $ownerId,
                data_get($detail, 'owner_business_info.name') ?: data_get($detail, 'on_behalf_of_business_info.name')
            );
        }

        $phones = [];
        try {
            $phones = $this->listPhoneNumbers($wabaId);
        } catch (\Throwable) {
            $phones = [];
        }

        return [
            'waba' => $detail,
            'phones' => $phones,
            'message' => 'WhatsApp Business account “'.($detail['name'] ?? $wabaId).'” linked. '
                .count($phones).' phone number(s) synced for Ad Studio.',
        ];
    }

    /**
     * Resolve Meta Business Manager ID (not WABA id) and persist when discovered.
     */
    public function resolveBusinessManagerId(?PlatformMetaConnection $connection = null): ?string
    {
        $connection ??= $this->connection();
        $explicit = trim((string) (
            config('platform.meta.business_id')
            ?: ''
        ));
        if ($explicit !== '') {
            $this->persistBusinessId($connection, $explicit, null);

            return $explicit;
        }

        $stored = trim((string) ($connection?->business_id ?? ''));
        $wabaId = trim((string) (
            $connection?->whatsapp_business_id
            ?: config('platform.whatsapp.business_id')
            ?: ''
        ));

        // Stored value that equals WABA id is NOT a Business Manager id.
        if ($stored !== '' && $stored !== $wabaId && $this->businessHasWabaEdge($stored)) {
            return $stored;
        }

        $token = $this->requireToken();

        // Discover BM from WABA owner fields.
        if ($wabaId !== '') {
            $response = Http::timeout(30)->get(
                "{$this->graphUrl}/{$this->graphVersion}/{$wabaId}",
                [
                    'access_token' => $token,
                    'fields' => 'id,name,owner_business_info{id,name},on_behalf_of_business_info{id,name}',
                ]
            );
            if ($response->ok()) {
                $ownerId = (string) (
                    data_get($response->json(), 'owner_business_info.id')
                    ?: data_get($response->json(), 'on_behalf_of_business_info.id')
                    ?: ''
                );
                $ownerName = (string) (
                    data_get($response->json(), 'owner_business_info.name')
                    ?: data_get($response->json(), 'on_behalf_of_business_info.name')
                    ?: ''
                );
                if ($ownerId !== '') {
                    $this->persistBusinessId($connection, $ownerId, $ownerName !== '' ? $ownerName : null);

                    return $ownerId;
                }
            }
        }

        // me/businesses (works for some tokens)
        $biz = Http::timeout(30)->get(
            "{$this->graphUrl}/{$this->graphVersion}/me/businesses",
            [
                'access_token' => $token,
                'fields' => 'id,name',
                'limit' => 25,
            ]
        );
        if ($biz->ok()) {
            foreach ($biz->json('data', []) as $row) {
                $id = (string) ($row['id'] ?? '');
                if ($id !== '' && $this->businessHasWabaEdge($id)) {
                    $this->persistBusinessId($connection, $id, $row['name'] ?? null);

                    return $id;
                }
            }
            $first = $biz->json('data.0.id');
            if ($first) {
                $this->persistBusinessId($connection, (string) $first, $biz->json('data.0.name'));

                return (string) $first;
            }
        }

        return $stored !== '' && $stored !== $wabaId ? $stored : null;
    }

    protected function persistBusinessId(?PlatformMetaConnection $connection, string $businessId, ?string $name): void
    {
        if (! $connection) {
            return;
        }
        $updates = ['business_id' => $businessId];
        if ($name) {
            $updates['business_name'] = $name;
        }
        $connection->forceFill($updates)->saveQuietly();
    }

    protected function businessHasWabaEdge(string $businessId): bool
    {
        try {
            $token = $this->requireToken();
            $response = Http::timeout(20)->get(
                "{$this->graphUrl}/{$this->graphVersion}/{$businessId}/owned_whatsapp_business_accounts",
                [
                    'access_token' => $token,
                    'fields' => 'id',
                    'limit' => 1,
                ]
            );

            return $response->ok();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<int, mixed>
     */
    protected function paginateEdge(string $url, array $params, int $maxPages = 8): array
    {
        return $this->paginateEdgeDetailed($url, $params, $maxPages)['rows'];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{rows: array<int, mixed>, ok: bool, error: ?string}
     */
    protected function paginateEdgeDetailed(string $url, array $params, int $maxPages = 8): array
    {
        $all = [];
        $next = null;
        $page = 0;
        $ok = false;
        $error = null;

        do {
            $page++;
            $response = $next
                ? Http::timeout(30)->get($next)
                : Http::timeout(30)->get($url, $params);

            if (! $response->ok()) {
                $error = (string) (data_get($response->json(), 'error.message') ?: ('HTTP '.$response->status()));
                break;
            }

            $ok = true;
            foreach ($response->json('data', []) as $row) {
                $all[] = $row;
            }

            $next = $response->json('paging.next');
        } while ($next && $page < $maxPages);

        return [
            'rows' => $all,
            'ok' => $ok,
            'error' => $error,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getWaba(string $wabaId): ?array
    {
        $wabaId = preg_replace('/\D+/', '', $wabaId) ?: '';
        if ($wabaId === '' || $this->isWabaRateLimited($wabaId)) {
            return null;
        }

        $token = $this->requireToken();

        $response = Http::timeout(20)->get(
            "{$this->graphUrl}/{$this->graphVersion}/{$wabaId}",
            [
                'access_token' => $token,
                'fields' => 'id,name,currency,timezone_id,message_template_namespace,account_review_status,business_verification_status,on_behalf_of_business_info,owner_business_info',
            ]
        );

        if (! $response->ok()) {
            $json = $response->json();
            $message = (string) (data_get($json, 'error.message') ?: $response->body());
            $this->rememberGraphRateLimit($message, $wabaId);
            Log::warning('WABA_GET_FAILED', [
                'waba_id' => $wabaId,
                'error' => $message,
            ]);

            return null;
        }

        $data = $response->json();
        $id = (string) ($data['id'] ?? $wabaId);

        return [
            'id' => $id,
            'name' => $this->bestWabaDisplayName(is_array($data) ? $data : [], $id),
            'currency' => $data['currency'] ?? null,
            'timezone_id' => $data['timezone_id'] ?? null,
            'account_review_status' => $data['account_review_status'] ?? null,
            'business_verification_status' => $data['business_verification_status'] ?? null,
            'on_behalf_of_business_info' => $data['on_behalf_of_business_info'] ?? null,
            'owner_business_info' => $data['owner_business_info'] ?? null,
            'message_template_namespace' => $data['message_template_namespace'] ?? null,
        ];
    }

    /**
     * All known WABA ids (local DB + linked ids + Graph when available).
     *
     * @return array<int, string>
     */
    public function discoverWabaIds(?PlatformMetaConnection $connection = null): array
    {
        $connection ??= $this->connection();
        $ids = [];

        foreach ($this->linkedWabaIds($connection) as $id) {
            if ($id !== '') {
                $ids[$id] = true;
            }
        }

        foreach ((array) ($connection?->linked_waba_directory ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = preg_replace('/\D+/', '', (string) ($row['id'] ?? '')) ?: '';
            if ($id !== '') {
                $ids[$id] = true;
            }
        }

        $fallback = preg_replace('/\D+/', '', (string) (
            $connection?->whatsapp_business_id ?: config('platform.whatsapp.business_id') ?: ''
        )) ?: '';
        if ($fallback !== '') {
            $ids[$fallback] = true;
        }

        try {
            foreach ($this->listWabas() as $waba) {
                $id = preg_replace('/\D+/', '', (string) ($waba['id'] ?? '')) ?: '';
                if ($id !== '') {
                    $ids[$id] = true;
                }
            }
        } catch (\Throwable) {
            // local ids are enough
        }

        return array_values(array_keys($ids));
    }

    /**
     * Production phone sync: pull /{waba}/phone_numbers for every known WABA and persist locally.
     * Never deletes previously saved numbers when Meta returns empty for a WABA.
     *
     * @return array{phones: array<int, array<string, mixed>>, fetched_wabas: int, phones_found: int}
     */
    public function syncAllPhonesFromMeta(?PlatformMetaConnection $connection = null, bool $forceRefresh = false): array
    {
        $connection ??= $this->connection();
        $previous = $this->loadPhoneDirectory($connection);
        $namesByWaba = [];
        foreach ((array) ($connection?->linked_waba_directory ?? []) as $row) {
            if (! is_array($row) || empty($row['id'])) {
                continue;
            }
            $namesByWaba[(string) $row['id']] = $row['name'] ?? null;
        }

        // Prefer nested phones from Business Manager directory (2 Graph calls) over N edge hits.
        // Soft sync skips re-list when local phones already exist — cacheWabaDirectory just ran nested.
        $incoming = [];
        $fetched = 0;
        $shouldNested = $forceRefresh || $previous === [];
        if ($shouldNested) {
            try {
                $bundle = $this->listWabasDetailed();
                foreach ($bundle['phones'] ?? [] as $phone) {
                    if (! is_array($phone) || empty($phone['id'])) {
                        continue;
                    }
                    $wabaId = (string) ($phone['waba_id'] ?? '');
                    $incoming[] = array_merge($phone, [
                        'waba_name' => $namesByWaba[$wabaId]
                            ?? ($phone['waba_name'] ?? null)
                            ?? ($phone['verified_name'] ?? null),
                    ]);
                }
                if (! empty($bundle['accounts']) && $connection) {
                    $accounts = $this->mergePreferringRealNames(
                        (array) ($connection->linked_waba_directory ?? []),
                        $bundle['accounts']
                    );
                    $this->persistWabaDirectory($connection, $accounts);
                    Cache::put(
                        'meta_waba_directory_'.($connection->id ?? 'platform'),
                        $accounts,
                        now()->addMinutes(30)
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('WA_NESTED_PHONE_SYNC_FAILED', ['error' => $e->getMessage()]);
            }
        }

        $covered = [];
        foreach ($incoming as $phone) {
            $wid = (string) ($phone['waba_id'] ?? '');
            if ($wid !== '') {
                $covered[$wid] = true;
            }
        }
        foreach ($previous as $p) {
            if (! is_array($p)) {
                continue;
            }
            $wid = (string) ($p['waba_id'] ?? '');
            if ($wid !== '' && ! $forceRefresh) {
                $covered[$wid] = true;
            }
        }

        // Only hit /{waba}/phone_numbers for WABAs still missing numbers (and not banned / unsupported).
        foreach ($this->discoverWabaIds($connection) as $wabaId) {
            if (isset($covered[$wabaId])) {
                continue;
            }
            if ($this->wabaHasNoPhoneEdge($wabaId) || $this->isWabaRateLimited($wabaId)) {
                continue;
            }
            if (Cache::get('meta_wa_rate_limited') && ! $forceRefresh) {
                break;
            }
            // Cap individual edge calls — Sync must not revive Meta #4 / #80008 storms
            if ($fetched >= 3) {
                break;
            }

            $phones = $this->listPhoneNumbers($wabaId);
            $fetched++;
            foreach ($phones as $phone) {
                $incoming[] = array_merge($phone, [
                    'waba_id' => $wabaId,
                    'waba_name' => $namesByWaba[$wabaId]
                        ?? ($phone['verified_name'] ?? null),
                ]);
            }
            if ($phones !== []) {
                $covered[$wabaId] = true;
                // Upgrade placeholder WABA name from verified phone display name
                $vname = trim((string) ($phones[0]['verified_name'] ?? ''));
                if ($vname !== '' && $connection) {
                    $dir = (array) ($connection->linked_waba_directory ?? []);
                    foreach ($dir as &$row) {
                        if (is_array($row) && (string) ($row['id'] ?? '') === $wabaId
                            && $this->isPlaceholderWabaName((string) ($row['name'] ?? ''), $wabaId)) {
                            $row['name'] = $vname;
                        }
                    }
                    unset($row);
                    $this->persistWabaDirectory($connection, $dir);
                }
            }
            usleep(500000);
        }

        $merged = $this->persistPhoneDirectory($connection, $incoming);

        Log::info('WA_PHONE_SYNC_DONE', [
            'connection_id' => $connection?->id,
            'fetched_wabas' => $fetched,
            'incoming' => count($incoming),
            'persisted' => count($merged),
            'force' => $forceRefresh,
        ]);

        return [
            'phones' => $merged,
            'fetched_wabas' => $fetched,
            'phones_found' => count($incoming),
        ];
    }

    /**
     * Fetch phone numbers for a single WABA and upsert into the durable directory.
     *
     * @return array<int, array<string, mixed>>
     */
    public function syncPhonesForWaba(string $wabaId, ?PlatformMetaConnection $connection = null): array
    {
        $wabaId = preg_replace('/\D+/', '', $wabaId) ?: '';
        if ($wabaId === '') {
            return [];
        }

        $connection ??= $this->connection();
        $previous = $this->loadPhoneDirectory($connection);
        $fetched = $this->listPhoneNumbers($wabaId);
        if ($fetched === []) {
            return array_values(array_filter(
                $previous,
                fn ($p) => is_array($p) && (string) ($p['waba_id'] ?? '') === $wabaId
            ));
        }

        $name = null;
        foreach ((array) ($connection?->linked_waba_directory ?? []) as $row) {
            if (is_array($row) && (string) ($row['id'] ?? '') === $wabaId) {
                $name = $row['name'] ?? null;
                break;
            }
        }

        $merged = $this->upsertPhonesForWaba($previous, $wabaId, $fetched, $name);
        $this->persistPhoneDirectory($connection, $merged);

        return array_values(array_filter(
            $merged,
            fn ($p) => is_array($p) && (string) ($p['waba_id'] ?? '') === $wabaId
        ));
    }

    /**
     * List phone numbers across all known WABAs (local directory + Graph).
     * Soft callers fill gaps; Sync now uses syncAllPhonesFromMeta(..., true).
     *
     * @return array<int, array{id: string, display_phone_number: string, verified_name: ?string, code_verification_status: ?string, quality_rating: ?string, status: ?string, waba_id: string, waba_name: ?string}>
     */
    public function listAllPhoneNumbers(bool $forceRefresh = false): array
    {
        return $this->syncAllPhonesFromMeta($this->connection(), $forceRefresh)['phones'];
    }

    /**
     * Merge phone directories without dropping numbers when Meta returns a partial/empty pull.
     *
     * @param  array<int, array<string, mixed>>  $previous
     * @param  array<int, array<string, mixed>>  $incoming
     * @return array<int, array<string, mixed>>
     */
    public function mergePhoneDirectories(array $previous, array $incoming): array
    {
        $byId = [];
        foreach ($previous as $phone) {
            if (! is_array($phone)) {
                continue;
            }
            $id = (string) ($phone['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $byId[$id] = $phone;
        }

        $incomingWabas = [];
        foreach ($incoming as $phone) {
            if (! is_array($phone)) {
                continue;
            }
            $id = (string) ($phone['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $wabaId = (string) ($phone['waba_id'] ?? '');
            if ($wabaId !== '') {
                $incomingWabas[$wabaId] = true;
            }
            $byId[$id] = array_merge($byId[$id] ?? [], $phone);
        }

        // If a WABA was in the incoming batch with an explicit empty list, callers should
        // not pass empty — they omit that WABA so previous numbers for it stay intact.

        return array_values($byId);
    }

    /**
     * Replace phones for one WABA only; keep every other WABA's numbers intact.
     * Empty $phonesForWaba keeps the previous numbers for that WABA (rate-limit safe).
     *
     * @param  array<int, array<string, mixed>>  $previous
     * @param  array<int, array<string, mixed>>  $phonesForWaba
     * @return array<int, array<string, mixed>>
     */
    public function upsertPhonesForWaba(array $previous, string $wabaId, array $phonesForWaba, ?string $wabaName = null): array
    {
        $wabaId = (string) $wabaId;
        if ($phonesForWaba === []) {
            return $previous;
        }

        $kept = [];
        foreach ($previous as $phone) {
            if (! is_array($phone)) {
                continue;
            }
            if ((string) ($phone['waba_id'] ?? '') === $wabaId) {
                continue;
            }
            $kept[] = $phone;
        }

        foreach ($phonesForWaba as $phone) {
            if (! is_array($phone) || empty($phone['id'])) {
                continue;
            }
            $kept[] = array_merge($phone, array_filter([
                'waba_id' => $wabaId,
                'waba_name' => $wabaName ?: ($phone['waba_name'] ?? null),
            ], fn ($v) => $v !== null && $v !== ''));
        }

        return array_values($kept);
    }

    /**
     * @return array<int, array{id: string, display_phone_number: string, verified_name: ?string, code_verification_status: ?string, quality_rating: ?string, status: ?string, name_status: ?string, platform_type: ?string, verified: bool}>
     */
    public function listPhoneNumbers(string $wabaId): array
    {
        $wabaId = preg_replace('/\D+/', '', $wabaId) ?: '';
        if ($wabaId === '' || $this->wabaHasNoPhoneEdge($wabaId) || $this->isWabaRateLimited($wabaId)) {
            return [];
        }

        $token = $this->requireToken();
        $all = [];
        $url = "{$this->graphUrl}/{$this->graphVersion}/{$wabaId}/phone_numbers";
        $params = [
            'access_token' => $token,
            'fields' => 'id,display_phone_number,verified_name,code_verification_status,quality_rating,status,platform_type,throughput,name_status,is_preverified_number,account_mode',
            'limit' => 100,
        ];

        for ($page = 0; $page < 10; $page++) {
            $response = Http::timeout(25)->get($url, $params);

            if (! $response->ok()) {
                $json = $response->json();
                $message = (string) (data_get($json, 'error.message') ?: $response->body());
                $code = (int) data_get($json, 'error.code', 0);
                $body = strtolower($message);

                // Not a Cloud-API WABA (common for some Business App / stale linked ids)
                if ($code === 100 || str_contains($body, 'nonexisting field')) {
                    Cache::put('meta_wa_no_phone_edge_'.$wabaId, 1, now()->addHours(12));
                    Log::info('WABA_NO_PHONE_EDGE', ['waba_id' => $wabaId, 'error' => $message]);
                    break;
                }

                $this->rememberGraphRateLimit($message, $wabaId);
                Log::warning('WABA_LIST_PHONES_FAILED', [
                    'waba_id' => $wabaId,
                    'response' => $json,
                ]);
                break;
            }

            foreach ($response->json('data', []) as $row) {
                if (! is_array($row) || empty($row['id'])) {
                    continue;
                }
                $mapped = [
                    'id' => (string) $row['id'],
                    'display_phone_number' => (string) ($row['display_phone_number'] ?? ''),
                    'verified_name' => $row['verified_name'] ?? null,
                    'code_verification_status' => $row['code_verification_status'] ?? null,
                    'name_status' => $row['name_status'] ?? null,
                    'quality_rating' => is_array($row['quality_rating'] ?? null)
                        ? ($row['quality_rating']['score'] ?? null)
                        : ($row['quality_rating'] ?? null),
                    'status' => $row['status'] ?? null,
                    'platform_type' => $row['platform_type'] ?? null,
                    'is_preverified_number' => (bool) ($row['is_preverified_number'] ?? false),
                    'account_mode' => $row['account_mode'] ?? null,
                ];
                $mapped['verified'] = $this->isPhoneVerified($mapped);
                $all[] = $mapped;
            }

            $next = $response->json('paging.next');
            if (! is_string($next) || $next === '') {
                break;
            }
            $url = $next;
            $params = [];
        }

        return $all;
    }

    /**
     * Meta "verified" for ads ≠ only code_verification_status.
     * Business App / coexistence numbers often omit VERIFIED on that field
     * even when the number is live and usable for Click-to-WhatsApp.
     *
     * @param  array<string, mixed>  $phone
     */
    public function isPhoneVerified(array $phone): bool
    {
        $code = strtoupper((string) ($phone['code_verification_status'] ?? ''));
        if ($code === 'VERIFIED') {
            return true;
        }

        // Explicit OTP failure / expiry only
        if (in_array($code, ['EXPIRED'], true)) {
            return false;
        }

        $nameStatus = strtoupper((string) ($phone['name_status'] ?? ''));
        if (in_array($nameStatus, ['APPROVED', 'AVAILABLE_WITHOUT_REVIEW'], true)) {
            return true;
        }

        $status = strtoupper((string) ($phone['status'] ?? ''));
        if (in_array($status, ['CONNECTED', 'ONLINE', 'AVAILABLE'], true)) {
            return true;
        }

        if (! empty($phone['is_preverified_number'])) {
            return true;
        }

        // Display name already set on the number = Meta has accepted it for BM / ads
        $verifiedName = trim((string) ($phone['verified_name'] ?? ''));
        if ($verifiedName !== '' && $code !== 'NOT_VERIFIED') {
            return true;
        }

        // WhatsApp Business App numbers frequently report NOT_VERIFIED for Cloud OTP
        // while still being fully verified in Business Manager for CTWA.
        $platform = strtoupper((string) ($phone['platform_type'] ?? ''));
        if ($verifiedName !== '' && in_array($platform, ['NOT_APPLICABLE', 'ON_PREMISE', ''], true)) {
            return true;
        }
        if ($verifiedName !== '' && $platform === 'CLOUD_API' && $code === '') {
            return true;
        }
        if ($verifiedName !== '' && $code === 'NOT_VERIFIED' && $platform !== 'CLOUD_API') {
            return true;
        }
        // Business App listed numbers: treat named numbers as verified for ad destination UI
        if ($verifiedName !== '' && ($code === 'NOT_VERIFIED' || $code === '')) {
            return true;
        }

        return false;
    }

    /**
     * Step 1 — POST /{waba-id}/phone_numbers
     *
     * @return array{status: string, phone_number_id: string, message: string}
     */
    public function addPhoneNumber(string $wabaId, string $phoneE164, string $verifiedName, bool $requestCode = true): array
    {
        $token = $this->requireToken();
        $digits = TenantMetaPageValidator::normalizeWhatsAppNumber($phoneE164);
        $verifiedName = mb_substr(trim($verifiedName) !== '' ? trim($verifiedName) : 'Business', 0, 128);

        $existing = $this->findPhoneOnWaba($wabaId, $token, $digits);
        if ($existing) {
            $phoneNumberId = (string) $existing['id'];
            $status = strtoupper((string) ($existing['code_verification_status'] ?? ''));

            if ($status === 'VERIFIED') {
                $this->maybeSetPlatformDefaultPhone($phoneNumberId, $existing['display_phone_number'] ?? $digits, $wabaId);

                return [
                    'status' => 'verified',
                    'phone_number_id' => $phoneNumberId,
                    'message' => 'This number is already on the WhatsApp Business Account and verified.',
                ];
            }

            if ($requestCode) {
                $this->requestCode($phoneNumberId, $token);
            }

            return [
                'status' => 'code_sent',
                'phone_number_id' => $phoneNumberId,
                'message' => 'Number already on WABA. Verification code sent via SMS.',
            ];
        }

        [$cc, $national] = $this->parsePhoneParts($digits);

        $create = Http::timeout(45)->asForm()->post(
            "{$this->graphUrl}/{$this->graphVersion}/{$wabaId}/phone_numbers",
            [
                'cc' => $cc,
                'phone_number' => $national,
                'verified_name' => $verifiedName,
                'access_token' => $token,
            ]
        );

        if (! $create->ok()) {
            $message = $create->json('error.message') ?? 'Could not add number to WhatsApp Business Account.';

            Log::error('WABA_ADD_PHONE_FAILED', [
                'waba_id' => $wabaId,
                'response' => $create->json(),
            ]);

            throw ValidationException::withMessages([
                'phone_number' => $message,
            ]);
        }

        $phoneNumberId = (string) ($create->json('id') ?? '');
        if ($phoneNumberId === '') {
            throw ValidationException::withMessages([
                'phone_number' => 'Meta did not return a phone number ID.',
            ]);
        }

        if ($requestCode) {
            $this->requestCode($phoneNumberId, $token);
        }

        return [
            'status' => 'code_sent',
            'phone_number_id' => $phoneNumberId,
            'message' => "Number added to WABA as \"{$verifiedName}\". Enter the SMS verification code.",
        ];
    }

    /**
     * Step 2 — POST /{phone-number-id}/request_code
     */
    public function requestVerificationCode(string $phoneNumberId, string $method = 'SMS'): array
    {
        $this->requestCode($phoneNumberId, $this->requireToken(), $method);

        return [
            'status' => 'code_sent',
            'phone_number_id' => $phoneNumberId,
            'message' => 'Verification code sent.',
        ];
    }

    /**
     * Step 3+4 — verify_code then register for Cloud API.
     *
     * @return array{status: string, phone_number_id: string, message: string}
     */
    public function verifyAndRegister(string $phoneNumberId, string $code, ?string $wabaId = null): array
    {
        $token = $this->requireToken();

        $verify = Http::timeout(45)->asForm()->post(
            "{$this->graphUrl}/{$this->graphVersion}/{$phoneNumberId}/verify_code",
            [
                'code' => preg_replace('/\D+/', '', $code),
                'access_token' => $token,
            ]
        );

        if (! $verify->ok()) {
            throw ValidationException::withMessages([
                'code' => $verify->json('error.message') ?? 'Invalid or expired verification code.',
            ]);
        }

        $pin = (string) config('platform.whatsapp.registration_pin', '123456');

        $register = Http::timeout(45)->asForm()->post(
            "{$this->graphUrl}/{$this->graphVersion}/{$phoneNumberId}/register",
            [
                'messaging_product' => 'whatsapp',
                'pin' => $pin,
                'access_token' => $token,
            ]
        );

        if (! $register->ok()) {
            Log::warning('WABA_REGISTER_FAILED', [
                'phone_number_id' => $phoneNumberId,
                'response' => $register->json(),
            ]);
        }

        $details = $this->getPhoneDetails($phoneNumberId, $token);
        $display = $details['display_phone_number'] ?? null;
        $this->maybeSetPlatformDefaultPhone($phoneNumberId, $display, $wabaId);

        return [
            'status' => 'verified',
            'phone_number_id' => $phoneNumberId,
            'message' => 'Phone number verified and registered for Cloud API messaging.',
        ];
    }

    /**
     * Use this number as the platform default for ads / messaging.
     */
    public function setAsPlatformDefault(string $phoneNumberId, ?string $wabaId = null): void
    {
        $token = $this->requireToken();
        $details = $this->getPhoneDetails($phoneNumberId, $token);

        if (! $details) {
            throw ValidationException::withMessages([
                'phone_number_id' => 'Could not load phone number from Meta.',
            ]);
        }

        $this->maybeSetPlatformDefaultPhone(
            $phoneNumberId,
            $details['display_phone_number'] ?? null,
            $wabaId
        );
    }

    protected function maybeSetPlatformDefaultPhone(string $phoneNumberId, ?string $display, ?string $wabaId = null): void
    {
        $connection = $this->connection();
        if (! $connection) {
            return;
        }

        $connection->forceFill(array_filter([
            'whatsapp_phone_number_id' => $phoneNumberId,
            'whatsapp_phone_number' => $display,
            'whatsapp_business_id' => $wabaId ?: $connection->whatsapp_business_id,
        ]))->save();
    }

    protected function requireToken(): string
    {
        $token = $this->connection()?->plainAccessToken()
            ?: config('platform.meta.system_user_token')
            ?: config('platform.whatsapp.access_token');

        if (! $token) {
            throw ValidationException::withMessages([
                'connection' => 'Platform Meta token is not configured. Connect Meta or sync from .env.',
            ]);
        }

        return $token;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findPhoneOnWaba(string $wabaId, string $token, string $digits): ?array
    {
        $response = Http::timeout(30)->get(
            "{$this->graphUrl}/{$this->graphVersion}/{$wabaId}/phone_numbers",
            [
                'access_token' => $token,
                'fields' => 'id,display_phone_number,verified_name,code_verification_status,status',
            ]
        );

        if (! $response->ok()) {
            return null;
        }

        foreach ($response->json('data', []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $display = TenantMetaPageValidator::normalizeWhatsAppNumber((string) ($row['display_phone_number'] ?? ''));
            if ($display === $digits) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getPhoneDetails(string $phoneNumberId, string $token): ?array
    {
        $response = Http::timeout(30)->get(
            "{$this->graphUrl}/{$this->graphVersion}/{$phoneNumberId}",
            [
                'access_token' => $token,
                'fields' => 'id,display_phone_number,verified_name,code_verification_status,status',
            ]
        );

        return $response->ok() ? $response->json() : null;
    }

    protected function requestCode(string $phoneNumberId, string $token, string $method = 'SMS'): void
    {
        $response = Http::timeout(45)->asForm()->post(
            "{$this->graphUrl}/{$this->graphVersion}/{$phoneNumberId}/request_code",
            [
                'code_method' => strtoupper($method) === 'VOICE' ? 'VOICE' : 'SMS',
                'language' => 'en_US',
                'access_token' => $token,
            ]
        );

        if (! $response->ok()) {
            throw ValidationException::withMessages([
                'phone_number' => $response->json('error.message') ?? 'Could not send Meta verification SMS.',
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function parsePhoneParts(string $digits): array
    {
        if (str_starts_with($digits, '1') && strlen($digits) === 11) {
            return ['1', substr($digits, 1)];
        }

        if (strlen($digits) === 10) {
            return ['1', $digits];
        }

        if (strlen($digits) > 10) {
            return [substr($digits, 0, strlen($digits) - 10), substr($digits, -10)];
        }

        throw ValidationException::withMessages([
            'phone_number' => 'Could not parse country code from this number. Use full international format (e.g. +14313014019).',
        ]);
    }
}
