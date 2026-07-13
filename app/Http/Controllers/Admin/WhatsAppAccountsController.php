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
        // Instant: cache/DB only. Meta Graph syncs after response when stale.
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

        $syncedAt = Cache::get($syncedAtKey);
        $stale = ! $syncedAt || $syncedAt === 'cached' || $accounts === [];
        if (! $stale) {
            try {
                $stale = \Carbon\Carbon::parse((string) $syncedAt)->lt(now()->subMinutes(15));
            } catch (\Throwable) {
                $stale = true;
            }
        }
        if ($stale) {
            $lockKey = 'meta_wa_bg_sync_'.$cacheSuffix;
            if (Cache::add($lockKey, 1, now()->addMinutes(2))) {
                dispatch(function () use ($cacheSuffix, $lockKey, $wabaCacheKey, $syncedAtKey) {
                    try {
                        app(MetaAutoSyncService::class)->sync(false);
                        $wa = app(WhatsAppBusinessAccountService::class);
                        $connection = $wa->connection();
                        $wa->resolveBusinessManagerId($connection);
                        $accounts = $wa->listWabas();
                        Cache::put($wabaCacheKey, $accounts, now()->addMinutes(30));
                        Cache::put($syncedAtKey, now()->toDateTimeString(), now()->addMinutes(30));
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('WA_BACKGROUND_SYNC_FAILED', ['error' => $e->getMessage()]);
                    } finally {
                        Cache::forget($lockKey);
                    }
                })->afterResponse();
            }
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
     * Explicit Meta sync (never runs on menu navigation).
     */
    public function syncNow(Request $request): RedirectResponse
    {
        $waba = (string) $request->input('waba', '');
        $connection = $this->whatsapp->connection();
        $cacheSuffix = (string) ($connection?->id ?? 'platform');

        try {
            $this->autoSync->syncAlways();
            $connection = $this->whatsapp->connection();
            $this->whatsapp->resolveBusinessManagerId($connection);
            $accounts = $this->whatsapp->listWabas();
            Cache::put('meta_waba_directory_'.$cacheSuffix, $accounts, now()->addMinutes(30));
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
                    // Also keep other WABA phones from previous cache when possible
                    $prev = Cache::get('meta_wa_phone_directory_'.$cacheSuffix);
                    if (is_array($prev)) {
                        foreach ($prev as $p) {
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

            return redirect()
                ->route('admin.meta.whatsapp.index', array_filter([
                    'waba' => $selectedId ?: null,
                    'tab' => 'phones',
                ]))
                ->with('success', count($accounts).' WhatsApp account(s) synced from Meta.');
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
     * @return array<int, array{id: string, name: string}>
     */
    protected function seedWabasFromConnection($connection): array
    {
        $items = [];
        $seen = [];

        foreach ($this->whatsapp->linkedWabaIds($connection) as $id) {
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

        $default = (string) ($connection?->whatsapp_business_id ?? '');
        if ($default !== '' && ! isset($seen[$default])) {
            $items[] = [
                'id' => $default,
                'name' => $connection?->business_name ?? 'Platform WhatsApp account',
                'ownership_type' => 'platform_default',
            ];
        }

        return $items;
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
