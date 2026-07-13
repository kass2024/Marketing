<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformMetaConnection;
use App\Services\Meta\MetaAutoSyncService;
use App\Services\Tenant\TenantConnectionResolver;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdminMetaController extends Controller
{
    protected string $graphVersion;
    protected string $graphUrl;
    protected string $oauthUrl;

    public function __construct()
    {
        $this->graphVersion = config('services.meta.graph_version') ?: 'v19.0';
        $this->graphUrl = rtrim((string) config('services.meta.graph_url', 'https://graph.facebook.com'), '/');
        $this->oauthUrl = rtrim((string) config('services.meta.oauth_url', 'https://www.facebook.com'), '/');
    }

    public function index()
    {
        // Instant DB read. Soft Meta sync after the HTML is sent (skipped while Meta rate-limits).
        $platformMeta = PlatformMetaConnection::query()->platformDefault()->active()->first()
            ?? PlatformMetaConnection::query()->where('connected_by', Auth::id())->first();

        $rateLimited = Cache::get('meta_wa_rate_limited') || Cache::get('meta_ig_rate_limited');
        if (! $rateLimited && Cache::add('meta_connection_bg_sync', 1, now()->addMinutes(2))) {
            dispatch(function () {
                try {
                    if (Cache::get('meta_wa_rate_limited') || Cache::get('meta_ig_rate_limited')) {
                        return;
                    }
                    app(MetaAutoSyncService::class)->sync(false);
                } catch (\Throwable $e) {
                    Log::warning('META_CONNECTION_BG_SYNC_FAILED', ['error' => $e->getMessage()]);
                } finally {
                    Cache::forget('meta_connection_bg_sync');
                }
            })->afterResponse();
        }

        return view('admin.meta.index', compact('platformMeta'));
    }

    public function connect()
    {
        $scopes = array_values(array_unique(array_merge(
            config('services.meta.required_permissions', []),
            config('services.meta.optional_instagram_permissions', []),
        )));

        $query = http_build_query([
            'client_id' => config('services.meta.app_id'),
            'redirect_uri' => config('services.meta.redirect_uri'),
            'scope' => implode(',', $scopes),
            'response_type' => 'code',
        ]);

        return redirect()->away(
            "{$this->oauthUrl}/{$this->graphVersion}/dialog/oauth?{$query}"
        );
    }

    public function callback()
    {
        $code = request()->get('code');

        if (! $code) {
            return redirect()
                ->route('admin.meta.index')
                ->with('error', 'Authorization cancelled.');
        }

        DB::beginTransaction();

        try {
            $shortResponse = Http::timeout(30)->get(
                "{$this->graphUrl}/{$this->graphVersion}/oauth/access_token",
                [
                    'client_id' => config('services.meta.app_id'),
                    'client_secret' => config('services.meta.app_secret'),
                    'redirect_uri' => config('services.meta.redirect_uri'),
                    'code' => $code,
                ]
            );

            if (! $shortResponse->ok()) {
                throw new \Exception('Short token exchange failed.');
            }

            $shortToken = $shortResponse->json('access_token');
            if (! $shortToken) {
                throw new \Exception('Short token missing.');
            }

            $longResponse = Http::timeout(30)->get(
                config('services.meta.long_lived_exchange_url'),
                [
                    'grant_type' => 'fb_exchange_token',
                    'client_id' => config('services.meta.app_id'),
                    'client_secret' => config('services.meta.app_secret'),
                    'fb_exchange_token' => $shortToken,
                ]
            );

            if (! $longResponse->ok()) {
                throw new \Exception('Long token exchange failed.');
            }

            $longToken = $longResponse->json('access_token');
            $expiresIn = $longResponse->json('expires_in');
            if (! $longToken) {
                throw new \Exception('Long token missing.');
            }

            $expiryDate = $expiresIn ? Carbon::now()->addSeconds($expiresIn) : null;

            $permissionsResponse = Http::timeout(30)->get(
                "{$this->graphUrl}/{$this->graphVersion}/me/permissions",
                ['access_token' => $longToken]
            );

            $granted = [];
            if ($permissionsResponse->ok()) {
                $granted = collect($permissionsResponse->json('data'))
                    ->where('status', 'granted')
                    ->pluck('permission')
                    ->values()
                    ->all();
            } else {
                // /me/permissions often fails for some long-lived / Business tokens even when the token is valid.
                Log::warning('META_OAUTH_PERMISSIONS_ENDPOINT_FAILED', [
                    'status' => $permissionsResponse->status(),
                    'error' => data_get($permissionsResponse->json(), 'error.message'),
                ]);

                $meCheck = Http::timeout(30)->get(
                    "{$this->graphUrl}/{$this->graphVersion}/me",
                    ['access_token' => $longToken, 'fields' => 'id,name']
                );
                if (! $meCheck->ok()) {
                    throw new \Exception(
                        'Meta token is invalid after login. Prefer Sync from .env (system user token), or reconnect and grant all permissions.'
                    );
                }

                // Token works — continue with configured scopes (same as .env / system-user path)
                $granted = config('services.meta.required_permissions', []);
            }

            if ($granted !== []) {
                $required = config('services.meta.required_permissions', []);
                $missing = array_values(array_diff($required, $granted));
                // Only block on critical scopes when Meta actually returned a grant list
                if ($permissionsResponse->ok() && $missing !== []) {
                    $critical = [
                        'ads_management',
                        'business_management',
                        'whatsapp_business_management',
                        'whatsapp_business_messaging',
                    ];
                    $missingCritical = array_values(array_intersect($critical, $missing));
                    if ($missingCritical !== []) {
                        throw new \Exception(
                            'Missing required Meta permissions: '.implode(', ', $missingCritical)
                            .'. Reconnect and approve all requested scopes, or use Sync from .env.'
                        );
                    }
                    Log::warning('META_OAUTH_NONCRITICAL_PERMISSIONS_MISSING', ['missing' => $missing]);
                }
            }

            $businessResponse = Http::get(
                "{$this->graphUrl}/{$this->graphVersion}/me/businesses",
                ['access_token' => $longToken]
            );

            $business = Arr::first($businessResponse->json('data', []));
            if (! $business) {
                throw new \Exception('No business found.');
            }

            $wabaResponse = Http::get(
                "{$this->graphUrl}/{$this->graphVersion}/{$business['id']}/owned_whatsapp_business_accounts",
                ['access_token' => $longToken]
            );

            $waba = Arr::first($wabaResponse->json('data', []));
            if (! $waba) {
                throw new \Exception('No WhatsApp Business Account found.');
            }

            $phoneResponse = Http::get(
                "{$this->graphUrl}/{$this->graphVersion}/{$waba['id']}/phone_numbers",
                ['access_token' => $longToken]
            );

            $phone = Arr::first($phoneResponse->json('data', []));
            if (! $phone || empty($phone['id'])) {
                throw new \Exception('No WhatsApp phone number found. Add a number in Business Manager → WhatsApp accounts.');
            }

            $phoneNumberId = $phone['id'];
            $whatsappPhone = $phone['display_phone_number'] ?? $phone['verified_name'] ?? null;

            $adAccountResponse = Http::get(
                "{$this->graphUrl}/{$this->graphVersion}/{$business['id']}/owned_ad_accounts",
                [
                    'access_token' => $longToken,
                    'fields' => 'id,name,account_status',
                    'limit' => 5,
                ]
            );

            $adAccount = Arr::first($adAccountResponse->json('data', []));
            $adAccountId = $adAccount['id'] ?? config('services.meta.ad_account_id');

            $pagesResponse = Http::get(
                "{$this->graphUrl}/{$this->graphVersion}/{$business['id']}/owned_pages",
                [
                    'access_token' => $longToken,
                    'fields' => 'id,name,instagram_business_account{id,username}',
                    'limit' => 10,
                ]
            );

            $page = Arr::first($pagesResponse->json('data', []))
                ?? Arr::first(Http::get(
                    "{$this->graphUrl}/{$this->graphVersion}/me/accounts",
                    ['access_token' => $longToken, 'fields' => 'id,name,instagram_business_account{id,username}', 'limit' => 10]
                )->json('data', []));

            if (! $page) {
                throw new \Exception('No Facebook Page found. Link a Page to your Business Manager.');
            }

            $instagramId = data_get($page, 'instagram_business_account.id');

            $connection = PlatformMetaConnection::query()->platformDefault()->first()
                ?? PlatformMetaConnection::query()->where('connected_by', Auth::id())->first()
                ?? new PlatformMetaConnection;

            $connection->fill([
                'connected_by' => Auth::id(),
                'business_id' => $business['id'],
                'business_name' => $business['name'] ?? null,
                'ad_account_id' => $adAccountId,
                'ad_account_name' => $adAccount['name'] ?? null,
                'page_id' => $page['id'],
                'page_name' => $page['name'] ?? null,
                'instagram_business_account_id' => $instagramId,
                'whatsapp_business_id' => $waba['id'],
                'whatsapp_phone_number_id' => $phoneNumberId,
                'whatsapp_phone_number' => $whatsappPhone,
                'access_token' => encrypt($longToken),
                'token_expires_at' => $expiryDate,
                'granted_permissions' => $granted,
                'is_platform_default' => true,
                'is_active' => true,
            ]);
            $connection->save();

            PlatformMetaConnection::query()
                ->where('id', '!=', $connection->id)
                ->where('is_platform_default', true)
                ->update(['is_platform_default' => false]);

            DB::commit();

            return redirect()
                ->route('admin.meta.whatsapp.index')
                ->with('success', 'Meta connected successfully. You can manage WhatsApp numbers below.');
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Meta OAuth Error', [
                'admin_id' => Auth::id(),
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.meta.index')
                ->with('error', $e->getMessage());
        }
    }

    public function disconnect()
    {
        $connection = app(TenantConnectionResolver::class)->forCurrentUser()
            ?? PlatformMetaConnection::query()->where('connected_by', Auth::id())->first();

        if (! $connection) {
            return redirect()
                ->route('admin.meta.index')
                ->with('info', 'No platform connected.');
        }

        $connection->delete();

        return redirect()
            ->route('admin.meta.index')
            ->with('success', 'Platform disconnected successfully.');
    }
}
