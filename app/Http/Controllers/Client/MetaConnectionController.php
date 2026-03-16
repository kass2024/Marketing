<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\MetaConnection;
use App\Services\MetaOAuthService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaConnectionController extends Controller
{
    protected MetaOAuthService $meta;

    public function __construct(MetaOAuthService $meta)
    {
        $this->meta = $meta;
    }

    /*
    |--------------------------------------------------------------------------
    | Redirect Client to Meta OAuth
    |--------------------------------------------------------------------------
    */

    public function connect()
    {
        try {

            if (!config('services.meta.app_id')) {

                return redirect()
                    ->route('client.dashboard')
                    ->with('error','Meta App configuration missing.');
            }

            $redirectUri = route('client.meta.callback');

            $authorizationUrl = $this->meta->getAuthorizationUrl($redirectUri);

            return redirect()->away($authorizationUrl);

        } catch (\Throwable $e) {

            Log::error('CLIENT_META_CONNECT_ERROR',[
                'user_id' => Auth::id(),
                'error'   => $e->getMessage()
            ]);

            return redirect()
                ->route('client.dashboard')
                ->with('error','Unable to start Meta connection.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Handle Meta OAuth Callback
    |--------------------------------------------------------------------------
    */

    public function callback(Request $request)
    {
        try {

            if (!$request->has('code')) {

                return redirect()
                    ->route('client.dashboard')
                    ->with('error','Meta authorization cancelled.');
            }

            $user = Auth::user();

            if (!$user || !$user->client) {

                return redirect()
                    ->route('client.dashboard')
                    ->with('error','Client account not found.');
            }

            $client = $user->client;

            $redirectUri = route('client.meta.callback');

            /*
            |--------------------------------------------------------------------------
            | Exchange Code for Short Token
            |--------------------------------------------------------------------------
            */

            $tokenData = $this->meta->exchangeCodeForToken(
                $request->get('code'),
                $redirectUri
            );

            if (!isset($tokenData['access_token'])) {

                throw new \Exception('Invalid token response from Meta.');
            }

            $shortToken = $tokenData['access_token'];

            /*
            |--------------------------------------------------------------------------
            | Convert to Long Lived Token (60 days)
            |--------------------------------------------------------------------------
            */

            $longTokenData = $this->meta->exchangeForLongLivedToken($shortToken);

            $accessToken = $longTokenData['access_token'] ?? $shortToken;
            $expiresIn   = $longTokenData['expires_in'] ?? 5184000;

            /*
            |--------------------------------------------------------------------------
            | Get Meta User ID
            |--------------------------------------------------------------------------
            */

            $metaUser = Http::get(
                'https://graph.facebook.com/'.config('services.meta.graph_version','v19.0').'/me',
                [
                    'access_token' => $accessToken
                ]
            )->json();

            $metaUserId = $metaUser['id'] ?? null;

            if (!$metaUserId) {

                throw new \Exception('Unable to retrieve Meta user ID.');
            }

            /*
            |--------------------------------------------------------------------------
            | Save Meta Connection
            |--------------------------------------------------------------------------
            */

            MetaConnection::updateOrCreate(

                ['client_id' => $client->id],

                [
                    'meta_user_id'     => $metaUserId,
                    'access_token'     => $this->meta->encryptToken($accessToken),
                    'token_expires_at' => now()->addSeconds($expiresIn),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Redirect to Ad Account Selection
            |--------------------------------------------------------------------------
            */

            return redirect()
                ->route('client.meta.index')
                ->with('success','Meta account connected successfully.');

        } catch (\Throwable $e) {

            Log::error('CLIENT_META_CALLBACK_ERROR',[

                'user_id' => Auth::id(),
                'error'   => $e->getMessage(),
                'request' => $request->all()
            ]);

            return redirect()
                ->route('client.dashboard')
                ->with('error','Meta connection failed. Please try again.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Disconnect Meta Account
    |--------------------------------------------------------------------------
    */

    public function disconnect()
    {
        try {

            $user = Auth::user();

            if (!$user || !$user->client) {

                return redirect()
                    ->route('client.dashboard')
                    ->with('error','Client not found.');
            }

            MetaConnection::where('client_id',$user->client->id)->delete();

            return redirect()
                ->route('client.meta.index')
                ->with('success','Meta account disconnected.');

        } catch (\Throwable $e) {

            Log::error('CLIENT_META_DISCONNECT_ERROR',[

                'user_id' => Auth::id(),
                'error'   => $e->getMessage()
            ]);

            return redirect()
                ->route('client.dashboard')
                ->with('error','Unable to disconnect Meta account.');
        }
    }
}