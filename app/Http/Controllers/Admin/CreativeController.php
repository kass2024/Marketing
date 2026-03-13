<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Creative;
use App\Models\AdSet;
use App\Models\Campaign;
use App\Models\AdAccount;
use App\Services\MetaAdsService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use Throwable;
use Exception;

class CreativeController extends Controller
{
    protected MetaAdsService $meta;

    public function __construct(MetaAdsService $meta)
    {
        $this->meta = $meta;
    }

    /*
    |--------------------------------------------------------------------------
    | LIST
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $creatives = Creative::with(['campaign','adset'])
            ->latest()
            ->paginate(20);

        return view('admin.creatives.index', compact('creatives'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $campaigns = Campaign::latest()->get();

        $adsets = AdSet::latest()->get();

        $pages = $this->meta->getPages();

        return view('admin.creatives.create', compact(
            'campaigns',
            'adsets',
            'pages'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        Log::info('CREATIVE_STORE_REQUEST', $request->all());

        $data = $request->validate([

            'campaign_id' => 'required|exists:campaigns,id',

            'adset_id' => 'required|exists:ad_sets,id',

            'page_id' => 'required|string',

            'name' => 'required|string|max:255',

            'headline' => 'nullable|string|max:255',

            'body' => 'nullable|string',

            'destination_url' => 'nullable|url',

            'call_to_action' => 'nullable|string|max:50',

            'image' => 'required|image|max:4096',

            'sync_meta' => 'nullable|boolean',

            'status' => 'nullable|string'

        ]);


        DB::beginTransaction();

        try {

            $campaign = Campaign::findOrFail($data['campaign_id']);

            $adset = AdSet::findOrFail($data['adset_id']);

            $account = AdAccount::whereNotNull('meta_id')->first();

            if (!$account) {
                throw new Exception('Meta Ad Account not connected.');
            }

            $accountId = $account->meta_id;

            if (!str_starts_with($accountId, 'act_')) {
                $accountId = 'act_' . $accountId;
            }


            /*
            |--------------------------------------------------------------------------
            | STORE IMAGE LOCALLY
            |--------------------------------------------------------------------------
            */

            $imagePath = $request->file('image')->store(
                'creatives',
                'public'
            );

            $imageFullPath = storage_path('app/public/'.$imagePath);


            /*
            |--------------------------------------------------------------------------
            | META IMAGE UPLOAD
            |--------------------------------------------------------------------------
            */

            $imageHash = null;

            if ($request->boolean('sync_meta')) {

                $imageResponse = $this->meta->uploadImage(
                    $accountId,
                    $imageFullPath
                );

                Log::info('META_IMAGE_UPLOAD', $imageResponse);

                if (!isset($imageResponse['images'])) {
                    throw new Exception('Meta image upload failed.');
                }

                $image = current($imageResponse['images']);

                $imageHash = $image['hash'] ?? null;

                if (!$imageHash) {
                    throw new Exception('Meta image hash missing.');
                }
            }


            /*
            |--------------------------------------------------------------------------
            | BUILD LINK DATA
            |--------------------------------------------------------------------------
            */

            $linkData = [

                'link' => $data['destination_url'] ?? config('app.url'),

                'message' => $data['body'] ?? '',

                'name' => $data['headline'] ?? $data['name'],

                'image_hash' => $imageHash

            ];


            if (!empty($data['call_to_action'])) {

                $linkData['call_to_action'] = [

                    'type' => $data['call_to_action'],

                    'value' => [
                        'link' => $data['destination_url']
                    ]

                ];
            }


            /*
            |--------------------------------------------------------------------------
            | CREATIVE PAYLOAD
            |--------------------------------------------------------------------------
            */

            $payload = [

                'name' => $data['name'],

                'object_story_spec' => [

                    'page_id' => $data['page_id'],

                    'link_data' => $linkData

                ]

            ];

            Log::info('META_CREATIVE_PAYLOAD', $payload);


            /*
            |--------------------------------------------------------------------------
            | CREATE META CREATIVE
            |--------------------------------------------------------------------------
            */

            $metaCreativeId = null;

