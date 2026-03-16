<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Exception;

class MetaOAuthService
{
    protected string $graphUrl;
    protected string $oauthUrl;
    protected string $appId;
    protected string $appSecret;

    public function __construct()
    {
        $version = config('services.meta.graph_version', 'v19.0');

        $this->graphUrl = rtrim(config('services.meta.base_url','https://graph.facebook.com'), '/') . "/{$version}";
        $this->oauthUrl = rtrim(config('services.meta.oauth_url','https://www.facebook.com'), '/') . "/{$version}";

        $this->appId = config('services.meta.app_id');
        $this->appSecret = config('services.meta.app_secret');

        if (!$this->appId || !$this->appSecret) {
            throw new Exception('Meta App configuration missing.');
        }
    }

    /**
     * Generate OAuth URL
     * $redirectUri can be admin or client callback
     */
    public function getAuthorizationUrl(string $redirectUri): string
    {
        $query = http_build_query([
            'client_id'     => $this->appId,
            'redirect_uri'  => $redirectUri,
            'scope'         => implode(',', [
                'ads_management',
                'ads_read',
                'business_management',
                'pages_show_list',
                'pages_read_engagement',
                'pages_manage_ads'
            ]),
            'response_type' => 'code'
        ]);

        return "{$this->oauthUrl}/dialog/oauth?{$query}";
    }

    /**
     * Exchange OAuth code for access token
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $response = Http::get("{$this->graphUrl}/oauth/access_token", [
            'client_id'     => $this->appId,
            'client_secret' => $this->appSecret,
            'redirect_uri'  => $redirectUri,
            'code'          => $code
        ]);

        if (!$response->successful()) {

            Log::error('META_OAUTH_TOKEN_ERROR', [
                'response' => $response->body()
            ]);

            throw new Exception('Failed to retrieve access token from Meta.');
        }

        $data = $response->json();

        if (!isset($data['access_token'])) {
            throw new Exception('Meta OAuth response missing access_token.');
        }

        return $data;
    }

    /**
     * Convert short-lived token → long-lived token
     */
    public function exchangeForLongLivedToken(string $shortToken): array
    {
        $response = Http::get("{$this->graphUrl}/oauth/access_token", [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $this->appId,
            'client_secret'     => $this->appSecret,
            'fb_exchange_token' => $shortToken
        ]);

        if (!$response->successful()) {

            Log::error('META_LONG_TOKEN_ERROR', [
                'response' => $response->body()
            ]);

            throw new Exception('Failed to retrieve long-lived token.');
        }

        return $response->json();
    }

    /**
     * Encrypt Meta token before storing
     */
    public function encryptToken(string $token): string
    {
        return Crypt::encryptString($token);
    }

    /**
     * Decrypt Meta token when needed
     */
    public function decryptToken(string $encrypted): string
    {
        return Crypt::decryptString($encrypted);
    }
}