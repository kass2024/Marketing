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

        Log::info('META_SERVICE_INITIALIZED', [
            'account' => $this->adAccountId,
            'graph_version' => $version
        ]);
    }

    protected function formatAccount(string $id): string
    {
        return str_starts_with($id, 'act_') ? $id : "act_{$id}";
    }

    protected function client()
    {
        return Http::timeout(30)
            ->retry(2, 500)
            ->acceptJson();
    }

    protected function debug(string $title, array $data = [])
    {
        if ($this->debug) {
            Log::info($title, $data);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ERROR HANDLER
    |--------------------------------------------------------------------------
    */

    protected function handleError($response, $endpoint, $payload = [])
    {
        $status = $response->status();
        $headers = $response->headers();
        $rawBody = $response->body();
        $jsonBody = $response->json();

        Log::error('META_API_ERROR_FULL', [
            'endpoint' => $endpoint,
            'status_code' => $status,
            'request_payload_array' => $payload,
            'request_payload_json' => json_encode($payload, JSON_PRETTY_PRINT),
            'response_headers' => $headers,
            'response_raw' => $rawBody,
            'response_json' => $jsonBody
        ]);

        $error = $jsonBody['error'] ?? [];

        $message = $error['message'] ?? 'Unknown Meta API error';
        $code = $error['code'] ?? 0;
        $subcode = $error['error_subcode'] ?? 0;

        Log::error('META_API_ERROR_PARSED', [
            'message' => $message,
            'code' => $code,
            'subcode' => $subcode
        ]);

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

        Log::info('META_GET_REQUEST_FULL', [
            'endpoint' => $endpoint,
            'params_array' => $params,
            'params_json' => json_encode($params, JSON_PRETTY_PRINT)
        ]);

        $start = microtime(true);

        $response = $this->client()->get("{$this->baseUrl}/{$endpoint}", $params);

        Log::info('META_GET_HTTP_RESULT', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body_raw' => $response->body(),
            'duration_ms' => round((microtime(true) - $start) * 1000, 2)
        ]);

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

        $start = microtime(true);

        Log::info('META_POST_REQUEST_FULL', [
            'endpoint' => $endpoint,
            'payload_array' => $payload,
            'payload_json' => json_encode($payload, JSON_PRETTY_PRINT)
        ]);

        $response = $this->client()
            ->asForm()
            ->post("{$this->baseUrl}/{$endpoint}", $payload);

        Log::info('META_POST_HTTP_RESULT', [
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body_raw' => $response->body(),
            'duration_ms' => round((microtime(true) - $start) * 1000, 2)
        ]);

        if ($response->failed()) {
            $this->handleError($response, $endpoint, $payload);
        }

        $result = $response->json();

        Log::info('META_POST_RESPONSE_FULL', [
            'endpoint' => $endpoint,
            'response_json' => $result
        ]);

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | TARGETING VALIDATOR (NEW)
    |--------------------------------------------------------------------------
    */

    protected function validateTargeting(array $payload): array
    {
        if (!isset($payload['targeting'])) {
            return $payload;
        }

        $targeting = $payload['targeting'];
        $objective = $payload['optimization_goal'] ?? 'REACH';

        /*
        |--------------------------------------------------------------------------
        | REACH VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($objective === 'REACH') {

            unset($targeting['interests']);
            unset($targeting['behaviors']);
            unset($targeting['flexible_spec']);

        }

        /*
        |--------------------------------------------------------------------------
        | LANGUAGE VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            isset($targeting['geo_locations']['countries']) &&
            count($targeting['geo_locations']['countries']) === 1
        ) {
            unset($targeting['locales']);
        }

        $payload['targeting'] = $targeting;

        Log::info('META_TARGETING_OBJECTIVE_VALIDATED', [
            'objective' => $objective,
            'targeting' => $targeting
        ]);

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

        Log::info('META_ADSET_DATA_RECEIVED', $data);

        if (!isset($data['campaign_id'])) {
            throw new Exception("campaign_id is required");
        }

        if (!isset($data['targeting'])) {
            throw new Exception("targeting is required");
        }

        Log::info('META_TARGETING_RAW', $data['targeting']);

        $payload = [

            'name' => $data['name'],

            'campaign_id' => $data['campaign_id'],

            'billing_event' => $data['billing_event'] ?? 'IMPRESSIONS',

            'optimization_goal' => $data['optimization_goal'] ?? 'REACH',

            'status' => $data['status'] ?? 'PAUSED',

            'targeting' => $data['targeting']

        ];

        if (isset($data['daily_budget'])) {
            $payload['daily_budget'] = $data['daily_budget'];
        }

        $payload['start_time'] =
            $data['start_time'] ?? now()->addMinutes(5)->toIso8601String();

        /*
        |--------------------------------------------------------------------------
        | VALIDATE TARGETING BEFORE META
        |--------------------------------------------------------------------------
        */

        $payload = $this->validateTargeting($payload);

        Log::info('META_ADSET_PAYLOAD_VALIDATED_FULL', [
            'payload_array' => $payload,
            'payload_json' => json_encode($payload, JSON_PRETTY_PRINT)
        ]);

        return $this->post("{$accountId}/adsets", $payload);
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

        Log::info('META_CREATE_CREATIVE_PAYLOAD', $payload);

        return $this->post("{$accountId}/adcreatives", $payload);
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