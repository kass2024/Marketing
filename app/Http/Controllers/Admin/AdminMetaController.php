<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformMetaConnection;
use App\Services\Meta\MetaAutoSyncService;
use App\Services\Tenant\TenantConnectionResolver;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
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
        $this->graphVersion = config('services.meta.graph_version');
        $this->graphUrl = rtrim(config('services.meta.graph_url'), '/');
        $this->oauthUrl = rtrim(config('services.meta.oauth_url'), '/');
    }

    public function index()
    {
        app(MetaAutoSyncService::class)->sync(false);

        $platformMeta = PlatformMetaConnection::query()->platformDefault()->active()->first()
            ?? PlatformMetaConnection::query()->where('connected_by', Auth::id())->first();

        return view('admin.meta.index', compact('platformMeta'));
    }

    public function connect()
    {
        $query = http_build_query([
            'client_id' => config('services.meta.app_id'),
            'redirect_uri' => config('services.meta.redirect_uri'),
            'scope' => implode(',', config('services.meta.required_permissions')),
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

            $permissionsResponse = Http::get(
                "{$this->graphUrl}/{$this->graphVersion}/me/permissions",
                ['access_token' => $longToken]
            );

            if (! $permissionsResponse->ok()) {
                throw new \Exception('Permission validation failed.');
            }

            $granted = collect($permissionsResponse->json('data'))
                ->where('status', 'granted')
                ->pluck('permission')
                ->toArray();

            foreach (config('services.meta.required_permissions') as $permission) {
                if (! in_array($permission, $granted, true)) {
                    throw new \Exception("Missing required permission: {$permission}");
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
