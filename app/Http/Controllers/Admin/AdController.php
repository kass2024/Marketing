<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Creative;
use App\Services\MetaAdsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdController extends Controller
{
    protected MetaAdsService $meta;

    public function __construct(MetaAdsService $meta)
    {
        $this->meta = $meta;
    }

    /*
    |--------------------------------------------------------------------------
    | List Ads
    |--------------------------------------------------------------------------
    */

    public function index()
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
    | Show Create Ad Form
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $adsets = AdSet::with([
                'campaign.adAccount'
            ])
            ->latest()
            ->get();

        $creatives = Creative::latest()->get();

        return view('admin.ads.create', compact('adsets', 'creatives'));
    }


    /*
    |--------------------------------------------------------------------------
    | Store Ad (Meta + Local)
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $data = $request->validate([
            'ad_set_id'   => 'required|exists:ad_sets,id',
            'creative_id' => 'required|exists:creatives,id',
            'name'        => 'required|string|max:255'
        ]);

        try {

            DB::beginTransaction();

            $adset = AdSet::with('campaign.adAccount')
                ->findOrFail($data['ad_set_id']);

            $creative = Creative::findOrFail($data['creative_id']);

            /*
            |--------------------------------------------------------------------------
            | Validate Meta Sync
            |--------------------------------------------------------------------------
            */

            if (!$adset->meta_id) {
                throw new \Exception('Selected AdSet is not synced with Meta.');
            }

            if (!$creative->meta_id) {
                throw new \Exception('Selected Creative is not synced with Meta.');
            }

            if (!$adset->campaign || !$adset->campaign->adAccount) {
                throw new \Exception('AdSet missing Campaign or AdAccount relation.');
            }

            $adAccountMetaId = $adset->campaign->adAccount->meta_id;

            if (!$adAccountMetaId) {
                throw new \Exception('AdAccount is not synced with Meta.');
            }

            /*
            |--------------------------------------------------------------------------
            | Create Ad on Meta
            |--------------------------------------------------------------------------
            */

            Log::info('Creating Meta Ad', [
                'ad_account_meta_id' => $adAccountMetaId,
                'adset_meta_id'      => $adset->meta_id,
                'creative_meta_id'   => $creative->meta_id
            ]);

            $response = $this->meta->createAd(
                $adAccountMetaId,
                [
                    'name' => $data['name'],
                    'adset_id' => $adset->meta_id,
                    'creative' => [
                        'creative_id' => $creative->meta_id
                    ],
                    'status' => 'PAUSED'
                ]
            );

            if (empty($response['id'])) {

                Log::error('Meta Ad creation failed', [
                    'response' => $response
                ]);

                throw new \Exception(
                    $response['error']['message'] ??
                    'Meta Ad creation failed.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Store Local Ad
            |--------------------------------------------------------------------------
            */

            $ad = Ad::create([
                'ad_set_id'   => $adset->id,
                'creative_id' => $creative->id,
                'meta_id'     => $response['id'],
                'name'        => $data['name'],
                'status'      => 'PAUSED'
            ]);

            DB::commit();

            Log::info('Ad created successfully', [
                'ad_id'  => $ad->id,
                'meta_id'=> $response['id']
            ]);

            return redirect()
                ->route('admin.ads.index')
                ->with('success', 'Ad created successfully.');

        } catch (\Throwable $e) {

            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Ad creation failed', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'meta' => 'Ad creation failed: ' . $e->getMessage()
                ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Load Ads for AdSet (AJAX)
    |--------------------------------------------------------------------------
    */

    public function byAdSet(int $adsetId)
    {
        $ads = Ad::where('ad_set_id', $adsetId)
            ->latest()
            ->get([
                'id',
                'name',
                'status',
                'impressions',
                'clicks',
                'spend'
            ]);

        return response()->json([
            'success' => true,
            'data' => $ads
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Update Ad Status
    |--------------------------------------------------------------------------
    */

    public function updateStatus(Request $request, Ad $ad)
    {
        $data = $request->validate([
            'status' => 'required|string'
        ]);

        $ad->update([
            'status' => $data['status']
        ]);

        return back()->with('success', 'Ad status updated.');
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Ad
    |--------------------------------------------------------------------------
    */

    public function destroy(Ad $ad)
    {
        try {

            Log::info('Deleting Ad', [
                'ad_id' => $ad->id,
                'meta_id' => $ad->meta_id
            ]);

            $ad->delete();

            return back()->with('success', 'Ad deleted.');

        } catch (\Throwable $e) {

            Log::error('Ad deletion failed', [
                'message' => $e->getMessage()
            ]);

            return back()->withErrors([
                'meta' => 'Unable to delete ad.'
            ]);
        }
    }
}