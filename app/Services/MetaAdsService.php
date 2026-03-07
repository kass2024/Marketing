<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class MetaAdsService
{
    protected string $baseUrl;
    protected string $accessToken;
    protected string $defaultAccount;
    protected bool $debug;

    public function __construct()
    {
        $version = config('services.meta.graph_version', 'v19.0');

        $this->baseUrl = "https://graph.facebook.com/{$version}";
        $this->accessToken = config('services.meta.token');
        $this->defaultAccount = $this->formatAccount(config('services.meta.ad_account_id'));
        $this->debug = config('app.debug', false);

        if (!$this->accessToken) {
            throw new Exception('Meta access token missing in config/services.php');
        }

        Log::info('META_SERVICE_INITIALIZED', [
            'account' => $this->defaultAccount,
            'graph_version' => $version
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT ACCOUNT
    |--------------------------------------------------------------------------
    */

    protected function formatAccount(string $id): string
    {
        return str_starts_with($id, 'act_') ? $id : "act_{$id}";
    }

    /*
    |--------------------------------------------------------------------------
    | HTTP CLIENT
    |--------------------------------------------------------------------------
    */

    protected function client()
    {
        return Http::timeout(30)
            ->retry(3, 500)
            ->acceptJson();
    }

    /*
    |--------------------------------------------------------------------------
    | ERROR HANDLER
    |--------------------------------------------------------------------------
    */

    protected function handleError($response, $endpoint, $payload = [])
    {
        $body = $response->json();

        Log::error('META_API_ERROR', [
            'endpoint' => $endpoint,
            'payload' => $payload,
            'response' => $body
        ]);

        $message = $body['error']['message'] ?? 'Meta API Error';

        throw new Exception($message);
    }

    /*
    |--------------------------------------------------------------------------
    | GET REQUEST
    |--------------------------------------------------------------------------
    */

    protected function get(string $endpoint, array $params = []): array
    {
        $params['access_token'] = $this->accessToken;

        Log::info('META_GET_REQUEST', [
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
    | POST REQUEST (FORM-DATA LIKE CURL)
    |--------------------------------------------------------------------------
    */

    protected function post(string $endpoint, array $payload = []): array
    {
        $payload['access_token'] = $this->accessToken;

        Log::info('META_POST_REQUEST', [
            'endpoint' => $endpoint,
            'payload' => $payload
        ]);

        $response = $this->client()
            ->asForm()
            ->post("{$this->baseUrl}/{$endpoint}", $payload);

        if ($response->failed()) {
            $this->handleError($response, $endpoint, $payload);
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | FETCH PAGES
    |--------------------------------------------------------------------------
    */

    public function getPages(): array
    {
        $res = $this->get("me/accounts");

        return $res['data'] ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE CAMPAIGN
    |--------------------------------------------------------------------------
    */

    public function createCampaign(string $accountId, array $data): array
    {
        $accountId = $this->formatAccount($accountId);

        $payload = [

            'name' => $data['name'],

            'objective' => $data['objective'],

            'status' => $data['status'] ?? 'PAUSED',

            'special_ad_categories' => json_encode(['NONE']),

            'is_adset_budget_sharing_enabled' => false
        ];

        Log::info('META_CAMPAIGN_PAYLOAD', $payload);

        return $this->post("{$accountId}/campaigns", $payload);
    }

    /*
    |--------------------------------------------------------------------------
    | CAMPAIGN STATUS
    |--------------------------------------------------------------------------
    */

    public function activateCampaign(string $campaignId): array
    {
        return $this->post($campaignId, ['status' => 'ACTIVE']);
    }

    public function pauseCampaign(string $campaignId): array
    {
        return $this->post($campaignId, ['status' => 'PAUSED']);
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD TARGETING
    |--------------------------------------------------------------------------
    */

    protected function buildTargeting(array $targeting): string
    {
        /*
        Remove locales if single country
        */

        if (
            isset($targeting['geo_locations']['countries']) &&
            count($targeting['geo_locations']['countries']) === 1
        ) {
            unset($targeting['locales']);
        }

        /*
        Advantage Audience required when interests used
        */

        if (isset($targeting['flexible_spec'])) {

            $targeting['targeting_automation'] = [
                'advantage_audience' => 0
            ];
        }

        Log::info('META_TARGETING_FINAL', $targeting);

        return json_encode($targeting);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE ADSET
    |--------------------------------------------------------------------------
    */

    public function createAdSet(string $accountId, array $data): array
    {
        $accountId = $this->formatAccount($accountId);

        if (!isset($data['campaign_id'])) {
            throw new Exception('campaign_id required');
        }

        if (!isset($data['targeting'])) {
            throw new Exception('targeting required');
        }

        $payload = [

            'name' => $data['name'],

            'campaign_id' => $data['campaign_id'],

            'daily_budget' => $data['daily_budget'],

            'billing_event' => $data['billing_event'] ?? 'IMPRESSIONS',

            'optimization_goal' => $data['optimization_goal'] ?? 'LINK_CLICKS',

            'bid_strategy' => $data['bid_strategy'] ?? 'LOWEST_COST_WITHOUT_CAP',

            'status' => $data['status'] ?? 'PAUSED',

            'start_time' => $data['start_time'] ?? now()->addMinutes(5)->toIso8601String(),

            'targeting' => $this->buildTargeting($data['targeting'])
        ];

        if (isset($data['promoted_object'])) {
            $payload['promoted_object'] = json_encode($data['promoted_object']);
        }

        Log::info('META_ADSET_PAYLOAD', $payload);

        return $this->post("{$accountId}/adsets", $payload);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE CREATIVE
    |--------------------------------------------------------------------------
    */

    public function createCreative(string $accountId, array $data): array
    {
        $accountId = $this->formatAccount($accountId);

        $payload = [

            'name' => $data['name'],

            'object_story_spec' => json_encode($data['object_story_spec'])
        ];

        Log::info('META_CREATIVE_PAYLOAD', $payload);

        return $this->post("{$accountId}/adcreatives", $payload);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE AD
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

        Log::info('META_CREATE_AD_PAYLOAD', $payload);

        return $this->post("{$accountId}/ads", $payload);
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