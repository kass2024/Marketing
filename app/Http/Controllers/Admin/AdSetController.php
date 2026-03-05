<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

use App\Models\Campaign;
use App\Models\AdSet;
use App\Models\AdAccount;
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
    | List Ad Sets
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $query = AdSet::with('campaign');

        // Filter by campaign
        if ($request->has('campaign_id')) {
            $query->where('campaign_id', $request->campaign_id);
        }

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Search by name
        if ($request->has('search') && $request->search !== '') {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $adsets = $query->latest()->paginate(20);

        // Get campaigns for filter dropdown
        $campaigns = Campaign::orderBy('name')->get(['id', 'name']);

        // Get summary stats
        $stats = [
            'total' => AdSet::count(),
            'active' => AdSet::where('status', 'ACTIVE')->count(),
            'paused' => AdSet::where('status', 'PAUSED')->count(),
            'draft' => AdSet::where('status', 'DRAFT')->count(),
        ];

        return view('admin.adsets.index', compact('adsets', 'campaigns', 'stats'));
    }

    /*
    |--------------------------------------------------------------------------
    | Show Single Ad Set
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $adset = AdSet::with(['campaign', 'ads'])->findOrFail($id);

        // Get insights from Meta if synced
        $insights = null;
        if ($adset->meta_id) {
            try {
                $insights = $this->meta->getAdSetInsights($adset->meta_id);
            } catch (\Exception $e) {
                Log::warning('Failed to fetch Meta insights', ['error' => $e->getMessage()]);
            }
        }

        return view('admin.adsets.show', compact('adset', 'insights'));
    }

    /*
    |--------------------------------------------------------------------------
    | Create AdSet Form (Standard)
    |--------------------------------------------------------------------------
    */

    public function create(Request $request)
    {
        // Get all campaigns
        $campaigns = Campaign::whereIn('status', ['ACTIVE', 'PAUSED', 'DRAFT'])
            ->orderBy('name')
            ->get(['id', 'name', 'objective', 'meta_id']);

        // Pre-select campaign if provided
        $selectedCampaignId = $request->campaign_id;

        // Get targeting options
        $countries = $this->getTargetingCountries();
        $languages = $this->getTargetingLanguages();
        $interests = $this->getTargetingInterests();
        
        // Get ad account currency
        $adAccount = AdAccount::first();
        $accountCurrency = $adAccount->currency ?? 'USD';

        return view('admin.adsets.create', compact(
            'campaigns',
            'selectedCampaignId',
            'countries',
            'languages',
            'interests',
            'accountCurrency',
            'adAccount'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Create AdSet Form (From Campaign) - FIXED FOR SINGLE BUSINESS
    |--------------------------------------------------------------------------
    */

    public function createFromCampaign(Request $request, $campaignId)
    {
        try {
            // Find campaign directly (no client filtering needed)
            $campaign = Campaign::with('adAccount')->findOrFail($campaignId);

            // Check if campaign has ad account
            if (!$campaign->adAccount) {
                // Try to get the first ad account
                $adAccount = AdAccount::first();
                
                if (!$adAccount) {
                    return redirect()->route('admin.accounts.index')
                        ->with('error', 'Please connect a Meta Ad Account first before creating ad sets.');
                }
                
                // Link the ad account to campaign
                $campaign->ad_account_id = $adAccount->id;
                $campaign->save();
                
                // Refresh the campaign
                $campaign->load('adAccount');
            }

            // Check if campaign is synced with Meta
            if (!$campaign->meta_id) {
                return redirect()->route('admin.campaigns.edit', $campaignId)
                    ->with('warning', 'This campaign needs to be synced with Meta before creating ad sets. Please save the campaign first.');
            }

            // Get all campaigns for dropdown (with this one pre-selected)
            $campaigns = Campaign::whereIn('status', ['ACTIVE', 'PAUSED', 'DRAFT'])
                ->orderBy('name')
                ->get(['id', 'name', 'objective', 'meta_id']);

            $selectedCampaignId = $campaignId;

            // Get targeting options
            $countries = $this->getTargetingCountries();
            $languages = $this->getTargetingLanguages();
            $interests = $this->getTargetingInterests();
            
            $accountCurrency = $campaign->adAccount->currency ?? 'USD';

            return view('admin.adsets.create', compact(
                'campaigns',
                'selectedCampaignId',
                'countries',
                'languages',
                'interests',
                'accountCurrency',
                'campaign'
            ));

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()->route('admin.campaigns.index')
                ->with('error', 'Campaign not found.');
        } catch (\Exception $e) {
            Log::error('Error in createFromCampaign', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->route('admin.campaigns.index')
                ->with('error', 'Unable to load campaign: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | List Ad Sets by Campaign - FIXED FOR SINGLE BUSINESS
    |--------------------------------------------------------------------------
    */

    public function indexByCampaign($campaignId)
    {
        // Find campaign directly (no client filtering needed)
        $campaign = Campaign::findOrFail($campaignId);
        
        // Get ad sets for this campaign
        $adsets = AdSet::where('campaign_id', $campaignId)
            ->with('campaign')
            ->latest()
            ->paginate(20);
        
        // Get summary stats for this campaign
        $stats = [
            'total' => AdSet::where('campaign_id', $campaignId)->count(),
            'active' => AdSet::where('campaign_id', $campaignId)
                ->where('status', 'ACTIVE')->count(),
            'paused' => AdSet::where('campaign_id', $campaignId)
                ->where('status', 'PAUSED')->count(),
            'draft' => AdSet::where('campaign_id', $campaignId)
                ->where('status', 'DRAFT')->count(),
        ];
        
        // Get total budget
        $totalBudget = AdSet::where('campaign_id', $campaignId)
            ->sum('daily_budget') / 100;
        
        return view('admin.adsets.by-campaign', compact('campaign', 'adsets', 'stats', 'totalBudget'));
    }

    /*
    |--------------------------------------------------------------------------
    | Store AdSet
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $data = $request->validate([
            'campaign_id'           => 'required|exists:campaigns,id',
            'name'                  => 'required|string|max:255',
            'daily_budget'          => 'required|numeric|min:1',
            'optimization_goal'     => 'nullable|string',
            'billing_event'         => 'nullable|string',
            'bid_strategy'          => 'nullable|string',
            
            'age_min'               => 'nullable|integer|min:13|max:65',
            'age_max'               => 'nullable|integer|min:13|max:65|gt:age_min',
            'genders'               => 'nullable|array',
            'genders.*'             => 'integer|in:1,2',
            
            'countries'             => 'required|array|min:1',
            'countries.*'           => 'string|size:2',
            'location_type'          => 'nullable|string',
            'exclude_locations'      => 'nullable|boolean',
            
            'languages'             => 'nullable|array',
            'languages.*'           => 'string',
            
            'interests'             => 'nullable|array',
            'interests.*'           => 'string',
            
            'device_platforms'       => 'nullable|array',
            'device_platforms.*'     => 'string',
            
            'publisher_platforms'    => 'nullable|array',
            'publisher_platforms.*'  => 'string',
            
            'facebook_positions'     => 'nullable|array',
            'instagram_positions'    => 'nullable|array',
            'messenger_positions'    => 'nullable|array',
            
            'start_time'             => 'nullable|date',
            'end_time'               => 'nullable|date|after:start_time',
            'status'                 => 'nullable|in:ACTIVE,PAUSED',
            
            'promoted_object_type'   => 'nullable|string',
            'promoted_object_id'     => 'nullable|string',
            'flexible_spec'          => 'nullable|json',
        ]);

        try {
            DB::beginTransaction();

            $campaign = Campaign::with('adAccount')->findOrFail($data['campaign_id']);

            // Check if campaign has ad account
            if (!$campaign->adAccount) {
                // Try to get any ad account
                $adAccount = AdAccount::first();
                
                if (!$adAccount) {
                    throw new \Exception('No Meta Ad Account found. Please connect an ad account first.');
                }
                
                // Link the ad account to campaign
                $campaign->ad_account_id = $adAccount->id;
                $campaign->save();
                $campaign->load('adAccount');
            }

            /*
            |--------------------------------------------------------------------------
            | Build Targeting Specification
            |--------------------------------------------------------------------------
            */

            $targeting = [
                'age_min' => $data['age_min'] ?? 18,
                'age_max' => $data['age_max'] ?? 65,
                'geo_locations' => [
                    'countries' => $data['countries']
                ]
            ];

            // Add genders
            if (!empty($data['genders'])) {
                $targeting['genders'] = $data['genders'];
            }

            // Add languages
            if (!empty($data['languages'])) {
                $targeting['locales'] = array_map(function($lang) {
                    return $lang;
                }, $data['languages']);
            }

            // Add location type
            if (!empty($data['location_type'])) {
                $targeting['location_types'] = [$data['location_type']];
            }

            // Add exclusions
            if (!empty($data['exclude_locations'])) {
                $targeting['excluded_geo_locations'] = [
                    'countries' => $data['countries']
                ];
            }

            // Add device platforms
            if (!empty($data['device_platforms'])) {
                $targeting['device_platforms'] = $data['device_platforms'];
            }

            // Add publisher platforms
            if (!empty($data['publisher_platforms'])) {
                $targeting['publisher_platforms'] = $data['publisher_platforms'];
            }

            // Build placements
            $placements = [];
            if (!empty($data['facebook_positions'])) {
                $placements['facebook'] = $data['facebook_positions'];
            }
            if (!empty($data['instagram_positions'])) {
                $placements['instagram'] = $data['instagram_positions'];
            }
            if (!empty($data['messenger_positions'])) {
                $placements['messenger'] = $data['messenger_positions'];
            }
            if (!empty($placements)) {
                $targeting['publisher_platforms'] = array_keys($placements);
                $targeting['facebook_positions'] = $placements['facebook'] ?? [];
                $targeting['instagram_positions'] = $placements['instagram'] ?? [];
                $targeting['messenger_positions'] = $placements['messenger'] ?? [];
            }

            // Add flexible spec if provided
            if (!empty($data['flexible_spec'])) {
                $targeting['flexible_spec'] = json_decode($data['flexible_spec'], true);
            }

            // Add interests if provided
            if (!empty($data['interests'])) {
                $targeting['interests'] = array_map(function($interest) {
                    return ['id' => $interest];
                }, $data['interests']);
            }

            Log::info('Meta AdSet Creation Started', [
                'campaign_id' => $campaign->id,
                'campaign_meta_id' => $campaign->meta_id,
                'targeting' => $targeting
            ]);

            /*
            |--------------------------------------------------------------------------
            | Create AdSet on Meta (if campaign is synced)
            |--------------------------------------------------------------------------
            */

            $metaResponse = null;
            if ($campaign->meta_id) {
                $metaResponse = $this->meta->createAdSet(
                    $campaign->adAccount->meta_id,
                    [
                        'name'              => $data['name'],
                        'campaign_id'       => $campaign->meta_id,
                        'daily_budget'      => (int)($data['daily_budget'] * 100), // Convert to cents
                        'billing_event'     => $data['billing_event'] ?? 'IMPRESSIONS',
                        'optimization_goal' => $data['optimization_goal'] ?? 'REACH',
                        'targeting'         => $targeting,
                        'status'            => $data['status'] ?? 'PAUSED',
                        'start_time'        => $data['start_time'] ?? null,
                        'end_time'          => $data['end_time'] ?? null,
                        'bid_strategy'      => $data['bid_strategy'] ?? 'LOWEST_COST_WITHOUT_CAP',
                    ]
                );

                if (empty($metaResponse['id'])) {
                    throw new \Exception(
                        $metaResponse['error']['message'] ?? 'Meta API failed to create AdSet.'
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Store AdSet Locally
            |--------------------------------------------------------------------------
            */

            $adset = AdSet::create([
                'campaign_id'       => $campaign->id,
                'meta_id'           => $metaResponse['id'] ?? null,
                'name'              => $data['name'],
                'daily_budget'      => (int)($data['daily_budget'] * 100),
                'optimization_goal' => $data['optimization_goal'] ?? 'REACH',
                'billing_event'     => $data['billing_event'] ?? 'IMPRESSIONS',
                'bid_strategy'      => $data['bid_strategy'] ?? 'LOWEST_COST_WITHOUT_CAP',
                'targeting'         => json_encode($targeting),
                'status'            => $data['status'] ?? 'PAUSED',
                'start_time'        => $data['start_time'] ?? null,
                'end_time'          => $data['end_time'] ?? null,
            ]);

            DB::commit();

            Log::info('AdSet Created Successfully', [
                'adset_id' => $adset->id,
                'meta_id'  => $metaResponse['id'] ?? null
            ]);

            $message = $campaign->meta_id 
                ? 'Ad Set created and synced with Meta successfully.'
                : 'Ad Set created locally. Sync with Meta to activate.';

            return redirect()
                ->route('admin.campaigns.show', $campaign->id)
                ->with('success', $message);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('AdSet Creation Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()
                ->withErrors(['error' => 'Unable to create Ad Set: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Edit AdSet Form
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        $adset = AdSet::with('campaign')->findOrFail($id);
        
        $campaigns = Campaign::all();
        $countries = $this->getTargetingCountries();
        $languages = $this->getTargetingLanguages();
        $targeting = json_decode($adset->targeting, true) ?? [];

        return view('admin.adsets.edit', compact('adset', 'campaigns', 'countries', 'languages', 'targeting'));
    }

    /*
    |--------------------------------------------------------------------------
    | Update AdSet
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $adset = AdSet::findOrFail($id);

        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'daily_budget'      => 'required|numeric|min:1',
            'status'            => 'nullable|in:ACTIVE,PAUSED',
            'age_min'           => 'nullable|integer|min:13|max:65',
            'age_max'           => 'nullable|integer|min:13|max:65|gt:age_min',
            'genders'           => 'nullable|array',
            'countries'         => 'required|array|min:1',
            'languages'         => 'nullable|array',
            'start_time'        => 'nullable|date',
            'end_time'          => 'nullable|date|after:start_time',
        ]);

        try {
            DB::beginTransaction();

            // Update targeting
            $targeting = json_decode($adset->targeting, true) ?? [];
            $targeting['age_min'] = $data['age_min'] ?? 18;
            $targeting['age_max'] = $data['age_max'] ?? 65;
            $targeting['geo_locations']['countries'] = $data['countries'];
            
            if (!empty($data['genders'])) {
                $targeting['genders'] = $data['genders'];
            }
            
            if (!empty($data['languages'])) {
                $targeting['locales'] = $data['languages'];
            }

            // Update on Meta if synced
            if ($adset->meta_id) {
                $metaUpdate = [
                    'name' => $data['name'],
                    'daily_budget' => (int)($data['daily_budget'] * 100),
                    'targeting' => $targeting,
                    'status' => $data['status'] ?? $adset->status,
                ];

                if ($data['start_time'] ?? null) {
                    $metaUpdate['start_time'] = $data['start_time'];
                }
                if ($data['end_time'] ?? null) {
                    $metaUpdate['end_time'] = $data['end_time'];
                }

                $response = $this->meta->updateAdSet($adset->meta_id, $metaUpdate);

                if (!($response['success'] ?? false)) {
                    throw new \Exception('Failed to update AdSet on Meta');
                }
            }

            // Update locally
            $adset->update([
                'name' => $data['name'],
                'daily_budget' => (int)($data['daily_budget'] * 100),
                'targeting' => json_encode($targeting),
                'status' => $data['status'] ?? $adset->status,
                'start_time' => $data['start_time'] ?? $adset->start_time,
                'end_time' => $data['end_time'] ?? $adset->end_time,
            ]);

            DB::commit();

            return redirect()
                ->route('admin.adsets.show', $adset->id)
                ->with('success', 'Ad Set updated successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('AdSet Update Failed', [
                'adset_id' => $id,
                'error' => $e->getMessage()
            ]);

            return back()
                ->withErrors(['error' => 'Unable to update Ad Set: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Delete AdSet
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $adset = AdSet::findOrFail($id);

        try {
            DB::beginTransaction();

            // Delete from Meta if synced
            if ($adset->meta_id) {
                $response = $this->meta->deleteAdSet($adset->meta_id);
                
                if (!($response['success'] ?? false)) {
                    throw new \Exception('Failed to delete AdSet from Meta');
                }
            }

            $campaignId = $adset->campaign_id;
            $adset->delete();

            DB::commit();

            return redirect()
                ->route('admin.campaigns.show', $campaignId)
                ->with('success', 'Ad Set deleted successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('AdSet Delete Failed', [
                'adset_id' => $id,
                'error' => $e->getMessage()
            ]);

            return back()->withErrors(['error' => 'Unable to delete Ad Set: ' . $e->getMessage()]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Activate AdSet
    |--------------------------------------------------------------------------
    */

    public function activate($id)
    {
        $adset = AdSet::findOrFail($id);

        try {
            if ($adset->meta_id) {
                $this->meta->updateAdSet($adset->meta_id, ['status' => 'ACTIVE']);
            }

            $adset->update(['status' => 'ACTIVE']);

            return response()->json(['success' => true, 'message' => 'Ad Set activated']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Pause AdSet
    |--------------------------------------------------------------------------
    */

    public function pause($id)
    {
        $adset = AdSet::findOrFail($id);

        try {
            if ($adset->meta_id) {
                $this->meta->updateAdSet($adset->meta_id, ['status' => 'PAUSED']);
            }

            $adset->update(['status' => 'PAUSED']);

            return response()->json(['success' => true, 'message' => 'Ad Set paused']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate AdSet
    |--------------------------------------------------------------------------
    */

    public function duplicate(Request $request, $id)
    {
        $adset = AdSet::with('campaign')->findOrFail($id);

        try {
            DB::beginTransaction();

            $newName = $adset->name . ' (Copy)';

            // Create on Meta if original was synced
            $metaId = null;
            if ($adset->meta_id && $adset->campaign->meta_id && $adset->campaign->adAccount) {
                $response = $this->meta->createAdSet(
                    $adset->campaign->adAccount->meta_id,
                    [
                        'name' => $newName,
                        'campaign_id' => $adset->campaign->meta_id,
                        'daily_budget' => $adset->daily_budget,
                        'billing_event' => $adset->billing_event,
                        'optimization_goal' => $adset->optimization_goal,
                        'targeting' => json_decode($adset->targeting, true),
                        'status' => 'PAUSED',
                    ]
                );

                if (!empty($response['id'])) {
                    $metaId = $response['id'];
                }
            }

            // Create locally
            $newAdSet = AdSet::create([
                'campaign_id' => $adset->campaign_id,
                'meta_id' => $metaId,
                'name' => $newName,
                'daily_budget' => $adset->daily_budget,
                'optimization_goal' => $adset->optimization_goal,
                'billing_event' => $adset->billing_event,
                'bid_strategy' => $adset->bid_strategy,
                'targeting' => $adset->targeting,
                'status' => 'PAUSED',
            ]);

            DB::commit();

            return redirect()
                ->route('admin.adsets.show', $newAdSet->id)
                ->with('success', 'Ad Set duplicated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('AdSet Duplicate Failed', [
                'adset_id' => $id,
                'error' => $e->getMessage()
            ]);

            return back()->withErrors(['error' => 'Unable to duplicate Ad Set: ' . $e->getMessage()]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Bulk Status Update
    |--------------------------------------------------------------------------
    */

    public function bulkStatusUpdate(Request $request)
    {
        $request->validate([
            'adset_ids' => 'required|array',
            'adset_ids.*' => 'exists:adsets,id',
            'status' => 'required|in:ACTIVE,PAUSED',
        ]);

        $count = 0;

        foreach ($request->adset_ids as $id) {
            $adset = AdSet::with('campaign')->find($id);
            
            if ($adset) {
                try {
                    if ($adset->meta_id) {
                        $this->meta->updateAdSet($adset->meta_id, ['status' => $request->status]);
                    }
                    $adset->update(['status' => $request->status]);
                    $count++;
                } catch (\Exception $e) {
                    Log::warning('Bulk status update failed for adset', [
                        'adset_id' => $id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} ad sets updated successfully."
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Get AdSets by Campaign (AJAX)
    |--------------------------------------------------------------------------
    */

    public function byCampaign($campaignId)
    {
        $adsets = AdSet::where('campaign_id', $campaignId)
            ->latest()
            ->get(['id', 'name', 'status', 'daily_budget', 'meta_id']);

        return response()->json([
            'success' => true,
            'data' => $adsets
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Sync AdSet with Meta
    |--------------------------------------------------------------------------
    */

    public function syncToMeta($id)
    {
        $adset = AdSet::with('campaign.adAccount')->findOrFail($id);

        try {
            if (!$adset->campaign->meta_id) {
                throw new \Exception('Campaign must be synced with Meta first.');
            }

            if (!$adset->campaign->adAccount) {
                throw new \Exception('Campaign has no linked Ad Account.');
            }

            $response = $this->meta->createAdSet(
                $adset->campaign->adAccount->meta_id,
                [
                    'name' => $adset->name,
                    'campaign_id' => $adset->campaign->meta_id,
                    'daily_budget' => $adset->daily_budget,
                    'billing_event' => $adset->billing_event,
                    'optimization_goal' => $adset->optimization_goal,
                    'targeting' => json_decode($adset->targeting, true),
                    'status' => $adset->status,
                ]
            );

            if (empty($response['id'])) {
                throw new \Exception('Failed to create AdSet on Meta');
            }

            $adset->update(['meta_id' => $response['id']]);

            return redirect()
                ->route('admin.adsets.show', $id)
                ->with('success', 'Ad Set synced with Meta successfully.');

        } catch (\Exception $e) {
            Log::error('AdSet Sync Failed', [
                'adset_id' => $id,
                'error' => $e->getMessage()
            ]);

            return back()->withErrors(['error' => 'Unable to sync Ad Set: ' . $e->getMessage()]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Get AdSet Insights
    |--------------------------------------------------------------------------
    */

    public function insights($id)
    {
        $adset = AdSet::findOrFail($id);

        if (!$adset->meta_id) {
            return response()->json([
                'success' => false,
                'message' => 'Ad Set not synced with Meta'
            ], 400);
        }

        try {
            $insights = $this->meta->getAdSetInsights($adset->meta_id);

            return response()->json([
                'success' => true,
                'data' => $insights
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helper: Get Targeting Countries
    |--------------------------------------------------------------------------
    */

    protected function getTargetingCountries()
    {
        return [
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'IE' => 'Ireland',
            'DE' => 'Germany',
            'FR' => 'France',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'NL' => 'Netherlands',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'CH' => 'Switzerland',
            'AT' => 'Austria',
            'BE' => 'Belgium',
            'PT' => 'Portugal',
            'GR' => 'Greece',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'SG' => 'Singapore',
            'IN' => 'India',
            'CN' => 'China',
            'ZA' => 'South Africa',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia',
            'IL' => 'Israel',
            'TR' => 'Turkey',
            'RU' => 'Russia',
            'PL' => 'Poland',
            'CZ' => 'Czech Republic',
            'HU' => 'Hungary',
            'RO' => 'Romania',
            'BG' => 'Bulgaria',
            'UA' => 'Ukraine',
            'EG' => 'Egypt',
            'KE' => 'Kenya',
            'NG' => 'Nigeria',
            'GH' => 'Ghana',
            'TZ' => 'Tanzania',
            'UG' => 'Uganda',
            'RW' => 'Rwanda',
            'ZM' => 'Zambia',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helper: Get Targeting Languages
    |--------------------------------------------------------------------------
    */

    protected function getTargetingLanguages()
    {
        return [
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'sv' => 'Swedish',
            'no' => 'Norwegian',
            'da' => 'Danish',
            'fi' => 'Finnish',
            'pl' => 'Polish',
            'cs' => 'Czech',
            'hu' => 'Hungarian',
            'ro' => 'Romanian',
            'bg' => 'Bulgarian',
            'ru' => 'Russian',
            'uk' => 'Ukrainian',
            'el' => 'Greek',
            'tr' => 'Turkish',
            'ar' => 'Arabic',
            'he' => 'Hebrew',
            'hi' => 'Hindi',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'th' => 'Thai',
            'vi' => 'Vietnamese',
            'id' => 'Indonesian',
            'ms' => 'Malay',
            'sw' => 'Swahili',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helper: Get Targeting Interests
    |--------------------------------------------------------------------------
    */

    protected function getTargetingInterests()
    {
        return [
            '6003139266461' => 'Study abroad',
            '6003159268461' => 'International students',
            '6003139294461' => 'Scholarships',
            '6003139298461' => 'University',
            '6003139299461' => 'College',
            '6003159269461' => 'Visa application',
            '6003139280461' => 'Education abroad',
            '6003139281461' => 'Student visa',
            '6003159270461' => 'Work abroad',
            '6003159271461' => 'Immigration',
            '6003139290461' => 'MBA',
            '6003139291461' => 'Master\'s degree',
            '6003139292461' => 'Bachelor\'s degree',
            '6003139293461' => 'PhD',
            '6003139295461' => 'Language school',
            '6003139296461' => 'Test preparation',
            '6003139297461' => 'TOEFL',
            '6003139298461' => 'IELTS',
            '6003139299461' => 'GRE',
            '6003139300461' => 'GMAT',
        ];
    }
}