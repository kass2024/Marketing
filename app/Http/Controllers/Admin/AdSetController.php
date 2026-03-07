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
    protected MetaAdsService $meta;

    public function __construct(MetaAdsService $meta)
    {
        $this->meta = $meta;
    }

    /*
    |--------------------------------------------------------------------------
    | LIST ALL ADSETS
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
    | LIST ADSETS BY CAMPAIGN  (FIXES YOUR ERROR)
    |--------------------------------------------------------------------------
    */

    public function indexByCampaign(int $campaignId)
    {
        $campaign = Campaign::findOrFail($campaignId);

        $adsets = AdSet::where('campaign_id', $campaignId)
            ->latest()
            ->paginate(20);

        return view('admin.adsets.index', [
            'campaign' => $campaign,
            'adsets' => $adsets
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE FORM
    |--------------------------------------------------------------------------
    */

    public function create(int $campaignId = null)
    {
        Log::info('META_ADSET_FORM_OPENED', [
            'campaign_id' => $campaignId
        ]);

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
    | STORE ADSET
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        Log::info('META_ADSET_STORE_REQUEST', $request->all());

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
            'interests' => 'nullable|array',

            'placement_type' => 'required|in:automatic,manual',
            'publisher_platforms' => 'nullable|array'
        ]);

        if ($data['age_min'] >= $data['age_max']) {
            return back()->withErrors([
                'age' => 'Max age must be greater than min age'
            ])->withInput();
        }

        DB::beginTransaction();

        try {

            $campaign = Campaign::with('adAccount')
                ->findOrFail($data['campaign_id']);

            if (!$campaign->meta_id) {
                throw new Exception('Campaign not synced with Meta');
            }

            if (!$campaign->adAccount || !$campaign->adAccount->meta_id) {
                throw new Exception('Ad account not connected');
            }

            /*
            |--------------------------------------------------------------------------
            | TARGETING
            |--------------------------------------------------------------------------
            */

            $targeting = [

                'geo_locations' => [
                    'countries' => array_values($data['countries'])
                ],

                'age_min' => (int)$data['age_min'],
                'age_max' => (int)$data['age_max']
            ];

            if (!empty($data['genders'])) {
                $targeting['genders'] = array_map('intval', $data['genders']);
            }

            if (!empty($data['interests'])) {

                $targeting['flexible_spec'][] = [
                    'interests' => collect($data['interests'])
                        ->take(5)
                        ->map(fn ($id) => ['id' => (string)$id])
                        ->values()
                        ->toArray()
                ];

                $targeting['targeting_automation'] = [
                    'advantage_audience' => 0
                ];
            }

            if (!empty($data['languages']) && count($data['countries']) > 1) {
                $targeting['locales'] = array_map('intval', $data['languages']);
            }

            if ($data['placement_type'] === 'manual') {

                if (empty($data['publisher_platforms'])) {
                    throw new Exception('Select at least one platform');
                }

                $targeting['publisher_platforms'] = $data['publisher_platforms'];
            }

            /*
            |--------------------------------------------------------------------------
            | META PAYLOAD
            |--------------------------------------------------------------------------
            */

            $payload = [

                'name' => $data['name'],

                'campaign_id' => $campaign->meta_id,

                'daily_budget' => (int)$data['daily_budget'] * 100,

                'billing_event' => 'IMPRESSIONS',

                'optimization_goal' => 'LINK_CLICKS',

                'bid_strategy' => $data['bid_strategy'],

                'status' => 'PAUSED',

                'start_time' => now()->addMinutes(5)->toIso8601String(),

                'promoted_object' => [
                    'page_id' => $data['page_id']
                ],

                'targeting' => $targeting
            ];

            Log::info('META_ADSET_PAYLOAD', $payload);

            $response = $this->meta->createAdSet(
                $campaign->adAccount->meta_id,
                $payload
            );

            if (!isset($response['id'])) {
                throw new Exception(
                    $response['error']['message'] ?? 'Meta API error'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | SAVE LOCAL
            |--------------------------------------------------------------------------
            */

            $adset = AdSet::create([

                'campaign_id' => $campaign->id,

                'meta_id' => $response['id'],

                'name' => $data['name'],

                'daily_budget' => $payload['daily_budget'],

                'billing_event' => 'IMPRESSIONS',

                'optimization_goal' => 'LINK_CLICKS',

                'targeting' => $targeting,

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
    | SHOW ADSET
    |--------------------------------------------------------------------------
    */

    public function show(int $id)
    {
        $adset = AdSet::with('campaign')->findOrFail($id);

        return view('admin.adsets.show', compact('adset'));
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE ADSET
    |--------------------------------------------------------------------------
    */

    public function destroy(int $id)
    {
        $adset = AdSet::findOrFail($id);

        try {

            if ($adset->meta_id) {

                $this->meta->deleteAdSet($adset->meta_id);
            }

            $adset->delete();

            return back()->with('success', 'AdSet deleted');

        } catch (Throwable $e) {

            Log::error('ADSET_DELETE_FAILED', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return back()->withErrors([
                'delete' => 'Failed to delete adset'
            ]);
        }
    }
}