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
            $accounts = $this->seedWabasFromConnection($connection);
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

                $auto = app(MetaAutoSyncService::class);
                if ($force) {
                    $auto->syncAlways();
                } else {
                    $auto->sync(false);
                }

                $wa = app(WhatsAppBusinessAccountService::class);
                $connection = $wa->connection();
                $wa->resolveBusinessManagerId($connection);
                $result = $wa->syncToConnection($connection);
                $accounts = $result['accounts'] ?? [];
                $prev = Cache::get($wabaCacheKey);
                if (is_array($prev) && $prev !== []) {
                    $accounts = $wa->mergePreferringRealNames($prev, $accounts);
                }
                $accounts = $wa->enrichPlaceholderNames($accounts);
                if ($accounts !== [] || ! is_array($prev) || $prev === []) {
                    Cache::put($wabaCacheKey, $accounts, now()->addMinutes(30));
                }
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
        $phoneCacheKey = 'meta_wa_phone_directory_'.$cacheSuffix;
        // Snapshot phones BEFORE any sync so rate-limited pulls cannot erase them.
        $phoneSnapshot = Cache::get($phoneCacheKey);
        $phoneSnapshot = is_array($phoneSnapshot) ? $phoneSnapshot : [];

        if ($force) {
            $this->autoSync->syncAlways();
        } else {
            $this->autoSync->sync(false);
        }

        $connection = $this->whatsapp->connection();
        $cacheSuffix = (string) ($connection?->id ?? 'platform');
        $this->whatsapp->resolveBusinessManagerId($connection);
        $result = $this->whatsapp->syncToConnection($connection);
        $accounts = $result['accounts'] ?? [];
        $incomplete = ! empty($result['incomplete']);

        $cacheKey = 'meta_waba_directory_'.$cacheSuffix;
        $prev = Cache::get($cacheKey);
        if (is_array($prev) && $prev !== []) {
            // Always keep the best known real name per WABA id across partial / rate-limited pulls.
            $accounts = $this->whatsapp->mergePreferringRealNames($prev, $accounts);
        }
        $accounts = $this->whatsapp->enrichPlaceholderNames($accounts);
        Cache::put($cacheKey, $accounts, now()->addMinutes(30));
        Cache::put('meta_bm_synced_at_'.$cacheSuffix, now()->toDateTimeString(), now()->addMinutes(30));

        // Restore/merge phones — never publish an empty directory over a known good snapshot.
        $phoneCacheKey = 'meta_wa_phone_directory_'.$cacheSuffix;
        $currentPhones = Cache::get($phoneCacheKey);
        $currentPhones = is_array($currentPhones) ? $currentPhones : [];
        $mergedPhones = $this->whatsapp->mergePhoneDirectories($phoneSnapshot, $currentPhones);

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
                $fetched = $this->whatsapp->listPhoneNumbers($selectedId);
                if ($fetched !== []) {
                    $mergedPhones = $this->whatsapp->upsertPhonesForWaba(
                        $mergedPhones,
                        $selectedId,
                        $fetched,
                        $detail['name'] ?? null
                    );
                }
            } catch (\Throwable) {
                // keep snapshot
            }
        }

        // Best-effort: fill other WABA phone lists without wiping on empty responses
        if (! Cache::get('meta_wa_rate_limited')) {
            foreach ($accounts as $account) {
                $id = (string) ($account['id'] ?? '');
                if ($id === '' || $id === $selectedId) {
                    continue;
                }
                $hasPhones = collect($mergedPhones)->contains(
                    fn ($p) => is_array($p) && (string) ($p['waba_id'] ?? '') === $id
                );
                if ($hasPhones) {
                    continue;
                }
                try {
                    $fetched = $this->whatsapp->listPhoneNumbers($id);
                    if ($fetched !== []) {
                        $mergedPhones = $this->whatsapp->upsertPhonesForWaba(
                            $mergedPhones,
                            $id,
                            $fetched,
                            $account['name'] ?? null
                        );
                    }
                } catch (\Throwable) {
                    break;
                }
                if (Cache::get('meta_wa_rate_limited')) {
                    break;
                }
            }
        }

        if ($mergedPhones !== []) {
            Cache::put($phoneCacheKey, $mergedPhones, now()->addMinutes(30));
        } elseif ($phoneSnapshot !== []) {
            Cache::put($phoneCacheKey, $phoneSnapshot, now()->addMinutes(30));
        }

        $stillPlaceholders = $this->accountsNeedNameRefresh($accounts);
        if ($incomplete && $stillPlaceholders) {
            $incomplete = true;
        }

        return [
            'connection' => $connection,
            'accounts' => $accounts,
            'incomplete' => $incomplete,
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
