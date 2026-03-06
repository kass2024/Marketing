<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\AdSet;
use App\Models\Ad;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

class AdsManagerController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | Ads Manager Dashboard
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): View
    {
        try {

            $query = Campaign::query();

            // Filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $query->where('name', 'LIKE', '%' . $request->search . '%');
            }

            $campaigns = $query
                ->withCount('adSets')
                ->latest()
                ->paginate(20);

            // Global stats
            $stats = [
                'campaigns' => Campaign::count(),
                'adsets' => AdSet::count(),
                'ads' => Ad::count(),
                'active_campaigns' => Campaign::where('status','ACTIVE')->count()
            ];

            return view('admin.ads.manager', [
                'campaigns' => $campaigns,
                'stats' => $stats
            ]);

        } catch (\Throwable $e) {

            Log::error('AdsManagerController@index failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            abort(500, 'Unable to load Ads Manager.');
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Load AdSets by Campaign
    |--------------------------------------------------------------------------
    */

    public function adsets(Campaign $campaign): JsonResponse
    {
        try {

            $adsets = $campaign->adSets()
                ->withCount('ads')
                ->latest()
                ->get([
                    'id',
                    'name',
                    'status',
                    'daily_budget',
                    'meta_id'
                ]);

            return response()->json([
                'success' => true,
                'campaign' => [
                    'id' => $campaign->id,
                    'name' => $campaign->name
                ],
                'data' => $adsets
            ]);

        } catch (\Throwable $e) {

            Log::error('AdsManagerController@adsets failed', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load Ad Sets'
            ], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Load Ads by AdSet
    |--------------------------------------------------------------------------
    */

    public function ads(AdSet $adset): JsonResponse
    {
        try {

            $ads = $adset->ads()
                ->latest()
                ->get([
                    'id',
                    'name',
                    'status',
                    'impressions',
                    'clicks',
                    'spend',
                    'meta_id'
                ]);

            return response()->json([
                'success' => true,
                'adset' => [
                    'id' => $adset->id,
                    'name' => $adset->name
                ],
                'data' => $ads
            ]);

        } catch (\Throwable $e) {

            Log::error('AdsManagerController@ads failed', [
                'adset_id' => $adset->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load Ads'
            ], 500);
        }
    }



    /*
    |--------------------------------------------------------------------------
    | Toggle Status (Campaign / AdSet / Ad)
    |--------------------------------------------------------------------------
    */

    public function toggleStatus(Request $request): JsonResponse
    {
        try {

            $type = $request->get('type');
            $id = $request->get('id');
            $status = $request->get('status');

            switch ($type) {

                case 'campaign':
                    $item = Campaign::findOrFail($id);
                    break;

                case 'adset':
                    $item = AdSet::findOrFail($id);
                    break;

                case 'ad':
                    $item = Ad::findOrFail($id);
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid type'
                    ], 400);
            }

            $item->update([
                'status' => $status
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status updated'
            ]);

        } catch (\Throwable $e) {

            Log::error('AdsManagerController@toggleStatus failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update status'
            ], 500);
        }
    }



    /*
    |--------------------------------------------------------------------------
    | Load Full Hierarchy (Campaign → AdSets → Ads)
    |--------------------------------------------------------------------------
    */

    public function hierarchy(): JsonResponse
    {
        try {

            $campaigns = Campaign::with([
                'adSets.ads'
            ])->latest()->get();

            return response()->json([
                'success' => true,
                'data' => $campaigns
            ]);

        } catch (\Throwable $e) {

            Log::error('AdsManagerController@hierarchy failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false
            ], 500);
        }
    }

}