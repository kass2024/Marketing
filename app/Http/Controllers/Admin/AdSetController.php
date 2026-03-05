<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

use App\Models\Campaign;
use App\Models\AdSet;
use App\Services\MetaAdsService;

class AdSetController extends Controller
{
    protected MetaAdsService $meta;

    public function __construct(MetaAdsService $meta)
    {
        $this->meta = $meta;
    }

    /*
    |--------------------------------------------------------------------------
    | Create AdSet Form
    |--------------------------------------------------------------------------
    */

    public function create(int $campaignId)
    {
        $campaign = Campaign::with('adAccount')->findOrFail($campaignId);

        if (!$campaign->meta_id) {
            return back()->withErrors([
                'meta' => 'Campaign is not synced with Meta.'
            ]);
        }

        if (!$campaign->adAccount) {
            return back()->withErrors([
                'meta' => 'Campaign has no Ad Account.'
            ]);
        }

        return view('admin.adsets.create', [
            'campaign' => $campaign
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Store AdSet
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $data = $request->validate([

            'campaign_id'   => 'required|exists:campaigns,id',
            'name'          => 'required|string|max:255',

            'daily_budget'  => 'required|numeric|min:5',

            'age_min'       => 'required|integer|min:18|max:65',
            'age_max'       => 'required|integer|min:18|max:65',

            'countries'     => 'required|array|min:1',
            'countries.*'   => 'string|size:2',

            'genders'       => 'nullable|array',
            'genders.*'     => 'integer|in:1,2',

            'placements'    => 'nullable|array',
            'devices'       => 'nullable|array',

            'interests'     => 'nullable|string'
        ]);

        if ($data['age_min'] >= $data['age_max']) {
            return back()
                ->withErrors([
                    'age' => 'Age max must be greater than age min.'
                ])
                ->withInput();
        }

        try {

            $campaign = Campaign::with('adAccount')
                ->findOrFail($data['campaign_id']);

            if (!$campaign->meta_id) {
                throw new \Exception('Campaign not synced with Meta.');
            }

            if (!$campaign->adAccount) {
                throw new \Exception('Campaign has no Ad Account.');
            }

            /*
            |--------------------------------------------------------------------------
            | Build Targeting
            |--------------------------------------------------------------------------
            */

            $targeting = [

                'age_min' => $data['age_min'],
                'age_max' => $data['age_max'],

                'geo_locations' => [
                    'countries' => $data['countries']
                ],

                'publisher_platforms' => $data['placements'] ?? [
                    'facebook',
                    'instagram'
                ]

            ];

            if (!empty($data['genders'])) {
                $targeting['genders'] = $data['genders'];
            }

            if (!empty($data['devices'])) {
                $targeting['device_platforms'] = $data['devices'];
            }

            /*
            |--------------------------------------------------------------------------
            | Interests Parsing
            |--------------------------------------------------------------------------
            */

            if (!empty($data['interests'])) {

                $interests = array_map(
                    'trim',
                    explode(',', $data['interests'])
                );

                $targeting['interests'] = $interests;
            }

            Log::info('Meta AdSet Creation Started', [
                'campaign_meta_id' => $campaign->meta_id,
                'targeting' => $targeting
            ]);

            /*
            |--------------------------------------------------------------------------
            | Create AdSet on Meta
            |--------------------------------------------------------------------------
            */

            $metaResponse = $this->meta->createAdSet(

                $campaign->adAccount->meta_id,

                [
                    'name'              => $data['name'],
                    'campaign_id'       => $campaign->meta_id,
                    'daily_budget'      => $data['daily_budget'] * 100,
                    'billing_event'     => 'IMPRESSIONS',
                    'optimization_goal' => 'REACH',
                    'targeting'         => $targeting,
                    'status'            => 'PAUSED'
                ]
            );

            if (empty($metaResponse['id'])) {

                Log::error('Meta AdSet Creation Failed', [
                    'response' => $metaResponse
                ]);

                throw new \Exception(
                    $metaResponse['error']['message']
                    ?? 'Meta API failed to create AdSet.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Store AdSet Locally
            |--------------------------------------------------------------------------
            */

            DB::beginTransaction();

            $adset = AdSet::create([

                'campaign_id'       => $campaign->id,
                'meta_id'           => $metaResponse['id'],

                'name'              => $data['name'],
                'daily_budget'      => $data['daily_budget'] * 100,

                'optimization_goal' => 'REACH',
                'billing_event'     => 'IMPRESSIONS',

                'targeting'         => $targeting,

                'status'            => 'PAUSED'
            ]);

            DB::commit();

            Log::info('AdSet Created Successfully', [
                'adset_id' => $adset->id,
                'meta_id'  => $metaResponse['id']
            ]);

            return redirect()
                ->route('admin.campaigns.index')
                ->with('success', 'Ad Set created successfully.');

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('AdSet Creation Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()
                ->withErrors([
                    'meta' => 'Unable to create Ad Set: ' . $e->getMessage()
                ])
                ->withInput();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Load AdSets by Campaign (AJAX)
    |--------------------------------------------------------------------------
    */

    public function byCampaign(int $campaignId)
    {
        $adsets = AdSet::where('campaign_id', $campaignId)
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
            'data' => $adsets
        ]);
    }
}