<?php

namespace App\Services\Meta;

use App\Models\PlatformMetaConnection;
use App\Services\Platform\PlatformBootstrapService;
use App\Services\Tenant\TenantConnectionResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps platform Meta connection + WhatsApp numbers in sync with .env and Graph API.
 * Safe for VPS cron and page-load soft sync (throttled).
 */
class MetaAutoSyncService
{
    public const CACHE_KEY = 'meta_auto_sync_last_at';

    public function __construct(
        protected PlatformBootstrapService $bootstrap,
        protected WhatsAppBusinessAccountService $whatsapp,
        protected InstagramBusinessAccountService $instagram,
        protected TenantConnectionResolver $resolver
    ) {}

    /**
     * @return array{
     *   synced: bool,
     *   skipped: bool,
     *   reason?: string,
     *   connection_id?: int|null,
     *   phone_count?: int,
     *   from_env?: bool,
     *   error?: string|null
     * }
     */
    public function sync(bool $force = false): array
    {
        $ttl = max(30, (int) config('platform.meta_auto_sync_ttl', 120));

        if (! $force && Cache::has(self::CACHE_KEY)) {
            return [
                'synced' => false,
                'skipped' => true,
                'reason' => 'throttled',
                'connection_id' => $this->resolver->forCurrentUser()?->id,
            ];
        }

        $error = null;
        $fromEnv = false;
        $phoneCount = 0;

        try {
            $connection = PlatformMetaConnection::query()->platformDefault()->active()->first();

            if (! $connection || ! $connection->plainAccessToken()) {
                $connection = $this->bootstrap->syncFromEnv();
                $fromEnv = (bool) $connection;
            } elseif (empty($connection->granted_permissions) && config('platform.platform_controls_api', true)) {
                $connection->forceFill([
                    'granted_permissions' => config('services.meta.required_permissions', []),
                ])->saveQuietly();
            }

            if (! $connection) {
                Cache::put(self::CACHE_KEY, now()->timestamp, $ttl);

                return [
                    'synced' => false,
                    'skipped' => false,
                    'reason' => 'no_connection',
                    'error' => 'No platform Meta connection. Set META_SYSTEM_USER_TOKEN / WHATSAPP_* in .env.',
                ];
            }

            // Refresh token from .env when platform controls API (VPS deploy updates .env often)
            if (config('platform.platform_controls_api', true)) {
                $envToken = config('platform.meta.system_user_token')
                    ?: config('platform.whatsapp.access_token')
                    ?: config('services.meta.token');

                if ($envToken && $connection->plainAccessToken() !== $envToken) {
                    $connection->storeAccessToken($envToken);
                    $fromEnv = true;
                }

                // Keep core IDs aligned with .env when present
                $updates = array_filter([
                    'ad_account_id' => ltrim((string) (config('platform.meta.ad_account_id') ?: ''), 'act_') ?: null,
                    'page_id' => config('platform.meta.page_id') ?: null,
                    'page_name' => config('platform.meta.page_name') ?: null,
                    'instagram_business_account_id' => config('platform.meta.instagram_user_id') ?: null,
                    'whatsapp_business_id' => config('platform.whatsapp.business_id') ?: null,
                    // Prefer explicit Meta Business Manager id — never use WABA id here
                    'business_id' => config('platform.meta.business_id') ?: null,
                ], fn ($v) => $v !== null && $v !== '');

                if ($updates !== []) {
                    $connection->forceFill($updates)->saveQuietly();
                }
            }

            $phoneCount = $this->syncWhatsAppNumbers($connection);
            $igCount = $this->syncInstagramAccounts($connection);
            $this->cacheWabaDirectory($connection);
            $connection = $connection->fresh();

            Cache::put(self::CACHE_KEY, now()->timestamp, $ttl);

            Log::info('META_AUTO_SYNC_OK', [
                'connection_id' => $connection?->id,
                'phone_count' => $phoneCount,
                'instagram_count' => $igCount,
                'from_env' => $fromEnv,
                'force' => $force,
            ]);

            return [
                'synced' => true,
                'skipped' => false,
                'connection_id' => $connection?->id,
                'phone_count' => $phoneCount,
                'instagram_count' => $igCount,
                'from_env' => $fromEnv,
                'error' => null,
            ];
        } catch (Throwable $e) {
            $error = $e->getMessage();
            Log::warning('META_AUTO_SYNC_FAILED', ['error' => $error]);
            Cache::put(self::CACHE_KEY, now()->timestamp, min(60, $ttl));

            return [
                'synced' => false,
                'skipped' => false,
                'error' => $error,
            ];
        }
    }

    /**
     * Always pull WABAs + phones from Meta (bypasses page-load throttle).
     * Used by WhatsApp BM and Ad Studio WhatsApp step.
     *
     * @return array<string, mixed>
     */
    public function syncAlways(): array
    {
        Cache::forget(self::CACHE_KEY);
        $connection = $this->resolver->forCurrentUser()
            ?? PlatformMetaConnection::query()->platformDefault()->active()->first();
        if ($connection) {
            // Never wipe phone directory here — Meta rate limits often return empty and would lose numbers.
            Cache::forget('meta_ig_directory_'.$connection->id);
            Cache::forget('meta_waba_directory_'.$connection->id);
            Cache::forget('meta_bm_synced_at_'.$connection->id);
            Cache::forget('meta_ig_synced_at_'.$connection->id);
        }
        Cache::forget('meta_ig_directory_platform');
        Cache::forget('meta_waba_directory_platform');

        return $this->sync(true);
    }

