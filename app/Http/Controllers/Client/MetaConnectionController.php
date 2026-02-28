<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\MetaConnection;
use App\Services\MetaOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MetaConnectionController extends Controller
{
    protected MetaOAuthService $meta;

    public function __construct(MetaOAuthService $meta)
    {
        $this->meta = $meta;
    }

    /**
     * Redirect client to META BUSINESS OAuth (Ads App)
     * This MUST use META_APP_ID (Marketing App)
     */
    public function connect()
    {
        // Safety check
        if (!config('services.meta.app_id')) {
            return redirect()
                ->route('client.dashboard')
                ->with('error', 'Meta App configuration missing.');
        }

        $authorizationUrl = $this->meta->getAuthorizationUrl();

        return redirect()->away($authorizationUrl);
    }

    /**
     * Handle Meta Business OAuth callback
     */
    public function callback(Request $request)
    {
        if (!$request->has('code')) {
            return redirect()
                ->route('client.dashboard')
                ->with('error', 'Meta authorization failed or was cancelled.');
        }

        try {

            // Exchange code for access token
            $tokenData = $this->meta->exchangeCodeForToken($request->code);

            if (!isset($tokenData['access_token'])) {
                throw new \Exception('Invalid token response.');
            }

            $client = Auth::user()->client;

            MetaConnection::updateOrCreate(
                ['client_id' => $client->id],
                [
                    'meta_user_id'     => Auth::id(),
                    'access_token'     => $this->meta->encryptToken($tokenData['access_token']),
                    'token_expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600),
                ]
            );

            return redirect()
                ->route('client.dashboard')
                ->with('success', 'Meta Business account connected successfully.');

        } catch (\Throwable $e) {

            report($e);

            return redirect()
                ->route('client.dashboard')
                ->with('error', 'Meta connection failed. Please try again.');
        }
    }
}