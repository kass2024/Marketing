<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use Throwable;

class MetaAdsService
{
    protected string $baseUrl;
    protected string $accessToken;
    protected string $defaultAccount;
    protected bool $debug;

    public function __construct()
    {
        $version = config('services.meta.graph_version','v19.0');

        $this->baseUrl = "https://graph.facebook.com/{$version}";
        $this->accessToken = config('services.meta.token');
        $this->defaultAccount = $this->formatAccount(
            config('services.meta.ad_account_id')
        );

        $this->debug = config('app.debug',false);

        if(!$this->accessToken){
            throw new Exception('Meta access token missing in config/services.php');
        }

        Log::info('META_SERVICE_INITIALIZED',[
            'account'=>$this->defaultAccount,
            'graph_version'=>$version
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT ACCOUNT
    |--------------------------------------------------------------------------
    */

    protected function formatAccount(?string $id): string
    {
        if(!$id){
            throw new Exception('Meta Ad Account ID missing.');
        }

        return str_starts_with($id,'act_') ? $id : "act_{$id}";
    }

    /*
    |--------------------------------------------------------------------------
    | HTTP CLIENT
    |--------------------------------------------------------------------------
    */

    protected function client()
    {
        $timeout = (int) config('services.meta.http_timeout', 90);
        $connectTimeout = (int) config('services.meta.http_connect_timeout', 45);

        return Http::timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->retry(4, 2000)
            ->acceptJson();
    }

    /*
    |--------------------------------------------------------------------------
    | HANDLE ERROR
    |--------------------------------------------------------------------------
    */
protected function handleError($response, $endpoint, $payload = [])
{
    /*
    |--------------------------------------------------------------------------
    | Parse Response Safely
    |--------------------------------------------------------------------------
    */

    $body = null;

    try {
        $body = $response->json();
    } catch (\Throwable $e) {
        $body = $response->body();
    }

    /*
    |--------------------------------------------------------------------------
    | Extract Error Message Safely
    |--------------------------------------------------------------------------
    */

    $message = 'Meta API Error';

    if (is_array($body) && isset($body['error']['message'])) {
        $message = $body['error']['message'];
        if (! empty($body['error']['error_user_msg'])) {
            $message .= ' — ' . $body['error']['error_user_msg'];
        }
        if (! empty($body['error']['error_subcode'])) {
            $message .= ' (Meta subcode ' . $body['error']['error_subcode'] . ')';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Log Detailed Meta Error
    |--------------------------------------------------------------------------
    */

    Log::error('META_API_ERROR', [

        'endpoint' => $endpoint,

        'http_status' => $response->status(),

        'payload' => $payload,

        'response' => $body,

        'meta_error_code' => $body['error']['code'] ?? null,

        'meta_error_type' => $body['error']['type'] ?? null

    ]);

    /*
    |--------------------------------------------------------------------------
    | Throw Exception
    |--------------------------------------------------------------------------
    */

    throw new Exception($message);
}

    /*
    |--------------------------------------------------------------------------
    | BASE REQUEST
    |--------------------------------------------------------------------------
    */

    protected function request(string $method,string $endpoint,array $payload=[],bool $asForm=true)
    {
        $payload['access_token'] = $this->accessToken;

        Log::info("META_API_{$method}",[
            'endpoint'=>$endpoint,
            'payload'=>$payload
        ]);

        $client = $this->client();

        if($asForm){
            $client = $client->asForm();
        }

        $response = $client->{$method}(
            "{$this->baseUrl}/{$endpoint}",
            $payload
        );

        if($response->failed()){
            $this->handleError($response,$endpoint,$payload);
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | GET
    |--------------------------------------------------------------------------
    */

    protected function get(string $endpoint,array $params=[]):array
    {
        return $this->request('get',$endpoint,$params,false);
    }

    /*
    |--------------------------------------------------------------------------
    | POST
    |--------------------------------------------------------------------------
    */

    protected function post(string $endpoint,array $payload=[]):array
    {
        return $this->request('post',$endpoint,$payload,true);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    protected function delete(string $endpoint):array
    {
        return $this->request('delete',$endpoint,[],false);
    }

    /*
    |--------------------------------------------------------------------------
    | CONNECTION TEST
    |--------------------------------------------------------------------------
    */

    public function checkConnection():bool
    {
        try{
            $this->get('me');
            return true;
        }
        catch(Exception $e){

            Log::error('META_CONNECTION_FAILED',[
                'error'=>$e->getMessage()
            ]);

            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET PAGES
    |--------------------------------------------------------------------------
    */

    public function getPages(): array
    {
        try {
            $res = $this->get('me/accounts');
            $data = $res['data'] ?? [];
            if ($data !== []) {
                return $data;
            }
        } catch (Throwable $e) {
            Log::warning('META_GET_PAGES_FAILED', [
                'message' => $e->getMessage(),
            ]);
        }

        $pageId = config('services.meta.page_id');
        if (! empty($pageId)) {
            Log::info('META_GET_PAGES_USING_CONFIG_FALLBACK', [
                'page_id' => $pageId,
            ]);

            return [
                [
                    'id' => (string) $pageId,
                    'name' => (string) config('services.meta.page_name', 'Facebook Page'),
                ],
            ];
        }

        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | CAMPAIGN
    |--------------------------------------------------------------------------
    */

    public function createCampaign(string $accountId,array $data):array
    {
        $accountId = $this->formatAccount($accountId);

        $payload = [

            'name'=>$data['name'],

            'objective'=>$data['objective'],

            'status'=>$data['status'] ?? 'PAUSED',

            'special_ad_categories'=>json_encode(['NONE']),

            'is_adset_budget_sharing_enabled'=>false
        ];

        Log::info('META_CAMPAIGN_PAYLOAD',$payload);

        return $this->post("{$accountId}/campaigns",$payload);
    }

    public function updateCampaign(string $campaignId,array $data):array
    {
        return $this->post($campaignId,$data);
    }

    public function deleteCampaign(string $campaignId):array
    {
        return $this->delete($campaignId);
    }

    /*
    |--------------------------------------------------------------------------
    | TARGETING BUILDER
    |--------------------------------------------------------------------------
    */

protected function buildTargeting(array $targeting): array
{
    unset($targeting['locales']);

    if (! empty($targeting['geo_locations'])) {
        $targeting['geo_locations'] = $this->normalizeGeoLocationsForApi(
            $targeting['geo_locations']
        );
    }

    if (! empty($targeting['flexible_spec'])) {
        $targeting = $this->sanitizeFlexibleSpec($targeting);
    }

    if (! empty($targeting['publisher_platforms'])) {
        $targeting = $this->enrichPlacementTargeting($targeting);
    }

    Log::info('META_TARGETING_FINAL', $targeting);

    return $targeting;
}

    /**
     * Build geo_locations from country codes and optional city entries.
     * Countries with selected cities are targeted at city level only.
     */
    public function buildGeoLocations(array $selectedCountries, array $selectedCities = []): array
    {
        $countries = array_values(array_unique(array_map(
            fn ($code) => strtoupper(trim((string) $code)),
            $selectedCountries
        )));

        if ($countries === []) {
            throw new Exception('At least one country is required.');
        }

        $citiesByCountry = [];

        foreach ($selectedCities as $city) {
            if (! is_array($city)) {
                continue;
            }

            $key = trim((string) ($city['key'] ?? ''));
            $country = strtoupper(trim((string) ($city['country'] ?? '')));

            if ($key === '' || $country === '') {
                continue;
            }

            $entry = ['key' => $key];

            if (! empty($city['name'])) {
                $entry['name'] = (string) $city['name'];
            }

            if (! empty($city['region'])) {
                $entry['region'] = (string) $city['region'];
            }

            if (! empty($city['region_id'])) {
                $entry['region_id'] = (int) $city['region_id'];
            }

            $entry['country'] = $country;
            $citiesByCountry[$country][] = $entry;
        }

        $geo = [
            'countries' => [],
            'cities' => [],
        ];

        foreach ($countries as $country) {
            if (! empty($citiesByCountry[$country])) {
                $geo['cities'] = array_merge($geo['cities'], $citiesByCountry[$country]);
                continue;
            }

            $geo['countries'][] = $country;
        }

        if ($geo['countries'] === []) {
            unset($geo['countries']);
        }

        if ($geo['cities'] === []) {
            unset($geo['cities']);
        }

        if (! isset($geo['countries']) && ! isset($geo['cities'])) {
            throw new Exception('At least one valid country or city is required.');
        }

        return $geo;
    }

    /**
     * Meta only needs city keys in API payloads; strip extra metadata safely.
     */
    protected function normalizeGeoLocationsForApi(array $geoLocations): array
    {
        if (! empty($geoLocations['countries']) && is_array($geoLocations['countries'])) {
            $geoLocations['countries'] = array_values(array_unique(array_map(
                fn ($code) => strtoupper(trim((string) $code)),
                $geoLocations['countries']
            )));
        }

        if (! empty($geoLocations['cities']) && is_array($geoLocations['cities'])) {
            $geoLocations['cities'] = array_values(array_map(function ($city) {
                $key = is_array($city)
                    ? trim((string) ($city['key'] ?? ''))
                    : trim((string) $city);

                if ($key === '') {
                    return null;
                }

                return ['key' => $key];
            }, $geoLocations['cities']));

            $geoLocations['cities'] = array_values(array_filter($geoLocations['cities']));

            if ($geoLocations['cities'] === []) {
                unset($geoLocations['cities']);
            }
        }

        return $geoLocations;
    }

    /**
     * Remove deprecated or invalid interest IDs (Meta subcode 2446394/2446395).
     */
    protected function sanitizeFlexibleSpec(array $targeting): array
    {
        if (empty($targeting['flexible_spec']) || ! is_array($targeting['flexible_spec'])) {
            unset($targeting['flexible_spec']);
            return $targeting;
        }

        $interestIds = [];

        foreach ($targeting['flexible_spec'] as $spec) {
            foreach ($spec['interests'] ?? [] as $interest) {
                $id = trim((string) ($interest['id'] ?? ''));
                if ($id !== '') {
                    $interestIds[] = $id;
                }
            }
        }

        $interestIds = array_values(array_unique($interestIds));

        if ($interestIds === []) {
            unset($targeting['flexible_spec']);
            return $targeting;
        }

        $validIds = $this->validateInterestIds($interestIds);

        if ($validIds === []) {
            Log::warning('META_INTERESTS_REMOVED', [
                'removed' => $interestIds,
            ]);
            unset($targeting['flexible_spec']);
            return $targeting;
        }

        if (count($validIds) !== count($interestIds)) {
            Log::warning('META_INTERESTS_FILTERED', [
                'requested' => $interestIds,
                'valid' => $validIds,
            ]);
        }

        $targeting['flexible_spec'] = [[
            'interests' => array_map(
                fn ($id) => ['id' => (string) $id],
                $validIds
            ),
        ]];

        return $targeting;
    }

    /**
     * @return string[] Valid Meta interest IDs
     */
    public function validateInterestIds(array $interestIds): array
    {
        $interestIds = array_values(array_unique(array_filter(array_map(
            fn ($id) => trim((string) $id),
            $interestIds
        ))));

        if ($interestIds === []) {
            return [];
        }

        try {
            $response = $this->client()->get("{$this->baseUrl}/search", [
                'type' => 'adinterestvalid',
                'interest_list' => json_encode($interestIds),
                'access_token' => $this->accessToken,
            ]);

            if ($response->failed()) {
                Log::warning('META_INTEREST_VALIDATION_FAILED', [
                    'interest_ids' => $interestIds,
                    'response' => $response->body(),
                ]);

                return $interestIds;
            }

            $result = $response->json();
            $valid = [];

            foreach ($result['data'] ?? [] as $item) {
                if (($item['valid'] ?? false) && ! empty($item['id'])) {
                    $valid[] = (string) $item['id'];
                }
            }

            return $valid;
        } catch (Throwable $e) {
            Log::warning('META_INTEREST_VALIDATION_ERROR', [
                'interest_ids' => $interestIds,
                'error' => $e->getMessage(),
            ]);

            return $interestIds;
        }
    }

    /**
     * Search cities, regions, or countries via Meta Targeting Search.
     */
    public function searchGeoLocations(
        string $query,
        string $locationType = 'city',
        ?string $countryCode = null
    ): array {
        $query = trim($query);

        if (strlen($query) < 2) {
            return [];
        }

        $allowedTypes = ['city', 'country', 'region', 'zip'];
        if (! in_array($locationType, $allowedTypes, true)) {
            $locationType = 'city';
        }

        $params = [
            'type' => 'adgeolocation',
            'location_types' => json_encode([$locationType]),
            'q' => $query,
            'limit' => 25,
            'access_token' => $this->accessToken,
        ];

        if ($countryCode) {
            $params['country_code'] = strtoupper(trim($countryCode));
        }

        $response = $this->client()->get("{$this->baseUrl}/search", $params);

        if ($response->failed()) {
            Log::warning('META_GEO_SEARCH_FAILED', [
                'query' => $query,
                'location_type' => $locationType,
                'country_code' => $countryCode,
                'response' => $response->body(),
            ]);

            return [];
        }

        $items = $response->json()['data'] ?? [];

        return collect($items)->map(function ($item) {
            return [
                'key' => (string) ($item['key'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'type' => (string) ($item['type'] ?? ''),
                'country_code' => strtoupper((string) ($item['country_code'] ?? $item['country'] ?? '')),
                'country_name' => (string) ($item['country_name'] ?? ''),
                'region' => (string) ($item['region'] ?? ''),
                'region_id' => isset($item['region_id']) ? (int) $item['region_id'] : null,
                'supports_city' => (bool) ($item['supports_city'] ?? true),
            ];
        })->filter(fn ($item) => $item['key'] !== '' && $item['name'] !== '')->values()->all();
    }

    protected function resolveDestinationType(string $optimizationGoal): ?string
    {
        return match (strtoupper($optimizationGoal)) {
            'LEAD_GENERATION', 'QUALITY_LEAD' => 'ON_AD',
            default => null,
        };
    }

    /**
     * Add required placement positions when publisher_platforms is present.
     */
    protected function enrichPlacementTargeting(array $targeting): array
    {
        $platforms = $targeting['publisher_platforms'];

        if (in_array('facebook', $platforms, true)) {
            $targeting['facebook_positions'] = $targeting['facebook_positions'] ?? [
                'feed',
                'story',
                'instream_video',
                'marketplace',
            ];
        }

        if (in_array('instagram', $platforms, true)) {
            $targeting['instagram_positions'] = $targeting['instagram_positions'] ?? [
                'stream',
                'story',
                'reels',
            ];
        }

        if (in_array('messenger', $platforms, true)) {
            $targeting['messenger_positions'] = $targeting['messenger_positions'] ?? [
                'messenger_home',
                'story',
            ];
        }

        if (in_array('audience_network', $platforms, true)) {
            $targeting['audience_network_positions'] = $targeting['audience_network_positions'] ?? [
                'classic',
                'instream_video',
            ];
        }

        if (empty($targeting['device_platforms'])) {
            $targeting['device_platforms'] = ['mobile', 'desktop'];
        }

        return $targeting;
    }

    /**
     * Apply placement position defaults (for local targeting JSON or API payloads).
     */
    public function enrichPlacementsForTargeting(array $targeting): array
    {
        if (empty($targeting['publisher_platforms'])) {
            return $targeting;
        }

        return $this->enrichPlacementTargeting($targeting);
    }

   /*
|--------------------------------------------------------------------------
| ADSET
|--------------------------------------------------------------------------
*/

public function createAdSet(string $accountId, array $data): array
{
    $accountId = $this->formatAccount($accountId);

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (empty($data['campaign_id'])) {
        throw new Exception('campaign_id required');
    }

    if (empty($data['name'])) {
        throw new Exception('AdSet name required');
    }

    if (empty($data['targeting'])) {
        throw new Exception('targeting required');
    }

    if (empty($data['daily_budget'])) {
        throw new Exception('daily_budget required');
    }

    /*
    |--------------------------------------------------------------------------
    | TARGETING SAFETY
    |--------------------------------------------------------------------------
    | Targeting may arrive as array OR JSON string
    */

    $targeting = $data['targeting'];

    if (is_string($targeting)) {

        $decoded = json_decode($targeting, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid targeting JSON');
        }

        $targeting = $decoded;
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD TARGETING
    |--------------------------------------------------------------------------
    */

    $targeting = $this->buildTargeting($targeting);

/*
|--------------------------------------------------------------------------
| Safety: ensure array
|--------------------------------------------------------------------------
*/

if (!is_array($targeting)) {
    throw new Exception('Invalid targeting structure');
}

    /*
    |--------------------------------------------------------------------------
    | PAYLOAD
    |--------------------------------------------------------------------------
    */

    $payload = [

        'name' => $data['name'],

        'campaign_id' => $data['campaign_id'],

        'daily_budget' => (int) $data['daily_budget'],

        'billing_event' => $data['billing_event'] ?? 'IMPRESSIONS',

        'optimization_goal' => $data['optimization_goal'] ?? 'LINK_CLICKS',

        'bid_strategy' => $data['bid_strategy'] ?? 'LOWEST_COST_WITHOUT_CAP',

        'status' => $data['status'] ?? 'PAUSED',

        'start_time' => $data['start_time'] ?? now()->addMinutes(5)->timestamp,

        'targeting' => json_encode($targeting)
    ];

    /*
    |--------------------------------------------------------------------------
    | PROMOTED OBJECT
    |--------------------------------------------------------------------------
    */

    if (!empty($data['promoted_object'])) {

        $payload['promoted_object'] = is_array($data['promoted_object'])
            ? json_encode($data['promoted_object'])
            : $data['promoted_object'];
    }

    $destinationType = $data['destination_type']
        ?? $this->resolveDestinationType((string) ($payload['optimization_goal'] ?? ''));

    if ($destinationType) {
        $payload['destination_type'] = $destinationType;
    }

    /*
    |--------------------------------------------------------------------------
    | DEBUG LOG
    |--------------------------------------------------------------------------
    */

    Log::info('META_ADSET_PAYLOAD', [
        'endpoint' => "{$accountId}/adsets",
        'payload' => $payload
    ]);

    /*
    |--------------------------------------------------------------------------
    | API REQUEST
    |--------------------------------------------------------------------------
    */

    $response = $this->post("{$accountId}/adsets", $payload);

    /*
    |--------------------------------------------------------------------------
    | RESPONSE LOG
    |--------------------------------------------------------------------------
    */

    Log::info('META_ADSET_CREATED', [
        'response' => $response
    ]);

    return $response;
}
    public function deleteAdSet(string $adsetId):array
    {
        return $this->delete($adsetId);
    }

    /*
    |--------------------------------------------------------------------------
    | IMAGE UPLOAD
    |--------------------------------------------------------------------------
    */

    public function uploadImage(string $accountId,string $filePath):array
    {
        $accountId = $this->formatAccount($accountId);

        Log::info('META_UPLOAD_IMAGE',[
            'account'=>$accountId,
            'file'=>$filePath
        ]);

        $timeout = (int) config('services.meta.http_timeout', 90);
        $connectTimeout = (int) config('services.meta.http_connect_timeout', 45);

        $response = Http::timeout(max(60, $timeout))
            ->connectTimeout($connectTimeout)
            ->attach(
                'filename',
                file_get_contents($filePath),
                basename($filePath)
            )
            ->post("{$this->baseUrl}/{$accountId}/adimages", [
                'access_token' => $this->accessToken,
            ]);

        if($response->failed()){
            $this->handleError($response,'uploadImage');
        }

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | CREATIVE
    |--------------------------------------------------------------------------
    */

    public function createCreative(string $accountId,array $data):array
    {
        $accountId = $this->formatAccount($accountId);

        $payload = [

            'name'=>$data['name'],
'object_story_spec' => json_encode(
    array_filter($data['object_story_spec'])
)
        ];

        Log::info('META_CREATIVE_PAYLOAD',$payload);

        return $this->post("{$accountId}/adcreatives",$payload);
    }

  

 /*
|--------------------------------------------------------------------------
| CREATE AD
|--------------------------------------------------------------------------
*/

public function createAd(string $accountId, array $data): array
{
    $accountId = $this->formatAccount($accountId);

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (empty($data['name'])) {
        throw new Exception('Ad name is required');
    }

    if (empty($data['adset_id'])) {
        throw new Exception('adset_id is required');
    }

    if (empty($data['creative']['id'])) {
        throw new Exception('creative id is required');
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD PAYLOAD
    |--------------------------------------------------------------------------
    | Meta requires the creative field to be JSON encoded
    | and it must contain creative_id
    */

    $payload = [

        'name' => $data['name'],

        'adset_id' => $data['adset_id'],

        'status' => $data['status'] ?? 'PAUSED',

        'creative' => json_encode([
            'creative_id' => $data['creative']['id']
        ])
    ];

    /*
    |--------------------------------------------------------------------------
    | DEBUG LOG
    |--------------------------------------------------------------------------
    */

    Log::info('META_AD_CREATE_PAYLOAD', [

        'endpoint' => "{$accountId}/ads",

        'payload' => $payload

    ]);

    /*
    |--------------------------------------------------------------------------
    | SEND REQUEST
    |--------------------------------------------------------------------------
    */

    $response = $this->post("{$accountId}/ads", $payload);

    /*
    |--------------------------------------------------------------------------
    | RESPONSE LOG
    |--------------------------------------------------------------------------
    */

    Log::info('META_AD_CREATE_RESPONSE', $response);

    return $response;
}

/*
|--------------------------------------------------------------------------
| UPDATE AD
|--------------------------------------------------------------------------
*/

public function updateAd(string $adId, array $data): array
{
    Log::info('META_AD_UPDATE_PAYLOAD', [
        'ad_id' => $adId,
        'payload' => $data
    ]);

    return $this->post($adId, $data);
}


/*
|--------------------------------------------------------------------------
| DELETE AD
|--------------------------------------------------------------------------
*/

public function deleteAd(string $adId): array
{
    Log::info('META_AD_DELETE', [
        'ad_id' => $adId
    ]);

    return $this->delete($adId);
}
public function getCampaigns(string $accountId): array
{
    $accountId = $this->formatAccount($accountId);

    return $this->get("{$accountId}/campaigns", [
        'fields' => 'id,name,status,objective'
    ]);
}


/*
|--------------------------------------------------------------------------
| GET ADSETS
|--------------------------------------------------------------------------
*/

public function getAdSets(string $accountId): array
{
    $accountId = $this->formatAccount($accountId);

    return $this->get("{$accountId}/adsets", [
        'fields' => 'id,name,campaign_id,status,daily_budget'
    ]);
}


/*
|--------------------------------------------------------------------------
| GET ADS
|--------------------------------------------------------------------------
*/
public function getAds(?string $accountId = null): array
{
    $accountId = $accountId
        ? $this->formatAccount($accountId)
        : $this->defaultAccount;

    return $this->get("{$accountId}/ads", [

        'fields' => implode(',', [

            'id',
            'name',
            'status',
            'effective_status',
            'adset_id',

            'creative{id,name}',

            'ad_review_feedback'

        ])

    ]);
}

/*
|--------------------------------------------------------------------------
| GET SINGLE AD
|--------------------------------------------------------------------------
*/

public function getAd(string $adId): array
{
    return $this->get($adId, [
        'fields' => 'id,name,status,effective_status,adset_id,campaign_id'
    ]);
}
/*
|--------------------------------------------------------------------------
| GET INSIGHTS
|--------------------------------------------------------------------------
*/
public function getInsights(string $objectId, string $preset = 'lifetime', array $extra = []): array
{
    /*
    |--------------------------------------------------------------------------
    | Default Fields For Monitoring Dashboard
    |--------------------------------------------------------------------------
    */

    $fields = implode(',', [

        'impressions',
        'clicks',
        'spend',
        'reach',

        'ctr',
        'cpm',
        'cpc',

        'frequency',
        'inline_link_clicks',

        'actions',
        'action_values',

        'video_p25_watched_actions',
        'video_p50_watched_actions',
        'video_p75_watched_actions',
        'video_p100_watched_actions',

        'date_start',
        'date_stop'
    ]);

    /*
    |--------------------------------------------------------------------------
    | Query Parameters
    |--------------------------------------------------------------------------
    */

    $params = array_merge([
        'fields' => $fields,
        'date_preset' => $preset,
        'limit' => 1
    ], $extra);

    Log::info('META_INSIGHTS_REQUEST', [
        'object_id' => $objectId,
        'preset' => $preset,
        'params' => $params
    ]);

    /*
    |--------------------------------------------------------------------------
    | Call Meta API
    |--------------------------------------------------------------------------
    */

    $response = $this->get("{$objectId}/insights", $params);

    /*
    |--------------------------------------------------------------------------
    | If breakdown requested → return raw rows (for audience/device tables)
    |--------------------------------------------------------------------------
    */

    if (isset($extra['breakdowns'])) {
        return $response['data'] ?? [];
    }

    /*
    |--------------------------------------------------------------------------
    | Normal Dashboard Metrics
    |--------------------------------------------------------------------------
    */

    $data = $response['data'][0] ?? [];

    return [

        'impressions' => (int)($data['impressions'] ?? 0),

        'clicks' => (int)($data['clicks'] ?? 0),

        'spend' => (float)($data['spend'] ?? 0),

        'reach' => (int)($data['reach'] ?? 0),

        'ctr' => (float)($data['ctr'] ?? 0),

        'cpm' => (float)($data['cpm'] ?? 0),

        'cpc' => (float)($data['cpc'] ?? 0),

        'frequency' => (float)($data['frequency'] ?? 0),

        'inline_link_clicks' => (int)($data['inline_link_clicks'] ?? 0),

        'actions' => $data['actions'] ?? [],

        'action_values' => $data['action_values'] ?? [],

        'video_25' => $data['video_p25_watched_actions'] ?? [],
        'video_50' => $data['video_p50_watched_actions'] ?? [],
        'video_75' => $data['video_p75_watched_actions'] ?? [],
        'video_100' => $data['video_p100_watched_actions'] ?? [],

        'date_start' => $data['date_start'] ?? null,
        'date_stop' => $data['date_stop'] ?? null,

        'raw' => $response
    ];
}
/*
|--------------------------------------------------------------------------
| GET CREATIVE
|--------------------------------------------------------------------------
| Fetch minimal Creative info from Meta
*/

public function getCreative(string $creativeId): array
{
    return $this->get($creativeId, [

        'fields' => implode(',', [

            'id',
            'name',
            'status'

        ])
    ]);
}
/*
|--------------------------------------------------------------------------
| GET SINGLE CAMPAIGN
|--------------------------------------------------------------------------
*/

public function getCampaign(string $campaignId): array
{
    return $this->get($campaignId, [
        'fields' => 'id,name,status,objective'
    ]);
}
/*
|--------------------------------------------------------------------------
| UPDATE ADSET
|--------------------------------------------------------------------------
*/

public function updateAdSet(string $adsetId, array $data): array
{
    Log::info('META_ADSET_UPDATE',[
        'adset_id'=>$adsetId,
        'payload'=>$data
    ]);

    return $this->post($adsetId,$data);
}
/*
|--------------------------------------------------------------------------
| ACCESS TOKEN
|--------------------------------------------------------------------------
*/

protected function getAccessToken(): string
{
    return $this->accessToken;
}
public function updateCreative(string $creativeId,array $data):array
{
    return $this->post($creativeId,$data);
}
public function getCreativeInsights(string $creativeId): array
{
    return $this->get("{$creativeId}/insights", [
        'fields' => 'impressions,clicks,spend,ctr',
        'date_preset' => 'maximum'
    ]);
}
public function getBillingInfo(string $accountId)
{
    $accountId = $this->formatAccount($accountId);

    $timeout = (int) config('services.meta.http_timeout', 90);
    $connectTimeout = (int) config('services.meta.http_connect_timeout', 45);

    $response = Http::timeout($timeout)
        ->connectTimeout($connectTimeout)
        ->get("{$this->baseUrl}/{$accountId}", [
            'fields' => implode(',', [
                'id',
                'name',
                'account_status',
                'currency',
                'timezone_name',
                'amount_spent',
                'spend_cap',
                'funding_source_details',
            ]),
            'access_token' => $this->accessToken,
        ]);

    if(!$response->successful()){
        $this->handleError($response,'billing_info');
    }

    return $response->json();
}
/*
|--------------------------------------------------------------------------
| 🔥 GET INSIGHTS BATCH (CRITICAL FOR SYNC)
|--------------------------------------------------------------------------
| Fetch all ads insights in ONE request (avoids rate limit)
*/

public function getInsightsBatch(string $accountId): array
{
    $accountId = $this->formatAccount($accountId);

    return $this->get("{$accountId}/insights", [

        'level' => 'ad',

        'fields' => implode(',', [
            'ad_id',
            'impressions',
            'clicks',
            'spend'
        ]),

        'date_preset' => 'today',

        'limit' => 500
    ]);
}
public function getAccountStatus($accountId)
{
    $accountId = str_starts_with($accountId, 'act_')
        ? $accountId
        : "act_{$accountId}";

    $timeout = (int) config('services.meta.http_timeout', 90);
    $connectTimeout = (int) config('services.meta.http_connect_timeout', 45);

    $response = Http::timeout($timeout)
        ->connectTimeout($connectTimeout)
        ->get("{$this->baseUrl}/{$accountId}", [
            'fields' => 'account_status',
            'access_token' => $this->accessToken,
        ]);

    if (!$response->successful()) {
        throw new \Exception($response->body());
    }

    return $response->json();
}
}