    /**
     * Pull BM / ad-account Instagram accounts and persist for Ad Studio.
     */
    protected function syncInstagramAccounts(PlatformMetaConnection $connection): int
    {
        try {
            $result = $this->instagram->syncToConnection($connection);
            $accounts = $result['accounts'] ?? [];
            Cache::put(
                'meta_ig_directory_'.($connection->id ?? 'platform'),
                $accounts,
                now()->addMinutes(30)
            );
            Cache::put(
                'meta_ig_synced_at_'.($connection->id ?? 'platform'),
                now()->toDateTimeString(),
                now()->addMinutes(30)
            );

            return count($accounts);
        } catch (Throwable $e) {
            Log::warning('META_AUTO_SYNC_IG_FAILED', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    protected function cacheWabaDirectory(PlatformMetaConnection $connection): void
    {
        try {
            $result = $this->whatsapp->syncToConnection($connection);
            $accounts = $result['accounts'] ?? [];
            $key = 'meta_waba_directory_'.($connection->id ?? 'platform');
            $prev = Cache::get($key);

            if (is_array($prev) && $prev !== []) {
                $accounts = $this->whatsapp->mergePreferringRealNames($prev, $accounts);
            }

            // Rate-limit / Graph errors must not shrink a previously good directory to 1 env WABA
            if (
                is_array($prev)
                && count($prev) > count($accounts)
                && ! empty($result['incomplete'])
            ) {
                Log::warning('META_AUTO_SYNC_WABA_CACHE_PRESERVED', [
                    'prev' => count($prev),
                    'new' => count($accounts),
                ]);
                Cache::put(
                    'meta_bm_synced_at_'.($connection->id ?? 'platform'),
                    now()->toDateTimeString(),
                    now()->addMinutes(30)
                );

                return;
            }

            if ($accounts === [] && is_array($prev) && $prev !== []) {
                return;
            }

            Cache::put($key, $accounts, now()->addMinutes(30));
            Cache::put(
                'meta_bm_synced_at_'.($connection->id ?? 'platform'),
                now()->toDateTimeString(),
                now()->addMinutes(30)
            );
        } catch (Throwable $e) {
            Log::warning('META_AUTO_SYNC_WABA_CACHE_FAILED', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Pull WABA phone numbers from Meta and keep platform default phone fresh.
     */
    protected function syncWhatsAppNumbers(PlatformMetaConnection $connection): int
    {
        $phones = [];

        // Back off while Meta is rate-limiting WhatsApp Graph calls
        if (Cache::get('meta_wa_rate_limited')) {
            $cached = Cache::get('meta_wa_phone_directory_'.($connection->id ?? 'platform'));

            return is_array($cached) ? count($cached) : 0;
        }

        try {
            $phones = $this->whatsapp->listAllPhoneNumbers();
        } catch (Throwable $e) {
            // Fall back to single WABA id on connection
            $wabaId = $connection->whatsapp_business_id ?: config('platform.whatsapp.business_id');
            if ($wabaId) {
                try {
                    $phones = array_map(
                        fn (array $p) => $p + ['waba_id' => (string) $wabaId],
                        $this->whatsapp->listPhoneNumbers((string) $wabaId)
                    );
                } catch (Throwable $inner) {
                    Log::warning('META_AUTO_SYNC_PHONES_FAILED', ['error' => $inner->getMessage()]);

                    return 0;
                }
            } else {
                Log::warning('META_AUTO_SYNC_PHONES_FAILED', ['error' => $e->getMessage()]);

                return 0;
            }
        }

        if ($phones === []) {
            return is_array($prevPhones = Cache::get('meta_wa_phone_directory_'.($connection->id ?? 'platform')))
                ? count($prevPhones)
                : 0;
        }

        $phoneKey = 'meta_wa_phone_directory_'.($connection->id ?? 'platform');
        $prevPhones = Cache::get($phoneKey);
        $merged = $this->whatsapp->mergePhoneDirectories(
            is_array($prevPhones) ? $prevPhones : [],
            $phones
        );

        // Don't shrink a full multi-WABA phone list with a rate-limited partial pull
        if (is_array($prevPhones) && count($prevPhones) > count($merged) && count($phones) <= 2) {
            Log::warning('META_AUTO_SYNC_PHONES_PRESERVED', [
                'prev' => count($prevPhones),
                'new' => count($phones),
                'merged' => count($merged),
            ]);
            $merged = $prevPhones;
        }

        Cache::put($phoneKey, $merged, now()->addMinutes(30));
        $phones = $merged;

        // Persist discovered Business Manager id when missing/wrong
        try {
            $this->whatsapp->resolveBusinessManagerId($connection);
        } catch (Throwable) {
            // ignore
        }

        $preferredId = (string) (
            config('platform.whatsapp.phone_number_id')
            ?: $connection->whatsapp_phone_number_id
            ?: ''
        );

        $chosen = null;
        foreach ($phones as $phone) {
            if ($preferredId !== '' && (string) ($phone['id'] ?? '') === $preferredId) {
                $chosen = $phone;
                break;
            }
        }

        if (! $chosen) {
            foreach ($phones as $phone) {
                if (strtoupper((string) ($phone['code_verification_status'] ?? '')) === 'VERIFIED') {
                    $chosen = $phone;
                    break;
                }
            }
        }

        $chosen = $chosen ?: $phones[0];

        $connection->forceFill(array_filter([
            'whatsapp_phone_number_id' => (string) ($chosen['id'] ?? ''),
            'whatsapp_phone_number' => $chosen['display_phone_number'] ?? null,
            'whatsapp_business_id' => $chosen['waba_id'] ?? $connection->whatsapp_business_id,
        ]))->saveQuietly();

        return count($phones);
    }
}