            if ($request->boolean('sync_meta')) {

                $response = $this->meta->createCreative(
                    $accountId,
                    $payload
                );

                Log::info('META_CREATIVE_RESPONSE', $response);

                if (!isset($response['id'])) {

                    throw new Exception(
                        $response['error']['message']
                        ?? 'Meta creative creation failed.'
                    );
                }

                $metaCreativeId = $response['id'];
            }


            /*
            |--------------------------------------------------------------------------
            | SAVE LOCAL CREATIVE
            |--------------------------------------------------------------------------
            */

            $creative = Creative::create([

                'campaign_id' => $campaign->id,

                'adset_id' => $adset->id,

                'name' => $data['name'],

                'headline' => $data['headline'] ?? null,

                'body' => $data['body'] ?? null,

                'destination_url' => $data['destination_url'] ?? null,

                'call_to_action' => $data['call_to_action'] ?? null,
'image_url' => Storage::url($imagePath),

                'image_hash' => $imageHash,

                'meta_id' => $metaCreativeId,

                'json_payload' => $payload,

                'status' => $data['status'] ?? Creative::STATUS_DRAFT

            ]);


            DB::commit();


            Log::info('CREATIVE_CREATED', [

                'creative_id' => $creative->id,

                'meta_id' => $metaCreativeId

            ]);


