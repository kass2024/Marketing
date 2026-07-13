<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Meta\InstagramBusinessAccountService;
use App\Services\Meta\MetaAutoSyncService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InstagramAccountsController extends Controller
{
    public function __construct(
        protected InstagramBusinessAccountService $instagram,
        protected MetaAutoSyncService $autoSync
    ) {}

    public function index(Request $request): View
    {
        // Instant: cache/DB only. Meta Graph runs after the response (or Sync now).
        $connection = $this->instagram->connection();
        $cacheSuffix = (string) ($connection?->id ?? 'platform');
        $igCacheKey = 'meta_ig_directory_'.$cacheSuffix;
        $syncedAtKey = 'meta_ig_synced_at_'.$cacheSuffix;

        $accounts = [];
        $fromCache = false;

        $cached = Cache::get($igCacheKey);
        if (is_array($cached) && $cached !== []) {
            $accounts = $cached;
            $fromCache = true;
        } else {
            $accounts = $this->seedFromConnection($connection);
        }

        $accounts = array_values(array_map(
            fn ($row) => $this->normalizeAccountLabel(is_array($row) ? $row : []),
            $accounts
        ));

        if ($this->shouldBackgroundSync($accounts, $syncedAtKey)) {
            $this->queueBackgroundSync($cacheSuffix);
        }

        $selectedId = (string) ($request->query('ig')
            ?: ($connection?->instagram_business_account_id ?? ($accounts[0]['id'] ?? '')));
        $selected = collect($accounts)->firstWhere('id', $selectedId);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $accounts = array_values(array_filter($accounts, function ($a) use ($search) {
                $hay = strtolower(($a['username'] ?? '').' '.($a['name'] ?? '').' '.($a['id'] ?? ''));

                return str_contains($hay, strtolower($search));
            }));
        }

        return view('admin.meta.instagram.index', [
            'connection' => $connection,
            'accounts' => $accounts,
            'selectedId' => $selectedId,
            'selected' => $selected,
            'search' => $search,
            'error' => null,
            'lastSyncedAt' => Cache::get($syncedAtKey) ?: ($fromCache ? 'cached' : null),
            'needsSync' => ! $fromCache && $accounts === [],
            'autoSynced' => false,
            'metaBusinessSuiteUrl' => 'https://business.facebook.com/latest/settings/instagram_account_settings',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $accounts
     */
    protected function shouldBackgroundSync(array $accounts, string $syncedAtKey): bool
    {
        if ($accounts === []) {
            return true;
        }

        foreach ($accounts as $row) {
            if (! is_array($row) || empty($row['username'])) {
                return true;
            }
        }

        $at = Cache::get($syncedAtKey);
        if (! $at || $at === 'cached') {
            return true;
        }

        try {
            return Carbon::parse((string) $at)->lt(now()->subMinutes(15));
        } catch (\Throwable) {
            return true;
        }
    }

    protected function queueBackgroundSync(string $cacheSuffix): void
    {
        // Meta (#4) rate limits turn afterResponse graph work into “endless loading”.
        if (Cache::get('meta_ig_rate_limited') || Cache::get('meta_wa_rate_limited')) {
            return;
        }

        $lockKey = 'meta_ig_bg_sync_'.$cacheSuffix;
        if (! Cache::add($lockKey, 1, now()->addMinutes(2))) {
            return;
        }

        dispatch(function () use ($cacheSuffix, $lockKey) {
            try {
                if (Cache::get('meta_ig_rate_limited') || Cache::get('meta_wa_rate_limited')) {
                    return;
                }
                app(MetaAutoSyncService::class)->sync(false);
                $result = app(InstagramBusinessAccountService::class)->syncToConnection();
                Cache::put('meta_ig_directory_'.$cacheSuffix, $result['accounts'], now()->addMinutes(30));
                Cache::put('meta_ig_synced_at_'.$cacheSuffix, now()->toDateTimeString(), now()->addMinutes(30));
            } catch (\Throwable $e) {
                Log::warning('IG_BACKGROUND_SYNC_FAILED', ['error' => $e->getMessage()]);
            } finally {
                Cache::forget($lockKey);
            }
        })->afterResponse();
    }

    /**
     * @return array<int, array{id:string,username:?string,name:?string,source:string}>
     */
    protected function seedFromConnection($connection): array
    {
        return app(InstagramBusinessAccountService::class)
            ->seedDirectoryForDisplay($connection);
    }

    /**
     * Prefer @username from Meta-synced data; never invent from .env / page name.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function normalizeAccountLabel(array $row): array
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
        }

        $row['label'] = ! empty($row['username'])
            ? '@'.$row['username']
            : ('IG '.($row['id'] ?? ''));

        return $row;
    }

    public function link(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'instagram_id' => 'required|string|max:64',
        ]);

        try {
            $result = $this->instagram->importExistingAccount($data['instagram_id']);
            Cache::forget('meta_ig_directory_'.($this->instagram->connection()?->id ?? 'platform'));
            Cache::forget('meta_ig_synced_at_'.($this->instagram->connection()?->id ?? 'platform'));

            return redirect()
                ->route('admin.meta.instagram.index', [
                    'ig' => $result['account']['id'] ?? $data['instagram_id'],
                ])
                ->with('success', $result['message']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput()->with('show_link_ig', true);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage())->withInput()->with('show_link_ig', true);
        }
    }

    public function setDefault(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'instagram_id' => 'required|string|max:64',
        ]);

        try {
            $this->instagram->setAsPlatformDefault($data['instagram_id']);

            return redirect()
                ->route('admin.meta.instagram.index', ['ig' => $data['instagram_id']])
                ->with('success', 'Default Instagram account updated for Ad Studio.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function sync(Request $request): RedirectResponse
    {
        try {
            Cache::forget('meta_ig_rate_limited');
            $this->autoSync->syncAlways();
            $result = $this->instagram->syncToConnection();
            $connection = $this->instagram->connection();
            $suffix = (string) ($connection?->id ?? 'platform');
            Cache::put('meta_ig_directory_'.$suffix, $result['accounts'], now()->addMinutes(30));
            Cache::put('meta_ig_synced_at_'.$suffix, now()->toDateTimeString(), now()->addMinutes(30));

            $names = collect($result['accounts'])
                ->map(fn ($a) => ! empty($a['username']) ? '@'.$a['username'] : null)
                ->filter()
                ->values()
                ->all();

            if ($names !== []) {
                $label = count($result['accounts']) === 1
                    ? ('Synced '.$names[0].' from Meta.')
                    : (count($result['accounts']).' Instagram account(s) synced from Meta: '.implode(', ', $names).'.');
            } else {
                $diag = $this->instagram->diagnoseTokenForInstagram();
                $ids = collect($result['accounts'])->pluck('id')->filter()->values()->all();
                $label = $diag['hint'];
                if (! empty($diag['missing_instagram_scopes'])) {
                    $label .= ' Missing scopes: '.implode(', ', $diag['missing_instagram_scopes']).'.';
                }
                if ($ids !== []) {
                    $label .= ' IDs: '.implode(', ', $ids).'.';
                }
                if (! empty($diag['token_type'])) {
                    $label .= ' Token type: '.$diag['token_type'].'.';
                }
            }

            return redirect()
                ->route('admin.meta.instagram.index')
                ->with($names !== [] ? 'success' : 'error', $label);
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.meta.instagram.index')
                ->with('error', $e->getMessage());
        }
    }
}
