<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Meta\MetaAutoSyncService;
use App\Services\Meta\WhatsAppBusinessAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class WhatsAppAccountsController extends Controller
{
    public function __construct(
        protected WhatsAppBusinessAccountService $whatsapp,
        protected MetaAutoSyncService $autoSync
    ) {}

    public function index(Request $request): View
    {
        // Always instant from cache/DB — never block Business Manager navigation on Meta Graph.
        $connection = $this->whatsapp->connection();
        $cacheSuffix = (string) ($connection?->id ?? 'platform');
        $wabaCacheKey = 'meta_waba_directory_'.$cacheSuffix;
        $phoneCacheKey = 'meta_wa_phone_directory_'.$cacheSuffix;
        $syncedAtKey = 'meta_bm_synced_at_'.$cacheSuffix;

        $error = null;
        $accounts = [];
        $fromCache = false;

        $cached = Cache::get($wabaCacheKey);
        if (is_array($cached) && $cached !== []) {
            $accounts = $cached;
            $fromCache = true;
        } else {
            // Durable local directory first (DB), then id seeds
            $dbAccounts = (array) ($connection?->linked_waba_directory ?? []);
            if ($dbAccounts !== []) {
                $accounts = $this->whatsapp->mergePreferringRealNames(
                    $this->seedWabasFromConnection($connection),
                    $dbAccounts
                );
                Cache::put($wabaCacheKey, $accounts, now()->addMinutes(30));
                $fromCache = true;
            } else {
                $accounts = $this->seedWabasFromConnection($connection);
            }
        }

        // Warm phone cache from local DB when Redis/file cache is empty
        if (! is_array(Cache::get($phoneCacheKey)) || Cache::get($phoneCacheKey) === []) {
            $dbPhones = $this->whatsapp->loadPhoneDirectory($connection);
            if ($dbPhones !== []) {
                Cache::put($phoneCacheKey, $dbPhones, now()->addMinutes(30));
            }
        }

        $needsNames = $this->accountsNeedNameRefresh($accounts);
        $force = $request->boolean('force_sync');
        if ($force || $needsNames || $this->shouldBackgroundSync($accounts, $syncedAtKey)) {
            // Placeholder names / stale cache: refresh in background after HTML is sent.
            $this->queueBackgroundSync($cacheSuffix, $wabaCacheKey, $syncedAtKey, $force || $needsNames);
        }

        $selectedId = (string) ($request->query('waba') ?: ($connection?->whatsapp_business_id ?? ($accounts[0]['id'] ?? '')));
        $selected = collect($accounts)->firstWhere('id', $selectedId);
        $detail = $selected ?: ($selectedId !== '' ? [
            'id' => $selectedId,
            'name' => $connection?->business_name ?? 'WhatsApp Business Account',
        ] : null);
        $phones = [];

        if ($selectedId !== '') {
            $cachedPhones = Cache::get($phoneCacheKey);
            if (is_array($cachedPhones)) {
                $phones = array_values(array_filter($cachedPhones, function ($p) use ($selectedId) {
                    return is_array($p) && (string) ($p['waba_id'] ?? '') === $selectedId;
                }));
            }

            // One quick Graph call for the open WABA when local directory has no numbers yet
            // (covers Business App accounts like +1 450-367-5329 on 731320686199458)
            if ($phones === [] && ! Cache::get('meta_wa_rate_limited')) {
                try {
                    $phones = $this->whatsapp->syncPhonesForWaba($selectedId, $connection);
                } catch (\Throwable) {
                    $phones = [];
                }
            }
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $accounts = array_values(array_filter($accounts, function ($a) use ($search) {
                return str_contains(strtolower(($a['name'] ?? '').' '.($a['id'] ?? '')), strtolower($search));
            }));
        }

        return view('admin.meta.whatsapp.index', [
            'connection' => $connection,
            'accounts' => $accounts,
            'selectedId' => $selectedId,
            'detail' => $detail,
            'phones' => $phones,
            'search' => $search,
            'error' => $error,
            'pendingPhoneId' => old('phone_number_id', session('pending_phone_number_id')),
            'lastSyncedAt' => Cache::get($syncedAtKey) ?: ($fromCache ? 'cached' : null),
            'needsSync' => ! $fromCache && $accounts === [],
            'syncingNames' => $needsNames,
        ]);
    }

    /**
     * Explicit Meta sync (also runs automatically on open when names are placeholders).
     */
    public function syncNow(Request $request): RedirectResponse
    {
        $waba = (string) $request->input('waba', '');

        try {
            $synced = $this->runFullDirectorySync($waba, true);
            $accounts = $synced['accounts'];
            $incomplete = $synced['incomplete'];
            $selectedId = $synced['selected_id'];

            $msg = count($accounts).' WhatsApp account(s) synced from Meta.';
            $phoneCount = count($this->whatsapp->loadPhoneDirectory($this->whatsapp->connection()));
            $msg .= ' '.$phoneCount.' phone number(s) saved locally.';
            if ($incomplete && $this->accountsNeedNameRefresh($accounts)) {
                $msg .= ' Some names are still loading — Meta rate-limited individual lookups; tap Sync now again in a few minutes.';
            } elseif ($incomplete) {
                $msg .= ' Directory preserved through a partial Meta response.';
            }

            return redirect()
                ->route('admin.meta.whatsapp.index', array_filter([
                    'waba' => $selectedId ?: null,
                    'tab' => 'phones',
                ]))
                ->with('success', $msg);
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.meta.whatsapp.index')
                ->with('error', collect($e->errors())->flatten()->first());
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.meta.whatsapp.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $accounts
     */
    protected function accountsNeedNameRefresh(array $accounts): bool
    {
        if ($accounts === []) {
            return true;
        }

        foreach ($accounts as $row) {
            if (! is_array($row)) {
                return true;
            }
            $id = (string) ($row['id'] ?? '');
            $name = trim((string) ($row['name'] ?? ''));
            if ($id === '' || $name === '') {
                return true;
            }
            if ($name === $id || str_starts_with($name, 'WhatsApp Business Account')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $accounts
     */
    protected function shouldBackgroundSync(array $accounts, string $syncedAtKey): bool
    {
        if ($this->accountsNeedNameRefresh($accounts)) {
            return true;
        }

        $syncedAt = Cache::get($syncedAtKey);
        if (! $syncedAt || $syncedAt === 'cached') {
            return true;
        }

        try {
            return \Carbon\Carbon::parse((string) $syncedAt)->lt(now()->subMinutes(15));
        } catch (\Throwable) {
            return true;
        }
    }

    protected function queueBackgroundSync(string $cacheSuffix, string $wabaCacheKey, string $syncedAtKey, bool $force = false): void
    {
        // Don't pile Graph calls while Meta is rate-limiting WhatsApp / BM endpoints.
        if (! $force && Cache::get('meta_wa_rate_limited')) {
            return;
        }

        $lockKey = 'meta_wa_bg_sync_'.$cacheSuffix;
        if (! Cache::add($lockKey, 1, now()->addMinutes(2))) {
            return;
        }

        dispatch(function () use ($cacheSuffix, $lockKey, $wabaCacheKey, $syncedAtKey, $force) {
            try {
                if (Cache::get('meta_wa_rate_limited') && ! $force) {
                    return;
                }

                $wa = app(WhatsAppBusinessAccountService::class);
                $connection = $wa->connection();

                // Phones before names — never wipe; force refreshes every WABA id we know
                $wa->syncAllPhonesFromMeta($connection, $force);

                $connection = $wa->connection();
                $wa->resolveBusinessManagerId($connection);
                $result = $wa->syncToConnection($connection);
                $accounts = $result['accounts'] ?? [];
                $prev = Cache::get($wabaCacheKey);
                if (is_array($prev) && $prev !== []) {
                    $accounts = $wa->mergePreferringRealNames($prev, $accounts);
                }
                $accounts = $wa->mergePreferringRealNames(
                    (array) ($connection?->linked_waba_directory ?? []),
                    $accounts
                );
                if (! Cache::get('meta_wa_rate_limited')) {
                    $accounts = $wa->enrichPlaceholderNames($accounts);
                }
                if ($accounts !== [] || ! is_array($prev) || $prev === []) {
                    Cache::put($wabaCacheKey, $accounts, now()->addMinutes(30));
                }
                $wa->persistWabaDirectory($connection, $accounts);
                Cache::put($syncedAtKey, now()->toDateTimeString(), now()->addMinutes(30));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('WA_BACKGROUND_SYNC_FAILED', ['error' => $e->getMessage()]);
            } finally {
                Cache::forget($lockKey);
            }
        })->afterResponse();
    }

    /**
     * Full Meta directory sync used by Sync now and auto-open name refresh.
     * Production rule: phones for every known WABA before name enrichment (rate-limit order).
     *
     * @return array{
     *   connection: mixed,
     *   accounts: array<int, array<string, mixed>>,
     *   incomplete: bool,
     *   selected_id: string
     * }
     */
    protected function runFullDirectorySync(string $waba = '', bool $force = true): array
    {
        $connection = $this->whatsapp->connection();
        $cacheSuffix = (string) ($connection?->id ?? 'platform');
        $phoneSnapshot = $this->whatsapp->loadPhoneDirectory($connection);

        if ($force) {
            Cache::forget('meta_auto_sync_last_at');
            Cache::forget('meta_waba_directory_'.$cacheSuffix);
            Cache::forget('meta_bm_synced_at_'.$cacheSuffix);
            Cache::forget('meta_wa_rate_limited');
        }

        // 1) Phones first — covers every linked/DB/Graph WABA (e.g. +1 450-367-5329 on 731320686199458)
        $phoneSync = $this->whatsapp->syncAllPhonesFromMeta($connection, $force);
        $mergedPhones = $phoneSync['phones'] !== [] ? $phoneSync['phones'] : $phoneSnapshot;

        // 2) Names second (do not call MetaAutoSync::syncAlways — that would re-hit phone endpoints)
        $connection = $this->whatsapp->connection();
        $cacheSuffix = (string) ($connection?->id ?? 'platform');
        $this->whatsapp->resolveBusinessManagerId($connection);
        $result = $this->whatsapp->syncToConnection($connection);
        $accounts = $result['accounts'] ?? [];
        $incomplete = ! empty($result['incomplete']);

        $cacheKey = 'meta_waba_directory_'.$cacheSuffix;
        $prev = Cache::get($cacheKey);
        if (is_array($prev) && $prev !== []) {
            $accounts = $this->whatsapp->mergePreferringRealNames($prev, $accounts);
        }
        $accounts = $this->whatsapp->mergePreferringRealNames(
            (array) ($connection?->linked_waba_directory ?? []),
            $accounts
        );
        if (! Cache::get('meta_wa_rate_limited')) {
            $accounts = $this->whatsapp->enrichPlaceholderNames($accounts);
        }
        Cache::put($cacheKey, $accounts, now()->addMinutes(30));
        Cache::put('meta_bm_synced_at_'.$cacheSuffix, now()->toDateTimeString(), now()->addMinutes(30));

        $selectedId = $waba !== '' ? $waba : (string) ($connection?->whatsapp_business_id ?? ($accounts[0]['id'] ?? ''));
        if ($selectedId !== '') {
            try {
                $detail = $this->whatsapp->getWaba($selectedId);
                if (is_array($detail) && ! empty($detail['name'])) {
                    foreach ($accounts as &$row) {
                        if ((string) ($row['id'] ?? '') === $selectedId) {
                            $row = array_merge($row, $detail);
                            $row['name'] = $this->whatsapp->bestWabaDisplayName($row, $selectedId);
                        }
                    }
                    unset($row);
                    Cache::put($cacheKey, $accounts, now()->addMinutes(30));
                }
                $forSelected = $this->whatsapp->syncPhonesForWaba($selectedId, $connection);
                if ($forSelected !== []) {
                    $mergedPhones = $this->whatsapp->loadPhoneDirectory($connection);
                }
            } catch (\Throwable) {
                // keep snapshot
            }
        }

        if ($mergedPhones !== []) {
            $this->whatsapp->persistPhoneDirectory($connection, $mergedPhones);
        } elseif ($phoneSnapshot !== []) {
            $this->whatsapp->persistPhoneDirectory($connection, $phoneSnapshot);
        }

        $this->whatsapp->persistWabaDirectory($connection, $accounts);

        return [
            'connection' => $connection,
            'accounts' => $accounts,
            'incomplete' => $incomplete || $this->accountsNeedNameRefresh($accounts),
            'selected_id' => $selectedId,
        ];
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    protected function seedWabasFromConnection($connection): array
    {
        return $this->whatsapp->seedDirectoryFromConnection($connection);
    }

    public function linkWaba(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'waba_id' => 'required|string|max:64',
        ]);

        try {
            $this->autoSync->syncAlways();
            $result = $this->whatsapp->importExistingWaba($data['waba_id']);
            $this->autoSync->syncAlways();

            return redirect()
                ->route('admin.meta.whatsapp.index', [
                    'waba' => $result['waba']['id'] ?? $data['waba_id'],
                    'tab' => 'phones',
                ])
                ->with('success', $result['message']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput()->with('show_link_waba', true);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput()->with('show_link_waba', true);
        }
    }

    /**
     * Meta-style link step 1: phone number → send verification (or skip to businesses).
     */
    public function linkByPhoneStart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country_code' => 'required|string|max:8',
            'phone_number' => 'required|string|max:32',
        ]);

        try {
            $this->autoSync->sync(false);
            $result = $this->whatsapp->startLinkByPhone($data['country_code'], $data['phone_number']);

            return response()->json(['ok' => true] + $result);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'errors' => $e->errors(),
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Meta-style link step 2: enter 5-digit code.
     */
    public function linkByPhoneVerify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_number_id' => 'required|string|max:64',
            'code' => 'required|string|max:12',
            'waba_id' => 'nullable|string|max:64',
        ]);

        try {
            $result = $this->whatsapp->verifyLinkByPhone(
                $data['phone_number_id'],
                $data['code'],
                $data['waba_id'] ?? null
            );

            return response()->json(['ok' => true] + $result);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'errors' => $e->errors(),
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function linkByPhoneResend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_number_id' => 'required|string|max:64',
        ]);

        try {
            $this->whatsapp->requestVerificationCode($data['phone_number_id'], 'SMS');

            return response()->json([
                'ok' => true,
                'message' => 'A new verification code was sent.',
                'resend_after' => 30,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Meta-style link step 3: pick associated business / WABA.
     */
    public function linkByPhoneComplete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'waba_id' => 'required|string|max:64',
            'phone_number_id' => 'required|string|max:64',
        ]);

        try {
            $result = $this->whatsapp->completeLinkByPhone($data['waba_id'], $data['phone_number_id']);
            $this->autoSync->syncAlways();

            return response()->json([
                'ok' => true,
                'message' => $result['message'],
                'redirect' => route('admin.meta.whatsapp.index', [
                    'waba' => $result['waba']['id'] ?? $data['waba_id'],
                    'tab' => 'phones',
                    'force_sync' => 1,
                ]),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function createWaba(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'waba_name' => 'required|string|max:128',
            'currency' => 'nullable|string|size:3',
        ]);

        try {
            $result = $this->whatsapp->createOwnedWaba(
                $data['waba_name'],
                strtoupper($data['currency'] ?? 'CAD')
            );
            $this->autoSync->syncAlways();

            return redirect()
                ->route('admin.meta.whatsapp.index', [
                    'waba' => $result['waba']['id'] ?? null,
                    'tab' => 'phones',
                ])
                ->with('success', $result['message']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput()->with('show_create_waba', true);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput()->with('show_create_waba', true);
        }
    }

    public function requestClientWaba(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_business_id' => 'required|string|max:64',
            'waba_name' => 'required|string|max:128',
        ]);

        try {
            $result = $this->whatsapp->requestClientWaba(
                $data['client_business_id'],
                $data['waba_name']
            );
            $this->autoSync->syncAlways();

            return redirect()
                ->route('admin.meta.whatsapp.index', ['tab' => 'phones'])
                ->with('success', $result['message']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput()->with('show_request_waba', true);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput()->with('show_request_waba', true);
        }
    }

    public function addPhone(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'waba_id' => 'required|string',
            'phone_number' => 'required|string|max:32',
            'verified_name' => 'required|string|max:128',
        ]);

        try {
            $result = $this->whatsapp->addPhoneNumber(
                $data['waba_id'],
                $data['phone_number'],
                $data['verified_name'],
                true
            );

            return redirect()
                ->route('admin.meta.whatsapp.index', ['waba' => $data['waba_id'], 'tab' => 'phones'])
                ->with('success', $result['message'])
                ->with('pending_phone_number_id', $result['phone_number_id']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function resendCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'waba_id' => 'required|string',
            'phone_number_id' => 'required|string',
            'code_method' => 'nullable|in:SMS,VOICE',
        ]);

        try {
            $result = $this->whatsapp->requestVerificationCode(
                $data['phone_number_id'],
                $data['code_method'] ?? 'SMS'
            );

            return redirect()
                ->route('admin.meta.whatsapp.index', ['waba' => $data['waba_id'], 'tab' => 'phones'])
                ->with('success', $result['message'])
                ->with('pending_phone_number_id', $data['phone_number_id']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function verifyPhone(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'waba_id' => 'required|string',
            'phone_number_id' => 'required|string',
            'code' => 'required|string|min:4|max:12',
        ]);

        try {
            $result = $this->whatsapp->verifyAndRegister(
                $data['phone_number_id'],
                $data['code'],
                $data['waba_id']
            );

            return redirect()
                ->route('admin.meta.whatsapp.index', ['waba' => $data['waba_id'], 'tab' => 'phones'])
                ->with('success', $result['message']);
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('pending_phone_number_id', $data['phone_number_id']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function setDefault(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'waba_id' => 'required|string',
            'phone_number_id' => 'required|string',
        ]);

        try {
            $this->whatsapp->setAsPlatformDefault($data['phone_number_id'], $data['waba_id']);

            return redirect()
                ->route('admin.meta.whatsapp.index', ['waba' => $data['waba_id'], 'tab' => 'phones'])
                ->with('success', 'Platform default WhatsApp number updated. Ads will deliver to this number.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
