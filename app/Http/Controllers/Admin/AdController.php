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
        $ads = Ad::with([
            'adSet.campaign.adAccount',
            'creative'
        ])
        ->latest()
        ->paginate(20);

        return view('admin.ads.index', compact('ads'));
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
            |--------------------------------------------------------------------------
            | META PAYLOAD
            |--------------------------------------------------------------------------
            */

            $payload = [

                'name' => $data['name'],

                'adset_id' => $adset->meta_id,

                'creative' => [
                    'creative_id' => $creative->meta_id
                ],

                'status' => $data['status']

            ];


            Log::info('META_AD_CREATE_REQUEST', [

                'account' => $accountId,

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

                'status' => $data['status']

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

    public function preview(Ad $ad): JsonResponse
    {
        $creative = $ad->creative;

        return response()->json([

            'image_url' => $creative->image_url ?? null,

            'video_url' => $creative->video_url ?? null,

            'headline' => $creative->headline ?? '',

            'body' => $creative->body ?? '',

            'call_to_action' => $creative->call_to_action ?? ''

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

            if ($ad->meta_ad_id) {

                $this->meta->updateAd(
                    $ad->meta_ad_id,
                    ['status'=>$data['status']]
                );
            }

            $ad->update([
                'status'=>$data['status']
            ]);

            return back()->with('success','Ad status updated.');

        }

        catch (Throwable $e) {

            Log::error('AD_STATUS_UPDATE_FAILED',[
                'error'=>$e->getMessage()
            ]);

            return back()->withErrors([
                'meta'=>'Unable to update ad status'
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
        'status' => 'required|in:ACTIVE,PAUSED'
    ]);

    try {

        if ($ad->meta_ad_id) {

            $this->meta->updateAd(
                $ad->meta_ad_id,
                ['name' => $data['name']]
            );

        }

        $ad->update($data);

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
    return $this->updateStatus(
        new Request(['status'=>'ACTIVE']),
        $ad
    );
}
public function pause(Ad $ad): RedirectResponse
{
    return $this->updateStatus(
        new Request(['status'=>'PAUSED']),
        $ad
    );
}
public function duplicate(Ad $ad): RedirectResponse
{
    $copy = $ad->replicate();

    $copy->name = $ad->name.' Copy';

    $copy->meta_ad_id = null;

    $copy->status = 'PAUSED';

    $copy->save();

    return back()->with('success','Ad duplicated.');
}
public function sync(Ad $ad): RedirectResponse
{
    if (!$ad->meta_ad_id) {
        return back()->withErrors([
            'sync'=>'Ad not synced with Meta'
        ]);
    }

    try {

        $meta = $this->meta->getAd($ad->meta_ad_id);

        $ad->update([
            'status' => $meta['status'] ?? $ad->status
        ]);

        return back()->with('success','Ad synced with Meta.');

    }

    catch(Throwable $e){

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
}