            return redirect()
                ->route('admin.creatives.index')
                ->with('success', 'Creative created and synced.');

        } catch (Throwable $e) {

            DB::rollBack();

            Log::error('CREATIVE_STORE_FAILED', [

                'error' => $e->getMessage()

            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'meta' => $e->getMessage()
                ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit(Creative $creative)
    {
        return view('admin.creatives.edit', compact('creative'));
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, Creative $creative)
    {
        $data = $request->validate([

            'name' => 'required|string|max:255',

            'headline' => 'nullable|string|max:255',

            'body' => 'nullable|string',

            'destination_url' => 'nullable|url',

            'call_to_action' => 'nullable|string|max:50',

            'image' => 'nullable|image|max:4096'

        ]);

        try {

            if ($request->hasFile('image')) {

                if ($creative->image_url) {
                    Storage::disk('public')->delete($creative->image_url);
                }

                $creative->image_url = $request->file('image')
                    ->store('creatives','public');
            }

            $creative->update($data);

            return redirect()
                ->route('admin.creatives.index')
                ->with('success','Creative updated.');

        } catch (Throwable $e) {

            Log::error('CREATIVE_UPDATE_FAILED', [

                'error' => $e->getMessage()

            ]);

            return back()->withErrors([
                'meta' => 'Unable to update creative'
            ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function destroy(Creative $creative)
    {
        try {

            if ($creative->image_url) {
                Storage::disk('public')->delete($creative->image_url);
            }

            $creative->delete();

            return back()->with('success','Creative deleted.');

        } catch (Throwable $e) {

            Log::error('CREATIVE_DELETE_FAILED', [

                'error' => $e->getMessage()

            ]);

            return back()->withErrors([
                'meta' => 'Unable to delete creative.'
            ]);
        }
    }

public function sync($id)
{
    try {

        $creative = Creative::findOrFail($id);

        if (!$creative->meta_id) {
            return back()->with('error', 'Creative is not connected to Meta.');
        }

        /*
        |---------------------------------------------------------
        | Get Creative From Meta
        |---------------------------------------------------------
        */

        $meta = $this->meta->getCreative($creative->meta_id);

        if (!$meta || isset($meta['error'])) {

            Log::error('META_CREATIVE_SYNC_ERROR', [
                'creative_id' => $creative->id,
                'meta_id' => $creative->meta_id,
                'meta_response' => $meta
            ]);

            return back()->with('error','Unable to fetch creative from Meta.');
        }

        /*
        |---------------------------------------------------------
        | Extract Basic Status
        |---------------------------------------------------------
        */

        $status = $meta['status'] ?? $creative->status;
        $effectiveStatus = $meta['effective_status'] ?? $creative->effective_status;

        $reviewStatus = null;
        $reviewFeedback = null;

        /*
        |---------------------------------------------------------
        | Try Creative Review Feedback
        |---------------------------------------------------------
        */

        if (!empty($meta['ad_review_feedback'])) {

            $reviewStatus =
                $meta['ad_review_feedback']['global']['review_status']
                ?? null;

            $reviewFeedback =
                $meta['ad_review_feedback']['global']['message']
                ?? null;
        }

        /*
        |---------------------------------------------------------
        | If Creative has no review info → check Ads
        |---------------------------------------------------------
        */

        if (!$reviewStatus) {

         $ads = $this->meta->getAds(
    config('services.meta.ad_account_id')
);

            if (!empty($ads['data'])) {

                foreach ($ads['data'] as $ad) {

                    if (
                        isset($ad['creative']['id']) &&
                        $ad['creative']['id'] == $creative->meta_id
                    ) {

                       $reviewStatus = match ($ad['effective_status']) {

    'ACTIVE' => 'APPROVED',

    'PAUSED' => 'APPROVED',

    'DISAPPROVED' => 'DISAPPROVED',

    default => 'PENDING_REVIEW'
};

                        $effectiveStatus = $ad['effective_status'];

                        break;
                    }
                }
            }
        }

        /*
        |---------------------------------------------------------
        | Final fallback
        |---------------------------------------------------------
        */

        if (!$reviewStatus) {

            $reviewStatus = match ($effectiveStatus) {

                'ACTIVE' => 'APPROVED',

                'DISAPPROVED' => 'DISAPPROVED',

                'PAUSED',
                'IN_PROCESS',
                'PENDING_REVIEW' => 'PENDING_REVIEW',

                default => 'PENDING_REVIEW'
            };
        }

        /*
        |---------------------------------------------------------
        | Update DB
        |---------------------------------------------------------
        */

        $creative->update([
            'status' => $status,
            'effective_status' => $effectiveStatus,
            'review_status' => $reviewStatus,
            'review_feedback' => $reviewFeedback,
            'last_synced_at' => now()
        ]);

        return back()->with('success','Creative synced successfully.');

    } catch (\Throwable $e) {

        Log::error('CREATIVE_SYNC_FAILED',[
            'creative_id'=>$id,
            'error'=>$e->getMessage()
        ]);

        return back()->with('error','Sync failed: '.$e->getMessage());
    }
}

    /*
    |--------------------------------------------------------------------------
    | PREVIEW
    |--------------------------------------------------------------------------
    */

    public function preview(Creative $creative)
    {
        return view('admin.creatives.preview', compact('creative'));
    }
    /*
|--------------------------------------------------------------------------
| ACTIVATE CREATIVE
|--------------------------------------------------------------------------
*/

public function activate(Creative $creative)
{
    try {

        if(!$creative->meta_id){
            return back()->withErrors([
                'meta' => 'Creative not synced with Meta.'
            ]);
        }

        $this->meta->updateCreative(
            $creative->meta_id,
            ['status' => 'ACTIVE']
        );

        $creative->update([
            'effective_status' => 'ACTIVE'
        ]);

        return back()->with('success','Creative activated.');

    } catch(Throwable $e){

        Log::error('CREATIVE_ACTIVATE_FAILED',[
            'error'=>$e->getMessage()
        ]);

        return back()->withErrors([
            'meta'=>'Unable to activate creative.'
        ]);
    }
}
/*
|--------------------------------------------------------------------------
| PAUSE CREATIVE
|--------------------------------------------------------------------------
*/

public function pause(Creative $creative)
{
    try {

        if(!$creative->meta_id){
            return back()->withErrors([
                'meta'=>'Creative not synced with Meta.'
            ]);
        }

        $this->meta->updateCreative(
            $creative->meta_id,
            ['status'=>'PAUSED']
        );

        $creative->update([
            'effective_status'=>'PAUSED'
        ]);

        return back()->with('success','Creative paused.');

    } catch(Throwable $e){

        Log::error('CREATIVE_PAUSE_FAILED',[
            'error'=>$e->getMessage()
        ]);

        return back()->withErrors([
            'meta'=>'Unable to pause creative.'
        ]);
    }
}

}