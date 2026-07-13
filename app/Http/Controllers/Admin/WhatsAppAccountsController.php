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
        // Instant from cache when names are real; auto Meta sync on open when placeholders / empty.
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

        // Placeholder seeds like "WhatsApp Business Account 8668…" must refresh on open (no Sync click).
        if ($this->accountsNeedNameRefresh($accounts) || $request->boolean('force_sync')) {
            try {
                $synced = $this->runFullDirectorySync(
                    (string) ($request->query('waba') ?: ''),
                    true
                );
                $connection = $synced['connection'];
                $accounts = $synced['accounts'];
                $fromCache = false;
            } catch (ValidationException $e) {
                $error = collect($e->errors())->flatten()->first();
                $this->queueBackgroundSync($cacheSuffix, $wabaCacheKey, $syncedAtKey);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                \Illuminate\Support\Facades\Log::warning('WA_OPEN_SYNC_FAILED', ['error' => $error]);
                $this->queueBackgroundSync($cacheSuffix, $wabaCacheKey, $syncedAtKey);
            }
        } elseif ($this->shouldBackgroundSync($accounts, $syncedAtKey)) {
            $this->queueBackgroundSync($cacheSuffix, $wabaCacheKey, $syncedAtKey);
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
            if ($incomplete) {
                $msg .= ' (Meta rate-limited — kept previously linked accounts; try again in a few minutes for fresh names.)';
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

    protected function queueBackgroundSync(string $cacheSuffix, string $wabaCacheKey, string $syncedAtKey): void
    {
        $lockKey = 'meta_wa_bg_sync_'.$cacheSuffix;
        if (! Cache::add($lockKey, 1, now()->addMinutes(2))) {
            return;
        }

        dispatch(function () use ($cacheSuffix, $lockKey, $wabaCacheKey, $syncedAtKey) {
            try {
                app(MetaAutoSyncService::class)->sync(false);
                $wa = app(WhatsAppBusinessAccountService::class);
                $connection = $wa->connection();
                $wa->resolveBusinessManagerId($connection);
                $result = $wa->syncToConnection($connection);
                $accounts = $result['accounts'] ?? [];
                $prev = Cache::get($wabaCacheKey);
                if (
                    is_array($prev)
                    && count($prev) > count($accounts)
                    && ! empty($result['incomplete'])
                ) {
                    // Keep wider directory on rate-limit, but still refresh names when prev is placeholders.
                    $prevNeedsNames = false;
                    foreach ($prev as $row) {
                        if (! is_array($row)) {
                            $prevNeedsNames = true;
                            break;
                        }
                        $name = trim((string) ($row['name'] ?? ''));
                        $id = (string) ($row['id'] ?? '');
                        if ($name === '' || $name === $id || str_starts_with($name, 'WhatsApp Business Account')) {
                            $prevNeedsNames = true;
                            break;
                        }
                    }
                    if (! $prevNeedsNames) {
                        Cache::put($syncedAtKey, now()->toDateTimeString(), now()->addMinutes(30));

                        return;
                    }
                }
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
        if (
            is_array($prev)
            && count($prev) > count($accounts)
            && $incomplete
            && ! $this->accountsNeedNameRefresh($prev)
        ) {
            $accounts = $prev;
        } else {
            Cache::put($cacheKey, $accounts, now()->addMinutes(30));
        }
        Cache::put('meta_bm_synced_at_'.$cacheSuffix, now()->toDateTimeString(), now()->addMinutes(30));

        $selectedId = $waba !== '' ? $waba : (string) ($connection?->whatsapp_business_id ?? ($accounts[0]['id'] ?? ''));
        if ($selectedId !== '') {
            try {
                $detail = $this->whatsapp->getWaba($selectedId);
                $phones = $this->whatsapp->listPhoneNumbers($selectedId);
                $allPhones = [];
                foreach ($phones as $phone) {
                    $allPhones[] = array_merge($phone, [
                        'waba_id' => $selectedId,
                        'waba_name' => $detail['name'] ?? null,
                    ]);
                }
                $prevPhones = Cache::get('meta_wa_phone_directory_'.$cacheSuffix);
                if (is_array($prevPhones)) {
                    foreach ($prevPhones as $p) {
                        if (is_array($p) && (string) ($p['waba_id'] ?? '') !== $selectedId) {
                            $allPhones[] = $p;
                        }
                    }
                }
                Cache::put('meta_wa_phone_directory_'.$cacheSuffix, $allPhones, now()->addMinutes(30));
            } catch (\Throwable) {
                // list still cached
            }
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
