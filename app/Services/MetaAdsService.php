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

            throw new \Exception($error);
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

            throw new \Exception($error);
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
            'fields' => 'id,name,account_status,currency'
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
            'fields' => 'id,name,status,objective,daily_budget,lifetime_budget'
        ]);
    }

    public function createCampaign(string $accountId, array $data): array
    {
        return $this->post("{$accountId}/campaigns", [
            'name' => $data['name'],
            'objective' => $data['objective'],
            'status' => $data['status'] ?? 'PAUSED',
            'daily_budget' => $data['daily_budget'],
            'special_ad_categories' => []
        ]);
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
        return $this->post("{$accountId}/adsets", [

            'name' => $data['name'],

            'campaign_id' => $data['campaign_id'],

            'daily_budget' => $data['daily_budget'],

            'billing_event' => 'IMPRESSIONS',

            'optimization_goal' => 'LINK_CLICKS',

            'targeting' => json_encode([

                'geo_locations' => [
                    'countries' => $data['countries'] ?? ['CA']
                ],

                'age_min' => $data['age_min'] ?? 18,
                'age_max' => $data['age_max'] ?? 65,

                'publisher_platforms' => [
                    'facebook',
                    'instagram',
                    'messenger'
                ]
            ]),

            'status' => 'PAUSED'
        ]);
    }

    public function getAdSets(): array
    {
        return $this->fetchAllPages("{$this->adAccountId}/adsets", [
            'fields' => 'id,name,status,campaign_id,daily_budget'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Creatives
    |--------------------------------------------------------------------------
    */

    public function createCreative(string $accountId, array $data): array
    {
        return $this->post("{$accountId}/adcreatives", [

            'name' => $data['name'],

            'object_story_spec' => json_encode([

                'page_id' => $data['page_id'],

                'link_data' => [

                    'message' => $data['message'],

                    'link' => $data['link'],

                    'call_to_action' => [

                        'type' => 'LEARN_MORE'

                    ]
                ]
            ])
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Ads
    |--------------------------------------------------------------------------
    */

    public function createAd(string $accountId, array $data): array
    {
        return $this->post("{$accountId}/ads", [

            'name' => $data['name'],

            'adset_id' => $data['adset_id'],

            'creative' => json_encode([
                'creative_id' => $data['creative_id']
            ]),

            'status' => 'PAUSED'
        ]);
    }

    public function getAds(): array
    {
        return $this->fetchAllPages("{$this->adAccountId}/ads", [
            'fields' => 'id,name,status,adset_id,creative'
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

            'fields' => 'campaign_name,impressions,clicks,spend,cpc,ctr,reach',

            'date_preset' => 'last_30d'

        ];

        return $this->fetchAllPages(
            "{$this->adAccountId}/insights",
            array_merge($default, $params)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Account Diagnostics
    |--------------------------------------------------------------------------
    */

    public function getAccountStatus(): array
    {
        return $this->request($this->adAccountId, [
            'fields' => 'account_status,spend_cap,balance'
        ]);
    }
}