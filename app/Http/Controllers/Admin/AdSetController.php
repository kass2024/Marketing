<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\Campaign;
use App\Models\AdSet;
use App\Services\MetaAdsService;

use Throwable;
use Exception;

class AdSetController extends Controller
{
    protected $meta;

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
        $adsets = AdSet::with('campaign')
            ->latest()
            ->paginate(20);

        return view('admin.adsets.index', compact('adsets'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE FORM
    |--------------------------------------------------------------------------
    */

    public function create($campaignId = null)
    {
        return view('admin.adsets.create', [

            'campaigns' => Campaign::latest()->get(),

            'selectedCampaign' => $campaignId,

            'countries' => config('meta.countries'),

            'languages' => config('meta.languages'),

            'pages' => $this->meta->getPages()

        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        Log::info('META_ADSET_REQUEST', $request->all());

        $data = $request->validate([

            'campaign_id' => 'required|exists:campaigns,id',

            'name' => 'required|string|max:255',

            'daily_budget' => 'required|numeric|min:5',

            'bid_strategy' => 'required|string',

            'page_id' => 'required|string',

            'age_min' => 'required|integer|min:18|max:65',

            'age_max' => 'required|integer|min:18|max:65',

            'countries' => 'required|array|min:1',

            'genders' => 'nullable|array',

            'languages' => 'nullable|array',

            'interests' => 'nullable|array|max:5',

            'placement_type' => 'required|in:automatic,manual',

            'publisher_platforms' => 'nullable|array'

        ]);


        if ($data['age_min'] >= $data['age_max']) {
            return back()->withErrors([
                'age' => 'Maximum age must be greater than minimum age'
            ])->withInput();
        }

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | CAMPAIGN
            |--------------------------------------------------------------------------
            */

            $campaign = Campaign::with('adAccount')
                ->findOrFail($data['campaign_id']);

            if (!$campaign->meta_id) {
                throw new Exception('Campaign not synced with Meta');
            }

            if (!$campaign->adAccount || !$campaign->adAccount->meta_id) {
                throw new Exception('Ad Account not connected');
            }

            $accountId = $campaign->adAccount->meta_id;

            if (!str_starts_with($accountId, 'act_')) {
                $accountId = 'act_' . $accountId;
            }

            /*
            |--------------------------------------------------------------------------
            | OBJECTIVE SAFE SETTINGS
            |--------------------------------------------------------------------------
            */

            $objective = strtoupper($campaign->objective);

            $optimizationMap = [

                'TRAFFIC' => 'LINK_CLICKS',

                'OUTCOME_TRAFFIC' => 'LINK_CLICKS',

                'LEADS' => 'LEAD_GENERATION',

                'OUTCOME_LEADS' => 'LEAD_GENERATION',

                'SALES' => 'OFFSITE_CONVERSIONS',

                'OUTCOME_SALES' => 'OFFSITE_CONVERSIONS',

                'AWARENESS' => 'REACH',

                'ENGAGEMENT' => 'POST_ENGAGEMENT'
            ];

            $optimizationGoal =
                $optimizationMap[$objective] ?? 'LINK_CLICKS';

            $billingEvent = 'IMPRESSIONS';


            /*
            |--------------------------------------------------------------------------
            | TARGETING
            |--------------------------------------------------------------------------
            */

            $targeting = [

                'geo_locations' => [
                    'countries' => array_values($data['countries'])
                ],

                'age_min' => (int) $data['age_min'],

                'age_max' => (int) $data['age_max']
            ];

            if (!empty($data['genders'])) {

                $targeting['genders'] =
                    array_map('intval', $data['genders']);
            }


            /*
            |--------------------------------------------------------------------------
            | INTERESTS
            |--------------------------------------------------------------------------
            */

            if (!empty($data['interests'])) {

                $interestList = [];

                foreach ($data['interests'] as $interestId) {

                    $interestList[] = [
                        'id' => (string) $interestId
                    ];
                }

                $targeting['flexible_spec'] = [
                    [
                        'interests' => $interestList
                    ]
                ];

                $targeting['targeting_automation'] = [
                    'advantage_audience' => 0
                ];
            }


            /*
            |--------------------------------------------------------------------------
            | LANGUAGES
            |--------------------------------------------------------------------------
            */

            if (!empty($data['languages'])) {

                $targeting['locales'] =
                    array_map('intval', $data['languages']);
            }


            /*
            |--------------------------------------------------------------------------
            | PLACEMENTS
            |--------------------------------------------------------------------------
            */

            if ($data['placement_type'] === 'manual') {

                if (empty($data['publisher_platforms'])) {
                    throw new Exception('Select at least one placement');
                }

                $targeting['publisher_platforms'] =
                    $data['publisher_platforms'];
            }


            /*
            |--------------------------------------------------------------------------
            | PAYLOAD
            |--------------------------------------------------------------------------
            */

            $payload = [

                'name' => $data['name'],

                'campaign_id' => $campaign->meta_id,

                'daily_budget' => (int)$data['daily_budget'] * 100,

                'billing_event' => $billingEvent,

                'optimization_goal' => $optimizationGoal,

                'bid_strategy' => $data['bid_strategy'],

                'status' => 'PAUSED',

                'start_time' => now()
                    ->addMinutes(5)
                    ->toIso8601String(),

                'promoted_object' => [
                    'page_id' => $data['page_id']
                ],

                'targeting' => $targeting
            ];


            Log::info('META_ADSET_PAYLOAD', $payload);


            /*
            |--------------------------------------------------------------------------
            | CREATE META
            |--------------------------------------------------------------------------
            */

            $response = $this->meta->createAdSet(
                $accountId,
                $payload
            );


            Log::info('META_ADSET_RESPONSE', $response);


            if (!isset($response['id'])) {

                throw new Exception(
                    $response['error']['message']
                        ?? 'Meta failed to create AdSet'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | SAVE
            |--------------------------------------------------------------------------
            */

            $adset = AdSet::create([

                'campaign_id' => $campaign->id,

                'meta_id' => $response['id'],

                'name' => $data['name'],

                'daily_budget' => $payload['daily_budget'],

                'billing_event' => $billingEvent,

                'optimization_goal' => $optimizationGoal,

                'targeting' => json_encode($targeting),

                'status' => 'PAUSED'
            ]);


            DB::commit();

            Log::info('META_ADSET_CREATED', [
                'meta_id' => $response['id'],
                'local_id' => $adset->id
            ]);

            return redirect()
                ->route('admin.campaigns.index')
                ->with('success', 'Ad Set created successfully');
        }

        catch (Throwable $e) {

            DB::rollBack();

            Log::error('META_ADSET_FAILED', [
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
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $adset = AdSet::findOrFail($id);

        try {

            if ($adset->meta_id) {
                $this->meta->deleteAdSet($adset->meta_id);
            }

            $adset->delete();

            return back()->with(
                'success',
                'AdSet deleted successfully'
            );

        } catch (Throwable $e) {

            Log::error('ADSET_DELETE_FAILED', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return back()->withErrors([
                'delete' => 'Failed to delete AdSet'
            ]);
        }
    }
}