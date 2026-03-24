<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Creative;
use App\Services\MetaAdsService;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Throwable;
use Exception;

class AdController extends Controller
{
    protected MetaAdsService $meta;

    public function __construct(MetaAdsService $meta)
    {
        $this->meta = $meta;
    }

    /*
    |--------------------------------------------------------------------------
    | LIST ADS
    |--------------------------------------------------------------------------
    */
public function index(): View
{
    /*
    |--------------------------------------------------------------------------
    | Load Ads With Required Relations
    |--------------------------------------------------------------------------
    */

    $ads = Ad::query()
        ->with([
            'creative:id,name,image_url',
            'adSet:id,name,campaign_id',
            'adSet.campaign:id,name,ad_account_id',
            'adSet.campaign.adAccount:id,name,meta_id'
        ])
    ->select([
'id',
'name',
'adset_id',
'creative_id',
'meta_ad_id',
'status',
'impressions',
'clicks',
'ctr',
'spend',

'daily_budget',
'daily_spend',
'pause_reason',
'spend_date',

'created_at'
])
        ->latest()
        ->paginate(20);


    /*
    |--------------------------------------------------------------------------
    | Dashboard Metrics
    |--------------------------------------------------------------------------
    */

    $collection = $ads->getCollection();

    $metrics = [

        'total_ads' => $ads->total(),

        'active_ads' => $collection->where('status','ACTIVE')->count(),

        'total_spend' => $collection->sum('spend'),

        'total_clicks' => $collection->sum('clicks'),

        'total_impressions' => $collection->sum('impressions'),

        'avg_ctr' => $collection->avg('ctr')

    ];


    /*
    |--------------------------------------------------------------------------
    | Return View
    |--------------------------------------------------------------------------
    */

    return view('admin.ads.index', [

        'ads' => $ads,

        'metrics' => $metrics

    ]);
}

    /*
    |--------------------------------------------------------------------------
    | CREATE FORM
    |--------------------------------------------------------------------------
    */

    public function create(): View
    {
        $adsets = AdSet::with('campaign.adAccount')
            ->latest()
            ->get();

        $creatives = Creative::latest()->get();

        return view('admin.ads.create', compact('adsets','creatives'));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE AD
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): RedirectResponse
    {
    $data = $request->validate([
'name' => 'required|string|max:255',
'adset_id' => 'required|exists:ad_sets,id',
'creative_id' => 'required|exists:creatives,id',
'daily_budget' => 'required|numeric|min:0.10',
'status' => 'required|in:ACTIVE,PAUSED'
]);

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | LOAD MODELS
            |--------------------------------------------------------------------------
            */

            $adset = AdSet::with('campaign.adAccount')
                ->findOrFail($data['adset_id']);

           #$creative = Creative::where('meta_id',$data['creative_id'])->firstOrFail();
           $creative = Creative::findOrFail($data['creative_id']);

            $campaign = $adset->campaign;

            $adAccount = $campaign->adAccount ?? null;


            /*
            |--------------------------------------------------------------------------
            | VALIDATE META SYNC
            |--------------------------------------------------------------------------
            */

            if (!$adset->meta_id) {
                throw new Exception('AdSet not synced with Meta.');
            }

            if (!$creative->meta_id) {
                throw new Exception('Creative not synced with Meta.');
            }

            if (!$adAccount || !$adAccount->meta_id) {
                throw new Exception('Meta Ad Account not connected.');
            }


            /*
            |--------------------------------------------------------------------------
            | PREVENT DUPLICATE ADS
            |--------------------------------------------------------------------------
            */

            $exists = Ad::where('adset_id',$adset->id)
                ->where('creative_id',$creative->id)
                ->first();

            if ($exists) {
                throw new Exception('Ad already exists for this AdSet + Creative.');
            }


            /*
            |--------------------------------------------------------------------------
            | FORMAT ACCOUNT ID
            |--------------------------------------------------------------------------
            */

            $accountId = $adAccount->meta_id;

            if (!str_starts_with($accountId,'act_')) {
                $accountId = 'act_'.$accountId;
            }


          /*
/*
|--------------------------------------------------------------------------
| META PAYLOAD
|--------------------------------------------------------------------------
| Prepare the payload to create the Ad in Meta.
| The creative meta_id is passed as "id" and converted by MetaAdsService
| to the required format: creative={"creative_id":"..."}
*/

$payload = [

    // Ad name in Meta
    'name' => $data['name'],

    // Meta AdSet ID (not local id)
    'adset_id' => $adset->meta_id,

    // Attach existing Meta creative
    'creative' => [
        'id' => $creative->meta_id
    ],

    // Delivery status (default paused for safety)
    'status' => $data['status'] ?? 'PAUSED'

];

/*
|--------------------------------------------------------------------------
| LOG META REQUEST
|--------------------------------------------------------------------------
|
| Useful for debugging API calls and verifying payload correctness.
|
*/

Log::info('META_AD_CREATE_REQUEST', [

    'account_id' => $accountId,

    'adset_meta_id' => $adset->meta_id,

    'creative_meta_id' => $creative->meta_id,

    'payload' => $payload

]);
            /*
            |--------------------------------------------------------------------------
            | CREATE AD ON META
            |--------------------------------------------------------------------------
            */

            $response = $this->meta->createAd(
                $accountId,
                $payload
            );


            Log::info('META_AD_CREATE_RESPONSE', $response);


            if (!isset($response['id'])) {

                $error = $response['error']['message']
                    ?? 'Meta API failed creating ad';

                throw new Exception($error);
            }


            /*
            |--------------------------------------------------------------------------
            | SAVE LOCAL AD
            |--------------------------------------------------------------------------
            */
$ad = Ad::create([
'adset_id' => $adset->id,
'creative_id' => $creative->id,
'meta_ad_id' => $response['id'],

'name' => $data['name'],
'status' => $data['status'],

'daily_budget' => $request->input('daily_budget', 2),
'daily_spend' => 0
]);

            DB::commit();


            Log::info('META_AD_CREATED', [

                'local_ad_id' => $ad->id,

                'meta_ad_id' => $response['id']

            ]);


            return redirect()
                ->route('admin.ads.index')
                ->with('success','Ad created and synced to Meta.');

        }

