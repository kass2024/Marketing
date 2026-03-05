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

        if (empty($this->accessToken)) {
            throw new Exception('Meta access token is missing in config/services.php');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Generic GET Request
    |--------------------------------------------------------------------------
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

            throw new Exception($error);
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | Generic POST Request
    |--------------------------------------------------------------------------
    */

    protected function post(string $endpoint, array $params = []): array
    {
        $response = Http::timeout(30)
            ->retry(2, 500)
            ->asForm()
            ->post("{$this->baseUrl}/{$endpoint}", array_merge($params, [
                'access_token' => $this->accessToken
            ]));

        if ($response->failed()) {

            Log::error('Meta POST Error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            $error = $response->json()['error']['message'] ?? 'Meta API POST failed';

            throw new Exception($error);
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | Generic DELETE Request
    |--------------------------------------------------------------------------
    */

    protected function delete(string $endpoint): array
    {
        $response = Http::timeout(30)
            ->retry(2, 500)
            ->delete("{$this->baseUrl}/{$endpoint}", [
                'access_token' => $this->accessToken
            ]);

        if ($response->failed()) {

            Log::error('Meta DELETE Error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            $error = $response->json()['error']['message'] ?? 'Meta API DELETE failed';

            throw new Exception($error);
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | Pagination handler
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

            $nextUrl = $response['paging']['next'];

            $response = Http::timeout(30)
                ->retry(2, 500)
                ->get($nextUrl)
                ->json();
        }

        return ['data' => $results];
    }

    /*
    |--------------------------------------------------------------------------
    | Ad Accounts
    |--------------------------------------------------------------------------
    */

    public function getAdAccounts(): array
    {
        return $this->fetchAllPages('me/adaccounts', [
            'fields' => 'id,name,account_status,currency,balance,spend_cap'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Campaigns
    |--------------------------------------------------------------------------
    */

    public function getCampaigns(): array
    {
        return $this->fetchAllPages("{$this->adAccountId}/campaigns", [
            'fields' => 'id,name,status,objective,daily_budget,lifetime_budget,created_time,start_time,stop_time'
        ]);
    }

    public function getCampaign(string $campaignId): array
    {
        return $this->request($campaignId, [
            'fields' => 'id,name,status,objective,daily_budget,lifetime_budget,created_time,start_time,stop_time'
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

        // Add budget if provided
        if (isset($data['daily_budget'])) {
            $payload['daily_budget'] = $data['daily_budget'];
        }

        if (isset($data['lifetime_budget'])) {
            $payload['lifetime_budget'] = $data['lifetime_budget'];
        }

        return $this->post("{$accountId}/campaigns", $payload);
    }

    public function updateCampaign(string $campaignId, array $data): array
    {
        return $this->post($campaignId, $data);
    }

    public function deleteCampaign(string $campaignId): array
    {
        return $this->delete($campaignId);
    }

    public function pauseCampaign(string $campaignId): array
    {
        return $this->post($campaignId, [
            'status' => 'PAUSED'
        ]);
    }

    public function activateCampaign(string $campaignId): array
    {
        return $this->post($campaignId, [
            'status' => 'ACTIVE'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Ad Sets (TARGETING)
    |--------------------------------------------------------------------------
    */

    public function createAdSet(string $accountId, array $data): array
    {
        $payload = [
            'name' => $data['name'],
            'campaign_id' => $data['campaign_id'],
            'billing_event' => $data['billing_event'] ?? 'IMPRESSIONS',
            'optimization_goal' => $data['optimization_goal'] ?? 'REACH',
            'targeting' => is_string($data['targeting']) ? $data['targeting'] : json_encode($data['targeting']),
            'status' => $data['status'] ?? 'PAUSED',
        ];

        // Add budget
        if (isset($data['daily_budget'])) {
            $payload['daily_budget'] = $data['daily_budget'];
        }

        // Add bid strategy
        if (isset($data['bid_strategy'])) {
            $payload['bid_strategy'] = $data['bid_strategy'];
        }

        // Add bid amount if present
        if (isset($data['bid_amount'])) {
            $payload['bid_amount'] = $data['bid_amount'];
        }

        // Add schedule
        if (isset($data['start_time'])) {
            $payload['start_time'] = $data['start_time'];
        }

        if (isset($data['end_time'])) {
            $payload['end_time'] = $data['end_time'];
        }

        // Add promoted object
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
            'fields' => 'id,name,status,campaign_id,daily_budget,bid_strategy,bid_amount,targeting,created_time,start_time,stop_time'
        ]);
    }

    public function getAdSet(string $adSetId): array
    {
        return $this->request($adSetId, [
            'fields' => 'id,name,status,campaign_id,daily_budget,bid_strategy,bid_amount,targeting,created_time,start_time,stop_time,optimization_goal,billing_event,promoted_object'
        ]);
    }

    public function updateAdSet(string $adSetId, array $data): array
    {
        $payload = [];

        // Only include fields that are present in the update
        if (isset($data['name'])) {
            $payload['name'] = $data['name'];
        }

        if (isset($data['daily_budget'])) {
            $payload['daily_budget'] = $data['daily_budget'];
        }

        if (isset($data['status'])) {
            $payload['status'] = $data['status'];
        }

        if (isset($data['targeting'])) {
            $payload['targeting'] = is_string($data['targeting']) 
                ? $data['targeting'] 
                : json_encode($data['targeting']);
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

        if (isset($data['optimization_goal'])) {
            $payload['optimization_goal'] = $data['optimization_goal'];
        }

        if (isset($data['billing_event'])) {
            $payload['billing_event'] = $data['billing_event'];
        }

        return $this->post($adSetId, $payload);
    }

    public function deleteAdSet(string $adSetId): array
    {
        return $this->delete($adSetId);
    }

    public function getAdSetInsights(string $adSetId, array $params = []): array
    {
        $defaultParams = [
            'fields' => 'impressions,clicks,spend,cpc,ctr,reach,cpm,actions',
            'date_preset' => 'last_30d',
            'level' => 'adset'
        ];

        $mergedParams = array_merge($defaultParams, $params);

        return $this->request("{$adSetId}/insights", $mergedParams);
    }

    /*
    |--------------------------------------------------------------------------
    | Creatives
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

        if (isset($data['degrees_of_freedom_spec'])) {
            $payload['degrees_of_freedom_spec'] = is_string($data['degrees_of_freedom_spec'])
                ? $data['degrees_of_freedom_spec']
                : json_encode($data['degrees_of_freedom_spec']);
        }

        if (isset($data['image_url'])) {
            $payload['image_url'] = $data['image_url'];
        }

        if (isset($data['object_story_id'])) {
            $payload['object_story_id'] = $data['object_story_id'];
        }

        if (isset($data['template_url'])) {
            $payload['template_url'] = $data['template_url'];
        }

        return $this->post("{$accountId}/adcreatives", $payload);
    }

    public function getCreatives(string $accountId): array
    {
        return $this->fetchAllPages("{$accountId}/adcreatives", [
            'fields' => 'id,name,object_story_spec,status'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Ads
    |--------------------------------------------------------------------------
    */

    public function createAd(string $accountId, array $data): array
    {
        $payload = [
            'name' => $data['name'],
            'adset_id' => $data['adset_id'],
            'status' => $data['status'] ?? 'PAUSED',
        ];

        if (isset($data['creative'])) {
            $payload['creative'] = is_string($data['creative']) 
                ? $data['creative'] 
                : json_encode($data['creative']);
        }

        return $this->post("{$accountId}/ads", $payload);
    }

    public function getAds(): array
    {
        return $this->fetchAllPages("{$this->adAccountId}/ads", [
            'fields' => 'id,name,status,adset_id,creative,created_time'
        ]);
    }

    public function getAd(string $adId): array
    {
        return $this->request($adId, [
            'fields' => 'id,name,status,adset_id,creative,created_time'
        ]);
    }

    public function updateAd(string $adId, array $data): array
    {
        return $this->post($adId, $data);
    }

    public function deleteAd(string $adId): array
    {
        return $this->delete($adId);
    }

    public function pauseAd(string $adId): array
    {
        return $this->post($adId, [
            'status' => 'PAUSED'
        ]);
    }

    public function activateAd(string $adId): array
    {
        return $this->post($adId, [
            'status' => 'ACTIVE'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Insights (Analytics)
    |--------------------------------------------------------------------------
    */

    public function getInsights(array $params = []): array
    {
        $default = [
            'fields' => 'campaign_name,adset_name,impressions,clicks,spend,cpc,ctr,reach,cpm,actions',
            'date_preset' => 'last_30d',
            'level' => 'account'
        ];

        return $this->fetchAllPages(
            "{$this->adAccountId}/insights",
            array_merge($default, $params)
        );
    }

    public function getCampaignInsights(string $campaignId, array $params = []): array
    {
        $default = [
            'fields' => 'impressions,clicks,spend,cpc,ctr,reach,cpm,actions',
            'date_preset' => 'last_30d',
            'level' => 'campaign'
        ];

        return $this->request("{$campaignId}/insights", array_merge($default, $params));
    }

    /*
    |--------------------------------------------------------------------------
    | Targeting & Interest Search
    |--------------------------------------------------------------------------
    */

    /**
     * Search for interests by keyword
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
        } catch (Exception $e) {
            Log::warning('Interest search failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Validate a single interest ID
     */
    public function validateInterest(string $interestId): ?array
    {
        try {
            $response = $this->request('search', [
                'type' => 'adinterest',
                'interest_list' => json_encode([$interestId]),
                'limit' => 1
            ]);

            return $response['data'][0] ?? null;
        } catch (Exception $e) {
            Log::warning('Interest validation failed', [
                'interest_id' => $interestId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Validate multiple interest IDs
     */
    public function validateInterests(array $interestIds): array
    {
        $valid = [];
        $invalid = [];

        foreach ($interestIds as $id) {
            $interest = $this->validateInterest($id);
            if ($interest) {
                $valid[] = $interest;
            } else {
                $invalid[] = $id;
            }
        }

        return [
            'valid' => $valid,
            'invalid' => $invalid
        ];
    }

    /**
     * Get targeting options (for building dropdowns)
     */
    public function getTargetingOptions(string $type, string $query = ''): array
    {
        $validTypes = ['interests', 'behaviors', 'life_events', 'industries'];
        
        if (!in_array($type, $validTypes)) {
            return [];
        }

        try {
            $response = $this->request('search', [
                'type' => 'ad' . $type,
                'q' => $query,
                'limit' => 10
            ]);

            return $response['data'] ?? [];
        } catch (Exception $e) {
            Log::warning('Targeting options search failed', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Audience Size Estimation
    |--------------------------------------------------------------------------
    */

    /**
     * Estimate audience size based on targeting criteria
     */
    public function estimateAudience(array $targeting): array
    {
        try {
            $response = $this->post('act_' . $this->adAccountId . '/reachestimate', [
                'targeting_spec' => is_string($targeting) ? $targeting : json_encode($targeting)
            ]);

            return [
                'users' => $response['users'] ?? 0,
                'estimate_ready' => $response['estimate_ready'] ?? false,
                'users_lower_bound' => $response['users_lower_bound'] ?? 0,
                'users_upper_bound' => $response['users_upper_bound'] ?? 0
            ];
        } catch (Exception $e) {
            Log::warning('Audience estimation failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'users' => 0,
                'estimate_ready' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Account Diagnostics
    |--------------------------------------------------------------------------
    */

    public function getAccountStatus(): array
    {
        return $this->request($this->adAccountId, [
            'fields' => 'account_status,spend_cap,balance,currency,disable_reason,min_daily_budget'
        ]);
    }

    public function getAccountSpend(): array
    {
        return $this->request("{$this->adAccountId}/insights", [
            'fields' => 'spend',
            'date_preset' => 'this_month'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Utility Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if API is accessible
     */
    public function checkConnection(): bool
    {
        try {
            $this->request('me');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Format budget for Meta API (convert to cents/100)
     */
    public function formatBudget(float $budget): int
    {
        return (int)($budget * 100);
    }

    /**
     * Parse Meta API error response
     */
    public function parseError(array $error): string
    {
        $code = $error['code'] ?? 0;
        $subcode = $error['error_subcode'] ?? 0;
        $message = $error['message'] ?? 'Unknown error';

        $errorMap = [
            100 => 'Invalid parameter',
            200 => 'Permission error',
            190 => 'Invalid access token',
            1815857 => 'Targeting conflict - incompatible options selected',
            1815855 => 'Audience too narrow',
            1815862 => 'Invalid interest ID',
            368 => 'Temporary server error, please retry',
        ];

        if (isset($errorMap[$subcode])) {
            return $errorMap[$subcode];
        }

        if (isset($errorMap[$code])) {
            return $errorMap[$code];
        }

        return $message;
    }
}