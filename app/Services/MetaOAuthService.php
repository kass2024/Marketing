<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;

class MetaOAuthService
{
    protected string $graphUrl;
    protected string $oauthUrl;

    public function __construct()
    {
        $version = config('services.meta.graph_version');

        $this->graphUrl = config('services.meta.base_url') . $version;
        $this->oauthUrl = config('services.meta.oauth_url') . $version;
    }

    public function getAuthorizationUrl(): string
    {
        $query = http_build_query([
            'client_id'     => config('services.meta.app_id'),
            'redirect_uri'  => config('services.meta.redirect_uri'),
            'scope'         => 'ads_management,ads_read,business_management',
            'response_type' => 'code',
        ]);

        return "{$this->oauthUrl}/dialog/oauth?{$query}";
    }

    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::get("{$this->graphUrl}/oauth/access_token", [
            'client_id'     => config('services.meta.app_id'),
            'client_secret' => config('services.meta.app_secret'),
            'redirect_uri'  => config('services.meta.redirect_uri'),
            'code'          => $code,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to retrieve access token from Meta.');
        }

        return $response->json();
    }

    public function encryptToken(string $token): string
    {
        return Crypt::encryptString($token);
    }

    public function decryptToken(string $encrypted): string
    {
        return Crypt::decryptString($encrypted);
    }
}