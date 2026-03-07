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
    protected $meta;

    public function __construct(MetaAdsService $meta)
    {
        $this->meta = $meta;
    }

    /*
    |--------------------------------------------------------------------------
    | List Ads
    |--------------------------------------------------------------------------
    */

    public function index(): View
    {
        $ads = Ad::with([
            'adSet.campaign',
            'creative'
        ])
        ->latest()
        ->paginate(20);

        return view('admin.ads.index', compact('ads'));
    }


    /*
    |--------------------------------------------------------------------------
    | Create Ad Form
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
    | Store Ad (Meta + Local)
    |--------------------------------------------------------------------------
    */

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'adset_id'    => ['required','exists:ad_sets,id'],
            'creative_id' => ['required','exists:creatives,id'],
            'name'        => ['required','string','max:255']
        ]);

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Load Models
            |--------------------------------------------------------------------------
            */

            $adset = AdSet::with('campaign.adAccount')
                ->findOrFail($data['adset_id']);

            $creative = Creative::findOrFail($data['creative_id']);

            $adAccount = $adset->campaign->adAccount ?? null;

            /*
            |--------------------------------------------------------------------------
            | Validate Meta Sync
            |--------------------------------------------------------------------------
            */

            if (!$adset->meta_id) {
                throw new Exception('AdSet not synced with Meta.');
            }

            if (!$creative->meta_id) {
                throw new Exception('Creative not synced with Meta.');
            }

            if (!$adAccount || !$adAccount->meta_id) {
                throw new Exception('Ad Account not connected.');
            }

            $metaAccountId = $adAccount->meta_id;

            if (strpos($metaAccountId,'act_') !== 0) {
                $metaAccountId = 'act_'.$metaAccountId;
            }

            /*
            |--------------------------------------------------------------------------
            | Prepare Meta Payload
            |--------------------------------------------------------------------------
            */

            $payload = [

                'name' => $data['name'],

                'adset_id' => $adset->meta_id,

                'creative' => [
                    'creative_id' => $creative->meta_id
                ],

                'status' => 'PAUSED'
            ];

            Log::info('META_AD_CREATE_REQUEST', [
                'account_id' => $metaAccountId,
                'payload' => $payload
            ]);

            /*
            |--------------------------------------------------------------------------
            | Create Ad on Meta
            |--------------------------------------------------------------------------
            */

            $response = $this->meta->createAd(
                $metaAccountId,
                $payload
            );

            Log::info('META_AD_CREATE_RESPONSE', $response);

            if (!is_array($response) || empty($response['id'])) {

                $metaError = $response['error']['message']
                    ?? 'Meta API failed to create Ad';

                throw new Exception($metaError);
            }

            /*
            |--------------------------------------------------------------------------
            | Save Local Ad
            |--------------------------------------------------------------------------
            */

            $ad = Ad::create([

                'adset_id'    => $adset->id,

                'creative_id' => $creative->id,

                'meta_ad_id'  => $response['id'],

                'name'        => $data['name'],

                'status'      => 'PAUSED'
            ]);

            DB::commit();

            Log::info('META_AD_CREATED', [
                'local_ad_id' => $ad->id,
                'meta_ad_id'  => $response['id']
            ]);

            return redirect()
                ->route('admin.ads.index')
                ->with('success','Ad created successfully.');

        }

        catch (Throwable $e) {

            DB::rollBack();

            Log::error('AD_CREATION_EXCEPTION', [
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
    | Ads by AdSet (AJAX)
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
    | Ad Creative Preview
    |--------------------------------------------------------------------------
    */

    public function preview(Ad $ad): JsonResponse
    {
        $creative = $ad->creative;

        return response()->json([
            'image_url' => $creative->image_url ?? null,
            'video_url' => $creative->video_url ?? null,
            'title'     => $creative->title ?? '',
            'body'      => $creative->body ?? '',
            'call_to_action' => $creative->call_to_action ?? ''
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Update Ad Status
    |--------------------------------------------------------------------------
    */

    public function updateStatus(Request $request, Ad $ad): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required','in:PAUSED,ACTIVE,ARCHIVED']
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
    | Delete Ad
    |--------------------------------------------------------------------------
    */

    public function destroy(Ad $ad): RedirectResponse
    {
        try {

            if ($ad->meta_ad_id) {

                $this->meta->deleteAd($ad->meta_ad_id);

            }

            $ad->delete();

            return back()->with('success','Ad deleted');

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
}