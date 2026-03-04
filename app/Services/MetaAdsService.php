<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaAdsService
{
    protected string $baseUrl;
    protected string $accessToken;
    protected string $adAccountId;

    public function __construct()
    {
        $this->baseUrl = 'https://graph.facebook.com/' . config('services.meta.graph_version', 'v19.0');
        $this->accessToken = config('services.meta.token');
        $this->adAccountId = config('services.meta.ad_account_id');

        if (empty($this->accessToken)) {
            throw new \Exception('META_SYSTEM_USER_TOKEN is not configured.');
        }
    }

    protected function request(string $endpoint, array $params = []): array
    {
        $response = Http::timeout(20)
            ->retry(2, 500)
            ->get("{$this->baseUrl}/{$endpoint}", array_merge($params, [
                'access_token' => $this->accessToken,
            ]));

        if ($response->failed()) {
            Log::error('Meta API Error', [
                'endpoint' => $endpoint,
                'response' => $response->body(),
            ]);

            throw new \Exception(
                $response->json()['error']['message'] ?? 'Meta API request failed.'
            );
        }

        return $response->json();
    }

    public function getAdAccounts(): array
    {
        return $this->request('me/adaccounts', [
            'fields' => 'id,name,account_status,currency'
        ]);
    }

    public function getCampaigns(): array
    {
        return $this->request("{$this->adAccountId}/campaigns", [
            'fields' => 'id,name,status,objective'
        ]);
    }
}