<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaAdsService
{
    protected string $baseUrl;
    protected string $accessToken;
    protected ?string $adAccountId;

    public function __construct()
    {
        $version = config('services.meta.graph_version', 'v19.0');

        $this->baseUrl = "https://graph.facebook.com/{$version}";
        $this->accessToken = config('services.meta.token');
        $this->adAccountId = config('services.meta.ad_account_id');

        if (empty($this->accessToken)) {
            throw new \Exception('Meta access token is missing in config/services.php');
        }
    }

    /**
     * Generic request handler for Meta Graph API
     */
    protected function request(string $endpoint, array $params = []): array
    {
        $response = Http::timeout(30)
            ->retry(2, 500)
            ->get("{$this->baseUrl}/{$endpoint}", array_merge($params, [
                'access_token' => $this->accessToken
            ]));

        if ($response->failed()) {

            Log::error('Meta API Error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            $error = $response->json()['error']['message'] ?? 'Meta API request failed';

            throw new \Exception($error);
        }

        return $response->json();
    }

    /**
     * Fetch ALL pages automatically
     */
    protected function fetchAllPages(string $endpoint, array $params = []): array
    {
        $results = [];
        $response = $this->request($endpoint, $params);

        while (true) {

            if (!empty($response['data'])) {
                $results = array_merge($results, $response['data']);
            }

            if (!isset($response['paging']['next'])) {
                break;
            }

            $nextUrl = $response['paging']['next'];

            $response = Http::timeout(30)
                ->retry(2, 500)
                ->get($nextUrl)
                ->json();
        }

        return ['data' => $results];
    }

    /**
     * Get all ad accounts
     */
    public function getAdAccounts(): array
    {
        return $this->fetchAllPages('me/adaccounts', [
            'fields' => 'id,name,account_status,currency'
        ]);
    }

    /**
     * Get campaigns
     */
    public function getCampaigns(): array
    {
        if (!$this->adAccountId) {
            throw new \Exception('Meta ad_account_id not configured.');
        }

        return $this->fetchAllPages("{$this->adAccountId}/campaigns", [
            'fields' => 'id,name,status,objective,daily_budget,lifetime_budget'
        ]);
    }

    /**
     * Get ad sets
     */
    public function getAdSets(): array
    {
        if (!$this->adAccountId) {
            throw new \Exception('Meta ad_account_id not configured.');
        }

        return $this->fetchAllPages("{$this->adAccountId}/adsets", [
            'fields' => 'id,name,status,campaign_id,daily_budget,lifetime_budget'
        ]);
    }

    /**
     * Get ads
     */
    public function getAds(): array
    {
        if (!$this->adAccountId) {
            throw new \Exception('Meta ad_account_id not configured.');
        }

        return $this->fetchAllPages("{$this->adAccountId}/ads", [
            'fields' => 'id,name,status,adset_id,creative'
        ]);
    }

    /**
     * Get insights (performance metrics)
     */
    public function getInsights(array $params = []): array
    {
        if (!$this->adAccountId) {
            throw new \Exception('Meta ad_account_id not configured.');
        }

        $defaultParams = [
            'fields' => 'campaign_name,impressions,clicks,spend,cpc,ctr',
            'date_preset' => 'last_30d'
        ];

        return $this->fetchAllPages("{$this->adAccountId}/insights", array_merge($defaultParams, $params));
    }
}