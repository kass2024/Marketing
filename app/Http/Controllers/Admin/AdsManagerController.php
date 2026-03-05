<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\AdSet;
use App\Models\Ad;
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

    public function index(): View
    {
        try {

            $campaigns = Campaign::query()
                ->latest()
                ->select([
                    'id',
                    'name',
                    'objective',
                    'daily_budget',
                    'status',
                    'spend',
                    'clicks'
                ])
                ->get();

            return view('admin.ads.manager', [
                'campaigns' => $campaigns
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
    | Load AdSets by Campaign (AJAX)
    |--------------------------------------------------------------------------
    */

    public function adsets(Campaign $campaign): JsonResponse
    {
        try {

            $adsets = $campaign->adSets()
                ->latest()
                ->select([
                    'id',
                    'name',
                    'status',
                    'daily_budget',
                    'meta_id'
                ])
                ->get();

            return response()->json([
                'success' => true,
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
    | Load Ads by AdSet (AJAX)
    |--------------------------------------------------------------------------
    */

    public function ads(AdSet $adset): JsonResponse
    {
        try {

            $ads = $adset->ads()
                ->latest()
                ->select([
                    'id',
                    'name',
                    'status',
                    'impressions',
                    'clicks',
                    'spend'
                ])
                ->get();

            return response()->json([
                'success' => true,
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

}