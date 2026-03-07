<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class MetaAdsService
{
    protected string $baseUrl;
    protected string $accessToken;
    protected string $adAccountId;
    protected bool $debug;

    public function __construct()
    {
        $version = config('services.meta.graph_version', 'v19.0');

        $this->baseUrl = "https://graph.facebook.com/{$version}";
        $this->accessToken = config('services.meta.token');
        $this->adAccountId = $this->formatAccount(config('services.meta.ad_account_id'));
        $this->debug = config('app.debug', false);

        if (!$this->accessToken) {
            throw new Exception('Meta access token missing in config/services.php');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT ACCOUNT
    |--------------------------------------------------------------------------
    */

    protected function formatAccount(string $id): string
    {
        if (str_starts_with($id, 'act_')) {
            return $id;
        }

        return "act_{$id}";
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
    | DEBUG LOGGER
    |--------------------------------------------------------------------------
    */

    protected function debug(string $title, array $data = [])
    {
        if ($this->debug) {
            Log::info($title, $data);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HANDLE ERROR
    |--------------------------------------------------------------------------
    */

    protected function handleError($response, $endpoint, $payload = [])
    {
        $body = $response->json();

        Log::error('META_API_ERROR', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'payload' => $payload,
            'response' => $body
        ]);

        $error = $body['error'] ?? [];

        $message = $error['message'] ?? 'Unknown Meta API error';
        $code = $error['code'] ?? 0;
        $subcode = $error['error_subcode'] ?? 0;

        throw new Exception("Meta API Error: {$message} (code:{$code} subcode:{$subcode})");
    }

    /*
    |--------------------------------------------------------------------------
    | GET REQUEST
    |--------------------------------------------------------------------------
    */

    protected function get(string $endpoint, array $params = []): array
    {
        $params['access_token'] = $this->accessToken;

        $this->debug('META_GET_REQUEST', [
            'endpoint' => $endpoint,
            'params' => $params
        ]);

        $response = $this->client()->get("{$this->baseUrl}/{$endpoint}", $params);

        if ($response->failed()) {
            $this->handleError($response, $endpoint, $params);
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | POST REQUEST
    |--------------------------------------------------------------------------
    */

    protected function post(string $endpoint, array $payload = []): array
    {
        $payload['access_token'] = $this->accessToken;

        $this->debug('META_POST_REQUEST', [
            'endpoint' => $endpoint,
            'payload' => $payload
        ]);

        $response = $this->client()
            ->asForm()
            ->post("{$this->baseUrl}/{$endpoint}", $payload);

        if ($response->failed()) {
            $this->handleError($response, $endpoint, $payload);
        }

        $result = $response->json();

        $this->debug('META_POST_RESPONSE', $result);

        return $result;
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
            $this->handleError($response, $endpoint);
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

        $response = $this->get($endpoint, $params);

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

    public function createCampaign(array $data): array
    {
        $payload = [
            'name' => $data['name'],
            'objective' => $data['objective'],
            'status' => $data['status'] ?? 'PAUSED',
            'special_ad_categories' => json_encode([])
        ];

        return $this->post("{$this->adAccountId}/campaigns", $payload);
    }

    public function getCampaign(string $campaignId): array
    {
        return $this->get($campaignId, [
            'fields' => 'id,name,objective,status'
        ]);
        $params = array_merge([
        'fields' => 'id,name,objective,status,effective_status,created_time'
    ], $params);

    return $this->fetchAllPages("{$this->adAccountId}/campaigns", $params);
    }

    /*
    |--------------------------------------------------------------------------
    | ADSETS
    |--------------------------------------------------------------------------
    */

    public function createAdSet(string $accountId, array $data): array
    {
        $accountId = $this->formatAccount($accountId);

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

        if (isset($data['promoted_object'])) {
            $payload['promoted_object'] = json_encode($data['promoted_object']);
        }

        return $this->post("{$accountId}/adsets", $payload);
    }

    /*
    |--------------------------------------------------------------------------
    | ADS
    |--------------------------------------------------------------------------
    */

    public function createAd(string $accountId, array $data): array
    {
        $accountId = $this->formatAccount($accountId);

        $payload = [
            'name' => $data['name'],
            'adset_id' => $data['adset_id'],
            'status' => $data['status'] ?? 'PAUSED'
        ];

        if (isset($data['creative'])) {
            $payload['creative'] = json_encode($data['creative']);
        }

        return $this->post("{$accountId}/ads", $payload);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATIVES
    |--------------------------------------------------------------------------
    */

    public function createCreative(string $accountId, array $data): array
    {
        $accountId = $this->formatAccount($accountId);

        $payload = [
            'name' => $data['name'],
            'object_story_spec' => json_encode($data['object_story_spec'])
        ];

        return $this->post("{$accountId}/adcreatives", $payload);
    }

    /*
    |--------------------------------------------------------------------------
    | INTEREST SEARCH
    |--------------------------------------------------------------------------
    */

    public function searchInterests(string $query): array
    {
        try {

            $response = $this->get('search', [
                'type' => 'adinterest',
                'q' => $query,
                'limit' => 10
            ]);

            return $response['data'] ?? [];

        } catch (Exception $e) {

            Log::warning('META_INTEREST_SEARCH_FAILED', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CONNECTION TEST
    |--------------------------------------------------------------------------
    */

    public function checkConnection(): bool
    {
        try {

            $this->get('me');

            return true;

        } catch (Exception $e) {

            Log::error('META_CONNECTION_FAILED', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

}