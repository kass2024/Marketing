<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use RuntimeException;

class FacebookLoginService
{
    protected string $graphUrl;
    protected string $oauthUrl;
    protected ?string $appId;
    protected ?string $appSecret;
    protected ?string $redirectUri;

    public function __construct()
    {
        $version = config('services.facebook.graph_version', 'v19.0');

        $this->graphUrl    = "https://graph.facebook.com/{$version}";
        $this->oauthUrl    = "https://www.facebook.com/{$version}";
        $this->appId       = config('services.facebook.client_id');
        $this->appSecret   = config('services.facebook.client_secret');
        $this->redirectUri = config('services.facebook.redirect');
    }

    /*
    |--------------------------------------------------------------------------
    | Internal Validation
    |--------------------------------------------------------------------------
    */

    protected function ensureConfigured(): void
    {
        if (empty($this->appId) || empty($this->appSecret) || empty($this->redirectUri)) {
            throw new RuntimeException('Facebook configuration is missing.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Generate Authorization URL
    |--------------------------------------------------------------------------
    */

    public function getAuthorizationUrl(array $scopes = []): string
    {
        $this->ensureConfigured();

        $state = Str::random(40);
        Session::put('fb_oauth_state', $state);

        $defaultScopes = [
            'email',
            'public_profile',
        ];

        $scopeList = implode(',', $scopes ?: $defaultScopes);

        $query = http_build_query([
            'client_id'     => $this->appId,
            'redirect_uri'  => $this->redirectUri,
            'scope'         => $scopeList,
            'response_type' => 'code',
            'state'         => $state,
        ]);

        return "{$this->oauthUrl}/dialog/oauth?{$query}";
    }

    /*
    |--------------------------------------------------------------------------
    | Exchange Code for Access Token
    |--------------------------------------------------------------------------
    */

    public function getAccessToken(string $code, string $state): array
    {
        $this->ensureConfigured();

        $storedState = Session::pull('fb_oauth_state');

        if (!$storedState || $storedState !== $state) {
            throw new RuntimeException('Invalid OAuth state.');
        }

        $response = Http::get("{$this->graphUrl}/oauth/access_token", [
            'client_id'     => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri'  => $this->redirectUri,
            'code'          => $code,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                'Failed to retrieve access token: ' . $response->body()
            );
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | Exchange Short Token for Long-Lived Token (Recommended for SaaS)
    |--------------------------------------------------------------------------
    */

    public function getLongLivedToken(string $shortToken): array
    {
        $this->ensureConfigured();

        $response = Http::get("{$this->graphUrl}/oauth/access_token", [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $this->appId,
            'client_secret'     => $this->appSecret,
            'fb_exchange_token' => $shortToken,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                'Failed to retrieve long-lived token: ' . $response->body()
            );
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | Get Authenticated User
    |--------------------------------------------------------------------------
    */

    public function getUser(string $accessToken): array
    {
        $response = Http::get("{$this->graphUrl}/me", [
            'fields'       => 'id,name,email',
            'access_token' => $accessToken,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                'Failed to retrieve user profile: ' . $response->body()
            );
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | Get User Pages (For Ads & WhatsApp)
    |--------------------------------------------------------------------------
    */

    public function getUserPages(string $accessToken): array
    {
        $response = Http::get("{$this->graphUrl}/me/accounts", [
            'access_token' => $accessToken,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                'Failed to retrieve pages: ' . $response->body()
            );
        }

        return $response->json();
    }
}