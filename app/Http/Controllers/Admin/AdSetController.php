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
            'languages' => config('meta.languages')
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

            $campaign = Campaign::with('adAccount')->findOrFail($data['campaign_id']);

            if (!$campaign->meta_id) {
                throw new Exception('Campaign not synced with Meta');
            }

            if (!$campaign->adAccount || !$campaign->adAccount->meta_id) {
                throw new Exception('Ad account not connected');
            }

            /*
            |--------------------------------------------------------------------------
            | TARGETING (Meta Safe Structure)
            |--------------------------------------------------------------------------
            */

            $targeting = [
                'geo_locations' => [
                    'countries' => array_values($data['countries'])
                ]
            ];

            /*
            |--------------------------------------------------------------------------
            | AGE
            |--------------------------------------------------------------------------
            */

            $targeting['age_min'] = (int) $data['age_min'];
            $targeting['age_max'] = (int) $data['age_max'];

            /*
            |--------------------------------------------------------------------------
            | GENDER
            |--------------------------------------------------------------------------
            */

            if (!empty($data['genders'])) {
                $targeting['genders'] = array_map('intval', $data['genders']);
            }

            /*
            |--------------------------------------------------------------------------
            | INTERESTS (Safe Usage)
            |--------------------------------------------------------------------------
            */

            if (!empty($data['interests'])) {

                $targeting['interests'] = collect($data['interests'])
                    ->take(5) // limit interests to avoid conflicts
                    ->map(fn($id) => ['id' => (string)$id])
                    ->values()
                    ->toArray();
            }

            /*
            |--------------------------------------------------------------------------
            | LANGUAGES (only if multiple countries)
            |--------------------------------------------------------------------------
            */

            if (!empty($data['languages']) && count($data['countries']) > 1) {

                $targeting['locales'] = array_map('intval', $data['languages']);
            }

            /*
            |--------------------------------------------------------------------------
            | PLACEMENTS
            |--------------------------------------------------------------------------
            */

            if ($data['placement_type'] === 'manual') {

                if (empty($data['publisher_platforms'])) {
                    throw new Exception('Select at least one platform');
                }

                $targeting['publisher_platforms'] = $data['publisher_platforms'];

                if (in_array('facebook', $data['publisher_platforms'])) {
                    $targeting['facebook_positions'] = ['feed', 'story'];
                }

                if (in_array('instagram', $data['publisher_platforms'])) {
                    $targeting['instagram_positions'] = ['stream', 'story'];
                }

                if (in_array('messenger', $data['publisher_platforms'])) {
                    $targeting['messenger_positions'] = ['messenger_home'];
                }

                if (in_array('audience_network', $data['publisher_platforms'])) {
                    $targeting['audience_network_positions'] = ['classic'];
                }

            } else {

                // Automatic placements recommended by Meta
                $targeting['publisher_platforms'] = [
                    'facebook',
                    'instagram'
                ];
            }

            Log::info('META_TARGETING_FINAL', $targeting);

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

                'optimization_goal' => 'REACH',

                'status' => 'PAUSED',

                'targeting' => $targeting
            ];

            Log::info('META_ADSET_PAYLOAD', $payload);

            /*
            |--------------------------------------------------------------------------
            | CREATE META ADSET
            |--------------------------------------------------------------------------
            */

            $response = $this->meta->createAdSet(
                $campaign->adAccount->meta_id,
                $payload
            );

            Log::info('META_RESPONSE', $response);

            if (!isset($response['id'])) {

                throw new Exception(
                    $response['error']['message'] ?? 'Meta API error'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | SAVE LOCAL COPY
            |--------------------------------------------------------------------------
            */

            $adset = AdSet::create([

                'campaign_id' => $campaign->id,

                'meta_id' => $response['id'],

                'name' => $data['name'],

                'daily_budget' => $payload['daily_budget'],

                'billing_event' => 'IMPRESSIONS',

                'optimization_goal' => 'REACH',

                'targeting' => $targeting,

                'status' => 'PAUSED'
            ]);

            DB::commit();

            Log::info('META_ADSET_CREATED', [
                'meta_id' => $response['id'],
                'local_id' => $adset->id
            ]);

            return redirect()
                ->route('admin.campaigns.show', $campaign->id)
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
}