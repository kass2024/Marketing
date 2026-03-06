<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

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

        if (!$this->accessToken) {
            throw new Exception('Meta access token missing in services config');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HTTP CLIENT
    |--------------------------------------------------------------------------
    */

    protected function client()
    {
        return Http::timeout(30)
            ->retry(2, 500)
            ->acceptJson();
    }

    /*
    |--------------------------------------------------------------------------
    | GET REQUEST
    |--------------------------------------------------------------------------
    */

    protected function request(string $endpoint, array $params = []): array
    {
        $response = $this->client()->get(
            "{$this->baseUrl}/{$endpoint}",
            array_merge($params, [
                'access_token' => $this->accessToken
            ])
        );

        if ($response->failed()) {

            Log::error('META_API_GET_FAILED', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            $error = $response->json()['error'] ?? [];

            throw new Exception($this->parseError($error));
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | POST REQUEST
    |--------------------------------------------------------------------------
    */

    protected function post(string $endpoint, array $params = []): array
    {
        $response = $this->client()
            ->asForm()
            ->post(
                "{$this->baseUrl}/{$endpoint}",
                array_merge($params, [
                    'access_token' => $this->accessToken
                ])
            );

        if ($response->failed()) {

            Log::error('META_API_POST_FAILED', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'payload' => $params,
                'response' => $response->body()
            ]);

            $error = $response->json()['error'] ?? [];

            throw new Exception($this->parseError($error));
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE REQUEST
    |--------------------------------------------------------------------------
    */

    protected function delete(string $endpoint): array
    {
        $response = $this->client()->delete(
            "{$this->baseUrl}/{$endpoint}",
            ['access_token' => $this->accessToken]
        );

        if ($response->failed()) {

            Log::error('META_API_DELETE_FAILED', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            $error = $response->json()['error'] ?? [];

            throw new Exception($this->parseError($error));
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | PAGINATION
    |--------------------------------------------------------------------------
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

            $response = Http::timeout(30)
                ->retry(2, 500)
                ->get($response['paging']['next'])
                ->json();
        }

        return ['data' => $results];
    }

    /*
    |--------------------------------------------------------------------------
    | CAMPAIGNS
    |--------------------------------------------------------------------------
    */

    public function getCampaigns(): array
    {
        return $this->fetchAllPages("{$this->adAccountId}/campaigns", [
            'fields' => 'id,name,status,objective,daily_budget,lifetime_budget,start_time,stop_time'
        ]);
    }

    public function createCampaign(string $accountId, array $data): array
    {
        $payload = [
            'name' => $data['name'],
            'objective' => $data['objective'],
            'status' => $data['status'] ?? 'PAUSED',
            'special_ad_categories' => json_encode([])
        ];

        if (isset($data['daily_budget'])) {
            $payload['daily_budget'] = $data['daily_budget'];
        }

        if (isset($data['lifetime_budget'])) {
            $payload['lifetime_budget'] = $data['lifetime_budget'];
        }

        return $this->post("{$accountId}/campaigns", $payload);
    }

    /*
    |--------------------------------------------------------------------------
    | ADSETS
    |--------------------------------------------------------------------------
    */

    public function createAdSet(string $accountId, array $data): array
    {
        $payload = [

            'name' => $data['name'],

            'campaign_id' => $data['campaign_id'],

            'billing_event' => $data['billing_event'] ?? 'IMPRESSIONS',

            'optimization_goal' => $data['optimization_goal'] ?? 'REACH',

            'status' => $data['status'] ?? 'PAUSED',

            'targeting' => is_string($data['targeting'])
                ? $data['targeting']
                : json_encode($data['targeting'])
        ];

        if (isset($data['daily_budget'])) {
            $payload['daily_budget'] = $data['daily_budget'];
        }

        if (isset($data['bid_strategy'])) {
            $payload['bid_strategy'] = $data['bid_strategy'];
        }

        if (isset($data['bid_amount'])) {
            $payload['bid_amount'] = $data['bid_amount'];
        }

        if (isset($data['start_time'])) {
            $payload['start_time'] = $data['start_time'];
        }

        if (isset($data['end_time'])) {
            $payload['end_time'] = $data['end_time'];
        }

        if (isset($data['promoted_object'])) {

            $payload['promoted_object'] = is_string($data['promoted_object'])
                ? $data['promoted_object']
                : json_encode($data['promoted_object']);
        }

        return $this->post("{$accountId}/adsets", $payload);
    }

    public function getAdSets(): array
    {
        return $this->fetchAllPages("{$this->adAccountId}/adsets", [
            'fields' => 'id,name,status,campaign_id,daily_budget,targeting'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATIVES
    |--------------------------------------------------------------------------
    */

    public function createCreative(string $accountId, array $data): array
    {
        $payload = [
            'name' => $data['name'],
            'object_story_spec' => is_string($data['object_story_spec'])
                ? $data['object_story_spec']
                : json_encode($data['object_story_spec'])
        ];

        return $this->post("{$accountId}/adcreatives", $payload);
    }

    /*
    |--------------------------------------------------------------------------
    | ADS
    |--------------------------------------------------------------------------
    */

    public function createAd(string $accountId, array $data): array
    {
        $payload = [
            'name' => $data['name'],
            'adset_id' => $data['adset_id'],
            'status' => $data['status'] ?? 'PAUSED'
        ];

        if (isset($data['creative'])) {
            $payload['creative'] = is_string($data['creative'])
                ? $data['creative']
                : json_encode($data['creative']);
        }

        return $this->post("{$accountId}/ads", $payload);
    }

    /*
    |--------------------------------------------------------------------------
    | INTEREST SEARCH
    |--------------------------------------------------------------------------
    */

    public function searchInterests(string $query): array
    {
        try {

            $response = $this->request('search', [
                'type' => 'adinterest',
                'q' => $query,
                'limit' => 10
            ]);

            return $response['data'] ?? [];
        }

        catch (Exception $e) {

            Log::warning('META_INTEREST_SEARCH_FAILED', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INSIGHTS
    |--------------------------------------------------------------------------
    */

    public function getInsights(array $params = []): array
    {
        $default = [
            'fields' => 'campaign_name,adset_name,impressions,clicks,spend,cpc,ctr,reach,cpm',
            'date_preset' => 'last_30d',
            'level' => 'account'
        ];

        return $this->fetchAllPages(
            "{$this->adAccountId}/insights",
            array_merge($default, $params)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UTILITIES
    |--------------------------------------------------------------------------
    */

    public function checkConnection(): bool
    {
        try {

            $this->request('me');

            return true;
        }

        catch (Exception $e) {

            return false;
        }
    }

    public function formatBudget(float $budget): int
    {
        return (int) ($budget * 100);
    }

    public function parseError(array $error): string
    {
        $code = $error['code'] ?? 0;
        $subcode = $error['error_subcode'] ?? 0;
        $message = $error['message'] ?? 'Unknown Meta API error';

        $map = [

            100 => 'Invalid parameter',

            190 => 'Invalid access token',

            200 => 'Permission error',

            1815857 => 'Targeting conflict',

            1815855 => 'Audience too narrow',

            1815862 => 'Invalid interest id',

            368 => 'Temporary Meta API error'
        ];

        if (isset($map[$subcode])) {
            return $map[$subcode];
        }

        if (isset($map[$code])) {
            return $map[$code];
        }

        return $message;
    }
}