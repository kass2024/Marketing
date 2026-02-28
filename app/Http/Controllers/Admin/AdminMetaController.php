<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use App\Models\PlatformMetaConnection;
use Carbon\Carbon;

class AdminMetaController extends Controller
{
    protected string $graphVersion;
    protected string $graphUrl;
    protected string $oauthUrl;

    public function __construct()
    {
        $this->graphVersion = config('services.meta.graph_version');
        $this->graphUrl     = rtrim(config('services.meta.graph_url'), '/');
        $this->oauthUrl     = rtrim(config('services.meta.oauth_url'), '/');
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $platformMeta = PlatformMetaConnection::where(
            'connected_by',
            Auth::id()
        )->first();

        return view('admin.meta.index', compact('platformMeta'));
    }

    /*
    |--------------------------------------------------------------------------
    | CONNECT (Redirect to Meta OAuth)
    |--------------------------------------------------------------------------
    */
    public function connect()
    {
        if (PlatformMetaConnection::where('connected_by', Auth::id())->exists()) {
            return redirect()
                ->route('admin.meta.index')
                ->with('info', 'Platform already connected.');
        }

        $query = http_build_query([
            'client_id'     => config('services.meta.app_id'),
            'redirect_uri'  => config('services.meta.redirect_uri'),
            'scope'         => implode(',', config('services.meta.required_permissions')),
            'response_type' => 'code',
        ]);

        return redirect()->away(
            "{$this->oauthUrl}/{$this->graphVersion}/dialog/oauth?{$query}"
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CALLBACK
    |--------------------------------------------------------------------------
    */
    public function callback()
    {
        $code = request()->get('code');

        if (!$code) {
            return redirect()
                ->route('admin.meta.index')
                ->with('error', 'Authorization cancelled or failed.');
        }

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | STEP 1: SHORT-LIVED TOKEN
            |--------------------------------------------------------------------------
            */
            $tokenResponse = Http::timeout(30)->get(
                "{$this->graphUrl}/{$this->graphVersion}/oauth/access_token",
                [
                    'client_id'     => config('services.meta.app_id'),
                    'client_secret' => config('services.meta.app_secret'),
                    'redirect_uri'  => config('services.meta.redirect_uri'),
                    'code'          => $code,
                ]
            );

            if (!$tokenResponse->ok()) {
                throw new \Exception('Short token exchange failed.');
            }

            $shortToken = $tokenResponse->json('access_token');

            if (!$shortToken) {
                throw new \Exception('Short token missing.');
            }

            /*
            |--------------------------------------------------------------------------
            | STEP 2: LONG-LIVED TOKEN
            |--------------------------------------------------------------------------
            */
            $longResponse = Http::timeout(30)->get(
                config('services.meta.long_lived_exchange_url'),
                [
                    'grant_type'        => 'fb_exchange_token',
                    'client_id'         => config('services.meta.app_id'),
                    'client_secret'     => config('services.meta.app_secret'),
                    'fb_exchange_token' => $shortToken,
                ]
            );

            if (!$longResponse->ok()) {
                throw new \Exception('Long token exchange failed.');
            }

            $longToken = $longResponse->json('access_token');
            $expiresIn = $longResponse->json('expires_in');

            if (!$longToken) {
                throw new \Exception('Long token missing.');
            }

            $expiryDate = $expiresIn
                ? Carbon::now()->addSeconds($expiresIn)
                : null;

            /*
            |--------------------------------------------------------------------------
            | STEP 3: VALIDATE PERMISSIONS
            |--------------------------------------------------------------------------
            */
            $permissionsResponse = Http::get(
                "{$this->graphUrl}/{$this->graphVersion}/me/permissions",
                ['access_token' => $longToken]
            );

            if (!$permissionsResponse->ok()) {
                throw new \Exception('Failed to validate permissions.');
            }

            $granted = collect($permissionsResponse->json('data'))
                ->where('status', 'granted')
                ->pluck('permission')
                ->toArray();

            $required = config('services.meta.required_permissions');

            foreach ($required as $permission) {
                if (!in_array($permission, $granted)) {
                    throw new \Exception("Missing required permission: {$permission}");
                }
            }

            /*
            |--------------------------------------------------------------------------
            | STEP 4: GET BUSINESS
            |--------------------------------------------------------------------------
            */
            $businessResponse = Http::get(
                "{$this->graphUrl}/{$this->graphVersion}/me/businesses",
                ['access_token' => $longToken]
            );

            if (!$businessResponse->ok()) {
                throw new \Exception('Failed to fetch business accounts.');
            }

            $business = Arr::first($businessResponse->json('data', []));

            if (!$business) {
                throw new \Exception('No business accounts found.');
            }

            /*
            |--------------------------------------------------------------------------
            | CLEAN PREVIOUS RECORD
            |--------------------------------------------------------------------------
            */
            PlatformMetaConnection::where(
                'connected_by',
                Auth::id()
            )->delete();

            /*
            |--------------------------------------------------------------------------
            | SAVE CONNECTION
            |--------------------------------------------------------------------------
            */
            PlatformMetaConnection::create([
                'connected_by'     => Auth::id(),
                'business_id'      => $business['id'],
                'business_name'    => $business['name'] ?? null,
                'access_token'     => encrypt($longToken),
                'token_expires_at' => $expiryDate,
            ]);

            DB::commit();

            return redirect()
                ->route('admin.meta.index')
                ->with('success', 'Meta connected successfully.');

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Meta OAuth Error', [
                'admin_id' => Auth::id(),
                'message'  => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.meta.index')
                ->with('error', 'Meta connection failed. Check logs.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DISCONNECT
    |--------------------------------------------------------------------------
    */
    public function disconnect()
    {
        $connection = PlatformMetaConnection::where(
            'connected_by',
            Auth::id()
        )->first();

        if (!$connection) {
            return redirect()
                ->route('admin.meta.index')
                ->with('info', 'No platform connected.');
        }

        try {

            $connection->delete();

            Log::info('Platform Meta disconnected', [
                'admin_id' => Auth::id(),
            ]);

            return redirect()
                ->route('admin.meta.index')
                ->with('success', 'Platform disconnected successfully.');

        } catch (\Throwable $e) {

            Log::error('Disconnect Failed', [
                'admin_id' => Auth::id(),
                'error'    => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.meta.index')
                ->with('error', 'Disconnect failed.');
        }
    }
}