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
    | ACCOUNT FORMAT
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
        $status = $response->status();
        $body = $response->json();

        Log::error('META_API_ERROR_FULL', [
            'endpoint' => $endpoint,
            'status_code' => $status,
            'request_payload' => $payload,
            'response' => $body
        ]);

        $error = $body['error'] ?? [];

        $message = $error['message'] ?? 'Unknown Meta API error';
        $code = $error['code'] ?? 0;

        throw new Exception("Meta API Error: {$message} (code: {$code})");
    }

    /*
    |--------------------------------------------------------------------------
    | GET
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
    | POST
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
    | CREATE CAMPAIGN
    |--------------------------------------------------------------------------
    */

    public function createCampaign(string $accountId, array $data): array
    {
        $accountId = $this->formatAccount($accountId);

        Log::info('META_CAMPAIGN_DATA_RECEIVED', $data);

        $payload = [

            'name' => $data['name'],

            'objective' => $data['objective'],

            'status' => $data['status'] ?? 'PAUSED',

            /*
            Meta requires this field even if no special category.
            Using NONE ensures compatibility with all ad accounts.
            */

            'special_ad_categories' => json_encode(['NONE'])
        ];

        Log::info('META_CAMPAIGN_PAYLOAD', $payload);

        return $this->post("{$accountId}/campaigns", $payload);
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVATE / PAUSE CAMPAIGN
    |--------------------------------------------------------------------------
    */

    public function activateCampaign(string $campaignId): array
    {
        return $this->post($campaignId, [
            'status' => 'ACTIVE'
        ]);
    }

    public function pauseCampaign(string $campaignId): array
    {
        return $this->post($campaignId, [
            'status' => 'PAUSED'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | OBJECTIVE → OPTIMIZATION GOAL
    |--------------------------------------------------------------------------
    */

    protected function resolveOptimization(string $objective): string
    {
        return match ($objective) {

            'OUTCOME_TRAFFIC' => 'LINK_CLICKS',

            'OUTCOME_LEADS' => 'LEAD_GENERATION',

            'OUTCOME_ENGAGEMENT' => 'POST_ENGAGEMENT',

            'OUTCOME_AWARENESS' => 'REACH',

            'OUTCOME_SALES' => 'CONVERSIONS',

            default => 'REACH'
        };
    }

    /*
    |--------------------------------------------------------------------------
    | TARGETING VALIDATION
    |--------------------------------------------------------------------------
    */

    protected function validateTargeting(array $payload): array
    {
        if (!isset($payload['targeting'])) {
            return $payload;
        }

        $targeting = $payload['targeting'];

        /*
        Remove language targeting when only one country
        */

        if (
            isset($targeting['geo_locations']['countries']) &&
            count($targeting['geo_locations']['countries']) === 1
        ) {
            unset($targeting['locales']);
        }

        /*
        Encode targeting as JSON for Meta API
        */

        $payload['targeting'] = json_encode($targeting);

        Log::info('META_TARGETING_VALIDATED', $targeting);

        return $payload;
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

        $optimization = $data['optimization_goal']
            ?? $this->resolveOptimization(
                $data['objective'] ?? 'OUTCOME_AWARENESS'
            );

        $payload = [

            'name' => $data['name'],

            'campaign_id' => $data['campaign_id'],

            'billing_event' => $data['billing_event'] ?? 'IMPRESSIONS',

            'optimization_goal' => $optimization,

            'status' => $data['status'] ?? 'PAUSED',

            'start_time' => $data['start_time']
                ?? now()->addMinutes(5)->toIso8601String(),

            'targeting' => $data['targeting']
        ];

        if (isset($data['daily_budget'])) {
            $payload['daily_budget'] = $data['daily_budget'];
        }

        $payload = $this->validateTargeting($payload);

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