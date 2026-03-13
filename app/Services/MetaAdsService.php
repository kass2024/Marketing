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
        return Http::timeout(30)
            ->retry(3,500)
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

    public function getPages():array
    {
        $res = $this->get('me/accounts');
        return $res['data'] ?? [];
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
    if(
        isset($targeting['geo_locations']['countries']) &&
        count($targeting['geo_locations']['countries']) === 1
    ){
        unset($targeting['locales']);
    }

    if(isset($targeting['flexible_spec'])){
        $targeting['targeting_automation'] = [
            'advantage_audience' => 0
        ];
    }

    Log::info('META_TARGETING_FINAL',$targeting);

    return $targeting;
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

        $response = Http::timeout(60)
            ->attach(
                'filename',
                file_get_contents($filePath),
                basename($filePath)
            )
            ->post("{$this->baseUrl}/{$accountId}/adimages",[
                'access_token'=>$this->accessToken
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
    | ADS
    |--------------------------------------------------------------------------
    */

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

    if (empty($data['creative']['creative_id'])) {
        throw new Exception('creative_id is required');
    }

    /*
    |--------------------------------------------------------------------------
    | BUILD PAYLOAD
    |--------------------------------------------------------------------------
    */

    $payload = [

        'name' => $data['name'],

        'adset_id' => $data['adset_id'],

        'status' => $data['status'] ?? 'PAUSED',

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT: Meta requires creative as JSON string
        |--------------------------------------------------------------------------
        */

        'creative' => json_encode([
            'creative_id' => $data['creative']['creative_id']
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

public function getAds(string $accountId): array
{
    $accountId = $this->formatAccount($accountId);

    return $this->get("{$accountId}/ads", [
        'fields' => 'id,name,adset_id,status'
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
public function getInsights(string $objectId): array
{
    return $this->get("{$objectId}/insights", [
        'fields' => 'impressions,clicks,spend,reach,ctr,cpm',
        'date_preset' => 'maximum'
    ]);
}
/*
|--------------------------------------------------------------------------
| PULISH CREATIVE
|--------------------------------------------------------------------------
*/
public function getCreative(string $creativeId): array
{
    return $this->get($creativeId, [
        'fields' => 'id,name,status,configured_status,effective_status,review_feedback'
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
        'date_preset' => 'lifetime'
    ]);
}
}