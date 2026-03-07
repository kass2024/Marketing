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

    protected function handleError($response,$endpoint,$payload=[])
    {
        $body = $response->json();

        Log::error('META_API_ERROR',[
            'endpoint'=>$endpoint,
            'payload'=>$payload,
            'response'=>$body
        ]);

        $message = $body['error']['message'] ?? 'Meta API Error';

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

    public function createAdSet(string $accountId,array $data):array
    {
        $accountId = $this->formatAccount($accountId);

        if(!isset($data['campaign_id'])){
            throw new Exception('campaign_id required');
        }

        if(!isset($data['targeting'])){
            throw new Exception('targeting required');
        }

        $payload = [

            'name'=>$data['name'],

            'campaign_id'=>$data['campaign_id'],

            'daily_budget'=>$data['daily_budget'],

            'billing_event'=>$data['billing_event'] ?? 'IMPRESSIONS',

            'optimization_goal'=>$data['optimization_goal'] ?? 'LINK_CLICKS',

            'bid_strategy'=>$data['bid_strategy'] ?? 'LOWEST_COST_WITHOUT_CAP',

            'status'=>$data['status'] ?? 'PAUSED',

            'start_time'=>$data['start_time']
                ?? now()->addMinutes(5)->toIso8601String(),

            'targeting'=>$this->buildTargeting($data['targeting'])
        ];

      // Add promoted object if provided
if (isset($data['promoted_object']) && is_array($data['promoted_object'])) {
    $payload['promoted_object'] = $data['promoted_object'];
}

// Log final payload before sending
Log::info('META_ADSET_PAYSET_PAYLOAD', [
    'endpoint' => "{$accountId}/adsets",
    'payload' => $payload
]);

// Send request to Meta Graph API
$response = $this->post("{$accountId}/adsets", $payload);

// Log Meta response
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

            'object_story_spec'=>json_encode(
                $data['object_story_spec']
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

    public function createAd(string $accountId,array $data):array
    {
        $accountId = $this->formatAccount($accountId);

        $payload = [

            'name'=>$data['name'],

            'adset_id'=>$data['adset_id'],

            'status'=>$data['status'] ?? 'PAUSED'
        ];

        if(isset($data['creative'])){
            $payload['creative'] = json_encode($data['creative']);
        }

        Log::info('META_AD_CREATE_PAYLOAD',$payload);

        return $this->post("{$accountId}/ads",$payload);
    }

    public function updateAd(string $adId,array $data):array
    {
        return $this->post($adId,$data);
    }

    public function deleteAd(string $adId):array
    {
        return $this->delete($adId);
    }
    /*
|--------------------------------------------------------------------------
| GET CAMPAIGNS
|--------------------------------------------------------------------------
*/

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
| GET INSIGHTS
|--------------------------------------------------------------------------
*/

public function getInsights(string $objectId): array
{
    return $this->get("{$objectId}/insights", [
        'fields' => 'impressions,clicks,spend'
    ]);
}
}