        catch (Throwable $e) {

            DB::rollBack();

            Log::error('AD_CREATION_FAILED', [

                'error' => $e->getMessage()

            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'meta' => 'Ad creation failed: '.$e->getMessage()
                ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | ADS BY ADSET (AJAX)
    |--------------------------------------------------------------------------
    */

    public function byAdset(int $adsetId): JsonResponse
    {
        $ads = Ad::where('adset_id',$adsetId)
            ->latest()
            ->get([
                'id',
                'name',
                'status',
                'impressions',
                'clicks',
                'spend'
            ]);

        return response()->json($ads);
    }


    /*
    |--------------------------------------------------------------------------
    | PREVIEW CREATIVE
    |--------------------------------------------------------------------------
    */
public function preview(Ad $ad): View
{
    $ad->load([
        'creative',
        'adSet',
        'adSet.campaign'
    ]);

    $audience = [
        'countries' => [],
        'age' => [],
        'gender' => []
    ];

    $devices = [];
    $placements = [];

    try {

        if (!$ad->meta_ad_id) {
            throw new Exception('Ad not synced with Meta.');
        }

        /*
        |--------------------------------------------------------------------------
        | BASIC INSIGHTS
        |--------------------------------------------------------------------------
        */

        $base = $this->meta->getInsights($ad->meta_ad_id,'maximum');

        if (!empty($base)) {

            $impressions = (int) ($base['impressions'] ?? 0);
            $clicks      = (int) ($base['clicks'] ?? 0);
            $spend       = (float) ($base['spend'] ?? 0);

            $ctr = $impressions > 0
                ? round(($clicks / $impressions) * 100, 2)
                : 0;

            $ad->update([
                'impressions' => $impressions,
                'clicks'      => $clicks,
                'spend'       => $spend,
                'ctr'         => $ctr
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | COUNTRY BREAKDOWN
        |--------------------------------------------------------------------------
        */

        $countries = $this->meta->getInsights(
            $ad->meta_ad_id,
            'maximum',
            ['breakdowns' => 'country']
        );

        foreach ($countries as $row) {

            $country = $row['country'] ?? 'Unknown';

            $audience['countries'][$country] =
                ($audience['countries'][$country] ?? 0)
                + (int)($row['impressions'] ?? 0);
        }


        /*
        |--------------------------------------------------------------------------
        | AGE + GENDER BREAKDOWN
        |--------------------------------------------------------------------------
        */

        $ages = $this->meta->getInsights(
            $ad->meta_ad_id,
            'maximum',
            ['breakdowns' => 'age,gender']
        );

        foreach ($ages as $row) {

            if (!empty($row['age'])) {

                $age = $row['age'];

                $audience['age'][$age] =
                    ($audience['age'][$age] ?? 0)
                    + (int)($row['impressions'] ?? 0);
            }

            if (!empty($row['gender'])) {

                $gender = $row['gender'];

                $audience['gender'][$gender] =
                    ($audience['gender'][$gender] ?? 0)
                    + (int)($row['impressions'] ?? 0);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | DEVICE BREAKDOWN
        |--------------------------------------------------------------------------
        */

        $deviceRows = $this->meta->getInsights(
            $ad->meta_ad_id,
            'maximum',
            ['breakdowns' => 'device_platform']
        );

        foreach ($deviceRows as $row) {

            $device = $row['device_platform'] ?? 'Unknown';

            $devices[$device] = [

                'device' => $device,

                'impressions' =>
                    ($devices[$device]['impressions'] ?? 0)
                    + (int)($row['impressions'] ?? 0),

                'clicks' =>
                    ($devices[$device]['clicks'] ?? 0)
                    + (int)($row['clicks'] ?? 0)
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | PLACEMENT BREAKDOWN
        |--------------------------------------------------------------------------
        */

        $placementRows = $this->meta->getInsights(
            $ad->meta_ad_id,
            'maximum',
            ['breakdowns' => 'publisher_platform']
        );

        foreach ($placementRows as $row) {

            $placement = $row['publisher_platform'] ?? 'Unknown';

            $placements[$placement] = [

                'placement' => $placement,

                'impressions' =>
                    ($placements[$placement]['impressions'] ?? 0)
                    + (int)($row['impressions'] ?? 0),

                'clicks' =>
                    ($placements[$placement]['clicks'] ?? 0)
                    + (int)($row['clicks'] ?? 0)
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | SORT DATA FOR DASHBOARD
        |--------------------------------------------------------------------------
        */

        arsort($audience['countries']);
        arsort($audience['age']);
        arsort($audience['gender']);

    }

    catch (Throwable $e) {

        Log::error('AD_INSIGHTS_PREVIEW_FAILED', [

            'ad_id' => $ad->id,
            'meta_ad_id' => $ad->meta_ad_id,
            'error' => $e->getMessage()

        ]);
    }

    return view('admin.ads.preview', [

        'ad' => $ad,

        'audience' => $audience,

        'devices' => array_values($devices),

        'placements' => array_values($placements)

    ]);
}
/*
|--------------------------------------------------------------------------
| UPDATE STATUS
|--------------------------------------------------------------------------
*/
public function updateStatus(Request $request, Ad $ad): RedirectResponse
{
    $data = $request->validate([
        'status' => 'required|in:ACTIVE,PAUSED,ARCHIVED'
    ]);

    try {

        /*
        |------------------------------------------------------------------
        | Update on Meta (if synced)
        |------------------------------------------------------------------
        */
        if ($ad->meta_ad_id) {

            $this->meta->updateAd(
                $ad->meta_ad_id,
                [
                    'status' => $data['status']
                ]
            );
        }

        /*
        |------------------------------------------------------------------
        | Determine pause reason
        |------------------------------------------------------------------
        */
        $pauseReason = $ad->pause_reason; // keep existing by default

        if ($data['status'] === 'PAUSED') {

            // Manual pause
            $pauseReason = 'manual';

        } elseif ($data['status'] === 'ACTIVE') {

            // Reset pause reason when activating
            $pauseReason = null;

        } elseif ($data['status'] === 'ARCHIVED') {

            // Archived = no pause logic needed
            $pauseReason = null;
        }

        /*
        |------------------------------------------------------------------
        | Update local DB
        |------------------------------------------------------------------
        */
        $ad->update([
            'status' => $data['status'],
            'pause_reason' => $pauseReason
        ]);

        return back()->with('success', 'Ad status updated.');

    } catch (\Throwable $e) {

        Log::error('AD_STATUS_UPDATE_FAILED', [
            'ad_id' => $ad->id,
            'status' => $data['status'] ?? null,
            'error' => $e->getMessage()
        ]);

        return back()->withErrors([
            'meta' => 'Unable to update ad status.'
        ]);
    }
}
    /*
    |--------------------------------------------------------------------------
    | DELETE AD
    |--------------------------------------------------------------------------
    */

    public function destroy(Ad $ad): RedirectResponse
    {
        try {

            if ($ad->meta_ad_id) {

                $this->meta->deleteAd($ad->meta_ad_id);

            }

            $ad->delete();

            return back()->with('success','Ad deleted.');

        }

        catch (Throwable $e) {

            Log::error('AD_DELETE_FAILED',[
                'error'=>$e->getMessage()
            ]);

            return back()->withErrors([
                'meta'=>'Unable to delete ad'
            ]);
        }
    }
    public function edit(Ad $ad): View
{
    $adsets = AdSet::with('campaign')->latest()->get();
    $creatives = Creative::latest()->get();

    return view('admin.ads.edit', [
        'ad' => $ad,
        'adsets' => $adsets,
        'creatives' => $creatives
    ]);
}
public function update(Request $request, Ad $ad): RedirectResponse
{
    $data = $request->validate([
        'name' => 'required|string|max:255',
        'adset_id' => 'required|exists:ad_sets,id',
        'creative_id' => 'required|exists:creatives,id',
        'daily_budget' => 'required|numeric|min:1',
        'status' => 'required|in:ACTIVE,PAUSED,ARCHIVED'
    ]);

    try {

        /*
        |--------------------------------------------------------------------------
        | UPDATE META AD (only name/status allowed)
        |--------------------------------------------------------------------------
        */

        if ($ad->meta_ad_id) {

            $this->meta->updateAd(
                $ad->meta_ad_id,
                [
                    'name' => $data['name'],
                    'status' => $data['status']
                ]
            );

        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE LOCAL DB
        |--------------------------------------------------------------------------
        */

        $ad->update([
            'name' => $data['name'],
            'adset_id' => $data['adset_id'],
            'creative_id' => $data['creative_id'],
            'daily_budget' => $data['daily_budget'],
            'status' => $data['status']
        ]);

        return redirect()
            ->route('admin.ads.index')
            ->with('success','Ad updated successfully.');

    }

    catch(Throwable $e){

        Log::error('AD_UPDATE_FAILED',[
            'error'=>$e->getMessage()
        ]);

        return back()->withErrors([
            'update'=>'Failed to update Ad'
        ]);
    }
}
public function activate(Ad $ad): RedirectResponse
{
    try {

        if ($ad->meta_ad_id) {

            $this->meta->updateAd(
                $ad->meta_ad_id,
                ['status'=>'ACTIVE']
            );

        }

        $ad->update([
            'status'=>'ACTIVE',
            'pause_reason'=>null
        ]);

        return back()->with('success','Ad activated.');

    } catch(Throwable $e){

        Log::error('AD_ACTIVATE_FAILED',[
            'error'=>$e->getMessage()
        ]);

        return back()->withErrors([
            'activate'=>'Failed to activate ad'
        ]);
    }
}
public function pause(Ad $ad): RedirectResponse
{
    try {

        if ($ad->meta_ad_id) {

            $this->meta->updateAd(
                $ad->meta_ad_id,
                ['status' => 'PAUSED']
            );

        }

        $ad->update([
            'status' => 'PAUSED',
            'pause_reason' => 'manual'
        ]);

        return back()->with('success','Ad paused manually.');

    } catch(Throwable $e){

        Log::error('AD_MANUAL_PAUSE_FAILED',[
            'error'=>$e->getMessage()
        ]);

        return back()->withErrors([
            'pause'=>'Failed to pause ad'
        ]);
    }
}
public function duplicate(Ad $ad): RedirectResponse
{
    $copy = $ad->replicate();

    $copy->name = $ad->name.' Copy';

    $copy->meta_ad_id = null;

    $copy->impressions = 0;
    $copy->clicks = 0;
    $copy->spend = 0;
    $copy->ctr = 0;

    $copy->status = 'PAUSED';

    $copy->save();

    return back()->with('success','Ad duplicated.');
}
public function sync(Ad $ad): RedirectResponse
{
    if (!$ad->meta_ad_id) {
        return back()->withErrors([
            'sync' => 'Ad not synced with Meta'
        ]);
    }

    try {

        $insights = $this->meta->getInsights($ad->meta_ad_id);

        $impressions = 0;
        $clicks = 0;
        $spend = 0;

        if (!empty($insights['data'][0])) {

            $row = $insights['data'][0];

            $impressions = (int) ($row['impressions'] ?? 0);
            $clicks = (int) ($row['clicks'] ?? 0);
            $spend = (float) ($row['spend'] ?? 0);
        }

        $ctr = $impressions > 0
            ? round(($clicks / $impressions) * 100, 2)
            : 0;

        $ad->update([

            'impressions' => $impressions,
            'clicks' => $clicks,
            'spend' => max($ad->spend, $spend),
            'ctr' => $ctr

        ]);

        return back()->with('success','Ad metrics refreshed.');

    }

    catch(Throwable $e){

        Log::error('AD_SYNC_FAILED',[
            'error'=>$e->getMessage()
        ]);

        return back()->withErrors([
            'sync'=>$e->getMessage()
        ]);
    }
}
public function createFromAdSet(AdSet $adset): View
{
    $creatives = Creative::latest()->get();

    return view('admin.ads.create', [
        'adsets' => collect([$adset]),
        'creatives' => $creatives,
        'selectedAdSet' => $adset->id
    ]);
}
public function bulkStatusUpdate(Request $request): RedirectResponse
{
    $data = $request->validate([
        'ids' => 'required|array',
        'status' => 'required|in:ACTIVE,PAUSED'
    ]);

    Ad::whereIn('id',$data['ids'])
        ->update(['status'=>$data['status']]);

    return back()->with('success','Ads updated.');
}
/*
|--------------------------------------------------------------------------
| PUBLISH AD
|--------------------------------------------------------------------------
*/
public function publish(Ad $ad): RedirectResponse
{
    try {

        /*
        |------------------------------------------------------------------
        | Load Required Relations
        |------------------------------------------------------------------
        */
        $ad->load([
            'creative',
            'adSet.campaign.adAccount'
        ]);

        /*
        |------------------------------------------------------------------
        | Validate Required Data
        |------------------------------------------------------------------
        */
        if (!$ad->meta_ad_id) {
            throw new Exception('Ad is not synced with Meta.');
        }

        if (!$ad->adSet || !$ad->adSet->meta_id) {
            throw new Exception('AdSet not synced with Meta.');
        }

        if (!$ad->creative || !$ad->creative->meta_id) {
            throw new Exception('Creative not synced with Meta.');
        }

        /*
        |------------------------------------------------------------------
        | Prepare Payload
        |------------------------------------------------------------------
        */
        $payload = [
            'status' => 'ACTIVE'
        ];

        /*
        |------------------------------------------------------------------
        | Send Request To Meta
        |------------------------------------------------------------------
        */
        Log::info('META_AD_PUBLISH_REQUEST', [
            'ad_id' => $ad->id,
            'meta_ad_id' => $ad->meta_ad_id,
            'payload' => $payload
        ]);

        $response = $this->meta->updateAd(
            $ad->meta_ad_id,
            $payload
        );

        Log::info('META_AD_PUBLISH_RESPONSE', [
            'ad_id' => $ad->id,
            'response' => $response
        ]);

        /*
        |------------------------------------------------------------------
        | Handle Meta Errors
        |------------------------------------------------------------------
        */
        if (isset($response['error'])) {
            throw new Exception(
                $response['error']['message'] ?? 'Meta API error'
            );
        }

        /*
        |------------------------------------------------------------------
        | Update Local Ad (IMPORTANT FIX)
        |------------------------------------------------------------------
        */
        $ad->update([
            'status' => 'ACTIVE',
            'pause_reason' => null // ✅ CRITICAL FIX
        ]);

        Log::info('AD_PUBLISHED_SUCCESS', [
            'ad_id' => $ad->id,
            'meta_ad_id' => $ad->meta_ad_id
        ]);

        return back()->with('success', 'Ad successfully published.');

    } catch (Throwable $e) {

        Log::error('AD_PUBLISH_FAILED', [
            'ad_id' => $ad->id ?? null,
            'meta_ad_id' => $ad->meta_ad_id ?? null,
            'error' => $e->getMessage()
        ]);

        return back()->withErrors([
            'publish' => 'Publish failed: ' . $e->getMessage()
        ]);
    }
}
public function live(): JsonResponse
{
    $ads = Ad::query()
        ->with([
            'creative:id,name,image_url',
            'adSet:id,name'
        ])
        ->select([
            'id',
            'name',
            'adset_id',
            'creative_id',
            'meta_ad_id',
            'status',
            'impressions',
            'clicks',
            'ctr',
            'spend',
            'daily_spend',
            'daily_budget',
            'pause_reason'
        ])
        ->latest()
        ->get();

    $metrics = [

        'total_ads' => $ads->count(),

        'active_ads' => $ads->where('status','ACTIVE')->count(),

        'total_spend' => $ads->sum('spend'),

        'total_clicks' => $ads->sum('clicks')

    ];

    return response()->json([
        'metrics' => $metrics,
        'ads' => $ads
    ]);
}
}