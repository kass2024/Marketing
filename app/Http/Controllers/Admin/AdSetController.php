<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Campaign;
use App\Models\AdSet;
use App\Models\AdAccount;
use App\Services\MetaAdsService;
use Carbon\Carbon;

class AdSetController extends Controller
{
    protected MetaAdsService $meta;
    
    // Meta API constants
    const META_API_VERSION = 'v18.0';
    const BILLING_EVENTS = ['IMPRESSIONS', 'LINK_CLICKS', 'PAGE_LIKES', 'POST_ENGAGEMENT', 'LEAD', 'THRUPLAY'];
    const OPTIMIZATION_GOALS = ['REACH', 'IMPRESSIONS', 'LANDING_PAGE_VIEWS', 'LINK_CLICKS', 'LEAD', 'CONVERSIONS', 'VALUE'];
    const BID_STRATEGIES = ['LOWEST_COST_WITHOUT_CAP', 'LOWEST_COST_WITH_BID_CAP', 'COST_CAP'];
    const PUBLISHER_PLATFORMS = ['facebook', 'instagram', 'messenger', 'audience_network'];
    const DEVICE_PLATFORMS = ['mobile', 'desktop'];
    const GENDERS = [1 => 'male', 2 => 'female', 3 => 'unknown'];
    
    // Location types mapping
    const LOCATION_TYPES = [
        'living_or_recent' => ['home', 'recent'],
        'living' => ['home'],
        'recent' => ['recent'],
        'traveling' => ['travel_in']
    ];

    public function __construct(MetaAdsService $meta)
    {
        $this->meta = $meta;
    }

    /**
     * List Ad Sets
     */
    public function index(Request $request)
    {
        $query = AdSet::with('campaign');

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->campaign_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $adsets = $query->latest()->paginate(20);
        
        $campaigns = Campaign::orderBy('name')->get(['id', 'name']);
        
        $stats = [
            'total' => AdSet::count(),
            'active' => AdSet::where('status', 'ACTIVE')->count(),
            'paused' => AdSet::where('status', 'PAUSED')->count(),
            'draft' => AdSet::where('status', 'DRAFT')->count(),
        ];

        return view('admin.adsets.index', compact('adsets', 'campaigns', 'stats'));
    }

    /**
     * Show single Ad Set
     */
    public function show($id)
    {
        $adset = AdSet::with(['campaign', 'ads'])->findOrFail($id);

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

    /**
     * Create Ad Set form
     */
    public function create(Request $request)
    {
        $campaigns = Campaign::whereIn('status', ['ACTIVE', 'PAUSED'])
            ->orderBy('name')
            ->get(['id', 'name', 'objective', 'meta_id']);

        $selectedCampaignId = $request->campaign_id;
        
        $countries = $this->getTargetingCountries();
        $languages = $this->getTargetingLanguages();
        
        $adAccount = AdAccount::first();
        $accountCurrency = $adAccount->currency ?? 'USD';

        return view('admin.adsets.create', compact(
            'campaigns',
            'selectedCampaignId',
            'countries',
            'languages',
            'accountCurrency',
            'adAccount'
        ));
    }

    /**
     * Create Ad Set from Campaign
     */
    public function createFromCampaign(Request $request, $campaignId)
    {
        try {
            $campaign = Campaign::with('adAccount')->findOrFail($campaignId);

            if (!$campaign->adAccount) {
                $adAccount = AdAccount::first();
                
                if (!$adAccount) {
                    return redirect()->route('admin.accounts.index')
                        ->with('error', 'Please connect a Meta Ad Account first.');
                }
                
                $campaign->ad_account_id = $adAccount->id;
                $campaign->save();
                $campaign->load('adAccount');
            }

            if (!$campaign->meta_id) {
                return redirect()->route('admin.campaigns.edit', $campaignId)
                    ->with('warning', 'Please save the campaign to sync with Meta first.');
            }

            $campaigns = Campaign::whereIn('status', ['ACTIVE', 'PAUSED'])
                ->orderBy('name')
                ->get(['id', 'name', 'objective', 'meta_id']);

            $countries = $this->getTargetingCountries();
            $languages = $this->getTargetingLanguages();
            
            $accountCurrency = $campaign->adAccount->currency ?? 'USD';

            return view('admin.adsets.create', compact(
                'campaigns',
                'campaign',
                'countries',
                'languages',
                'accountCurrency'
            ));

        } catch (\Exception $e) {
            Log::error('Error in createFromCampaign', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->route('admin.campaigns.index')
                ->with('error', 'Unable to load campaign: ' . $e->getMessage());
        }
    }

    /**
     * Store Ad Set
     */
    public function store(Request $request)
    {
        // Comprehensive validation rules
        $validator = Validator::make($request->all(), [
            // Basic fields
            'campaign_id' => 'required|exists:campaigns,id',
            'name' => 'required|string|max:255',
            'daily_budget' => 'required|numeric|min:1|max:1000000',
            
            // Meta API fields
            'optimization_goal' => 'nullable|string|in:' . implode(',', self::OPTIMIZATION_GOALS),
            'billing_event' => 'nullable|string|in:' . implode(',', self::BILLING_EVENTS),
            'bid_strategy' => 'nullable|string|in:' . implode(',', self::BID_STRATEGIES),
            'bid_amount' => 'nullable|numeric|min:0.01|required_if:bid_strategy,LOWEST_COST_WITH_BID_CAP,COST_CAP',
            
            // Age targeting
            'age_min' => 'nullable|integer|min:13|max:65',
            'age_max' => 'nullable|integer|min:13|max:65|gte:age_min',
            
            // Gender targeting
            'genders' => 'nullable|array',
            'genders.*' => 'integer|in:1,2',
            
            // Location targeting - COMPREHENSIVE COUNTRY LIST
            'countries' => 'required_without:excluded_countries|array|min:1',
            'countries.*' => 'string|size:2|in:' . implode(',', array_keys($this->getTargetingCountries())),
            'excluded_countries' => 'nullable|array',
            'excluded_countries.*' => 'string|size:2|in:' . implode(',', array_keys($this->getTargetingCountries())),
            
            // Location types
            'location_types' => 'nullable|array',
            'location_types.*' => 'string|in:home,recent,travel_in',
            
            // Location options
            'location_type' => 'nullable|string|in:living_or_recent,living,recent,traveling',
            'exclude_locations' => 'nullable|boolean',
            
            // City/Region targeting (optional)
            'cities' => 'nullable|array',
            'cities.*.key' => 'required_with:cities|string',
            'cities.*.name' => 'required_with:cities|string',
            'cities.*.country' => 'required_with:cities|string|size:2',
            
            'regions' => 'nullable|array',
            'regions.*.key' => 'required_with:regions|string',
            'regions.*.name' => 'required_with:regions|string',
            'regions.*.country' => 'required_with:regions|string|size:2',
            
            'zips' => 'nullable|array',
            'zips.*.key' => 'required_with:zips|string',
            'zips.*.name' => 'required_with:zips|string',
            'zips.*.country' => 'required_with:zips|string|size:2',
            
            // Languages (Meta locale IDs)
            'languages' => 'nullable|array',
            'languages.*' => 'integer|in:' . implode(',', array_keys($this->getTargetingLanguages())),
            
            // Interests/detailed targeting
            'interests' => 'nullable|array|max:10',
            'interests.*' => 'string',
            
            'behaviors' => 'nullable|array',
            'behaviors.*' => 'string',
            
            'life_events' => 'nullable|array',
            'life_events.*' => 'string',
            
            'industries' => 'nullable|array',
            'industries.*' => 'string',
            
            'income' => 'nullable|array',
            'income.*' => 'string',
            
            'family_statuses' => 'nullable|array',
            'family_statuses.*' => 'string',
            
            'user_device' => 'nullable|array',
            'user_device.*' => 'string',
            
            'user_os' => 'nullable|array',
            'user_os.*' => 'string',
            
            'wireless_carrier' => 'nullable|array',
            'wireless_carrier.*' => 'string',
            
            // Connections
            'connections_type' => 'nullable|string|in:and,or,not',
            'connections' => 'nullable|array',
            'connections.*' => 'string',
            'connections_fields' => 'nullable|array',
            
            // Device and platform targeting
            'device_platforms' => 'nullable|array',
            'device_platforms.*' => 'string|in:' . implode(',', self::DEVICE_PLATFORMS),
            
            'publisher_platforms' => 'nullable|array',
            'publisher_platforms.*' => 'string|in:' . implode(',', self::PUBLISHER_PLATFORMS),
            
            'facebook_positions' => 'nullable|array',
            'facebook_positions.*' => 'string|in:feed,story,marketplace,video_feeds,right_column,instant_article,in_stream',
            
            'instagram_positions' => 'nullable|array',
            'instagram_positions.*' => 'string|in:stream,story,explore,reels,shop',
            
            'messenger_positions' => 'nullable|array',
            'messenger_positions.*' => 'string|in:messenger_home,sponsored_messages,story,inbox',
            
            'audience_network_positions' => 'nullable|array',
            'audience_network_positions.*' => 'string|in:native,banner,interstitial,rewarded_video',
            
            // Placement type
            'placement_type' => 'nullable|in:automatic,manual',
            
            // Schedule
            'schedule_type' => 'nullable|in:now,start_end',
            'start_time' => 'nullable|required_if:schedule_type,start_end|date|after_or_equal:now',
            'end_time' => 'nullable|date|after:start_time',
            
            // Status
            'status' => 'nullable|in:ACTIVE,PAUSED',
            
            // Flexible spec (advanced)
            'flexible_spec' => 'nullable|json',
            'flexible_spec_json' => 'nullable|json',
            
            // Promoted object
            'promoted_object_type' => 'nullable|string|in:page,event,instagram,application',
            'promoted_object_id' => 'nullable|string|required_with:promoted_object_type',
            'promoted_object_page_id' => 'nullable|string',
            'promoted_object_instagram_id' => 'nullable|string',
            
            // Custom audiences
            'custom_audiences' => 'nullable|array',
            'custom_audiences.*' => 'string',
            'excluded_custom_audiences' => 'nullable|array',
            'excluded_custom_audiences.*' => 'string',
            
        ], [
            'countries.required_without' => 'Please select at least one country to target.',
            'countries.*.in' => 'Invalid country code selected.',
            'interests.max' => 'You can select up to 10 interests only.',
            'bid_amount.required_if' => 'Bid amount is required when using bid cap or cost cap strategy.',
            'start_time.required_if' => 'Start date is required when setting specific schedule.',
            'end_time.after' => 'End date must be after start date.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        try {
            DB::beginTransaction();

            $campaign = Campaign::with('adAccount')->findOrFail($data['campaign_id']);

            // Ensure ad account exists
            if (!$campaign->adAccount) {
                $adAccount = AdAccount::first();
                if (!$adAccount) {
                    throw new \Exception('No Meta Ad Account found. Please connect an ad account first.');
                }
                $campaign->ad_account_id = $adAccount->id;
                $campaign->save();
                $campaign->load('adAccount');
            }

            /**
             * Build comprehensive targeting specification for Meta API
             */
            $targeting = $this->buildTargetingSpec($data);

            // Validate targeting before sending to Meta
            $this->validateTargetingForMeta($targeting);

            Log::info('Creating AdSet with targeting', [
                'targeting' => $targeting,
                'campaign_id' => $campaign->id,
                'campaign_meta_id' => $campaign->meta_id,
            ]);

            /**
             * Create on Meta if campaign is synced
             */
            $metaResponse = null;
            if ($campaign->meta_id) {
                $adSetParams = [
                    'name' => $data['name'],
                    'campaign_id' => $campaign->meta_id,
                    'daily_budget' => (int)($data['daily_budget'] * 100), // Convert to cents
                    'billing_event' => $data['billing_event'] ?? 'IMPRESSIONS',
                    'optimization_goal' => $data['optimization_goal'] ?? 'REACH',
                    'targeting' => $targeting,
                    'status' => $data['status'] ?? 'PAUSED',
                ];

                // Add bid strategy and amount if specified
                if (!empty($data['bid_strategy'])) {
                    $adSetParams['bid_strategy'] = $data['bid_strategy'];
                    
                    if (!empty($data['bid_amount']) && in_array($data['bid_strategy'], ['LOWEST_COST_WITH_BID_CAP', 'COST_CAP'])) {
                        $adSetParams['bid_amount'] = (int)($data['bid_amount'] * 100); // Convert to cents
                    }
                }

                // Handle schedule
                if (!empty($data['schedule_type']) && $data['schedule_type'] === 'start_end') {
                    if (!empty($data['start_time'])) {
                        $adSetParams['start_time'] = Carbon::parse($data['start_time'])->toIso8601String();
                    }
                    if (!empty($data['end_time'])) {
                        $adSetParams['end_time'] = Carbon::parse($data['end_time'])->toIso8601String();
                    }
                }

                // Add promoted object if specified
                if (!empty($data['promoted_object_type']) && !empty($data['promoted_object_id'])) {
                    $adSetParams['promoted_object'] = $this->buildPromotedObject($data);
                }
Log::debug('META ADSET REQUEST', [
    'campaign_id' => $campaign->id,
    'campaign_meta_id' => $campaign->meta_id,
    'ad_account_id' => $campaign->adAccount->meta_id,
    'params' => $adSetParams,
    'targeting' => $targeting,
]);

                $metaResponse = $this->meta->createAdSet(
                    $campaign->adAccount->meta_id,
                    $adSetParams
                );
                 Log::debug('META ADSET RESPONSE', [
    'response' => $metaResponse
]);
       if (isset($metaResponse['error'])) {

    Log::error('META ADSET ERROR', [
        'error' => $metaResponse['error'],
        'request_payload' => $adSetParams,
        'targeting' => $targeting
    ]);

    throw new \Exception($metaResponse['error']['message'] ?? 'Meta API error');
}

                Log::info('Meta AdSet created successfully', [
                    'meta_id' => $metaResponse['id'],
                    'response' => $metaResponse
                ]);
            }

            /**
             * Store locally with full targeting data
             */
            $adset = AdSet::create([
                'campaign_id' => $campaign->id,
                'meta_id' => $metaResponse['id'] ?? null,
                'name' => $data['name'],
                'daily_budget' => (int)($data['daily_budget'] * 100),
                'optimization_goal' => $data['optimization_goal'] ?? 'REACH',
                'billing_event' => $data['billing_event'] ?? 'IMPRESSIONS',
                'bid_strategy' => $data['bid_strategy'] ?? 'LOWEST_COST_WITHOUT_CAP',
                'bid_amount' => isset($data['bid_amount']) ? (int)($data['bid_amount'] * 100) : null,
                'targeting' => json_encode($targeting),
                'status' => $data['status'] ?? 'PAUSED',
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'promoted_object' => isset($data['promoted_object_type']) ? json_encode($this->buildPromotedObject($data)) : null,
            ]);

            DB::commit();

            $message = $campaign->meta_id 
                ? 'Ad Set created and synced with Meta successfully.'
                : 'Ad Set created locally. Sync with Meta to activate.';

            return redirect()
                ->route('admin.campaigns.show', $campaign->id)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('ADSET CREATION FAILED', [
    'error' => $e->getMessage(),
    'campaign_id' => $data['campaign_id'] ?? null,
    'request_data' => $data,
]);

            // Check for specific Meta API errors
            if (strpos($e->getMessage(), '1815857') !== false) {
                return back()
                    ->withErrors(['error' => 'Targeting error: ' . $this->getTargetingErrorMessage($e->getMessage())])
                    ->withInput();
            }

            return back()
                ->withErrors(['error' => 'Unable to create Ad Set: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Build comprehensive targeting specification
     */
    private function buildTargetingSpec(array $data): array
    {
        $targeting = [];

        // Age range
        $targeting['age_min'] = (int)($data['age_min'] ?? 18);
        $targeting['age_max'] = (int)($data['age_max'] ?? 65);

        // Genders
        if (!empty($data['genders'])) {
            $targeting['genders'] = array_map('intval', array_filter($data['genders'], function($g) {
                return in_array($g, [1, 2]);
            }));
        }

        /**
         * GEO LOCATIONS - COMPREHENSIVE SUPPORT
         */
        $targeting['geo_locations'] = [];

        // Countries targeting
        if (!empty($data['countries']) && empty($data['exclude_locations'])) {
            $targeting['geo_locations']['countries'] = $data['countries'];
        }

        // Location types
        if (!empty($data['location_types'])) {
            $targeting['geo_locations']['location_types'] = $data['location_types'];
        } elseif (!empty($data['location_type']) && isset(self::LOCATION_TYPES[$data['location_type']])) {
            $targeting['geo_locations']['location_types'] = self::LOCATION_TYPES[$data['location_type']];
        } else {
            $targeting['geo_locations']['location_types'] = ['home', 'recent'];
        }

        // Excluded locations
        if (!empty($data['exclude_locations']) && !empty($data['countries'])) {
            $targeting['excluded_geo_locations'] = [
                'countries' => $data['countries']
            ];
            unset($targeting['geo_locations']['countries']);
        }

        // Cities targeting
        if (!empty($data['cities'])) {
            $targeting['geo_locations']['cities'] = array_map(function($city) {
                return [
                    'key' => $city['key'],
                    'name' => $city['name'],
                    'country_code' => $city['country']
                ];
            }, $data['cities']);
        }

        // Regions targeting
        if (!empty($data['regions'])) {
            $targeting['geo_locations']['regions'] = array_map(function($region) {
                return [
                    'key' => $region['key'],
                    'name' => $region['name'],
                    'country_code' => $region['country']
                ];
            }, $data['regions']);
        }

        // ZIP codes targeting
        if (!empty($data['zips'])) {
            $targeting['geo_locations']['zips'] = array_map(function($zip) {
                return [
                    'key' => $zip['key'],
                    'name' => $zip['name'],
                    'country_code' => $zip['country'],
                    'primary_city_id' => $zip['primary_city_id'] ?? null,
                    'region_id' => $zip['region_id'] ?? null,
                ];
            }, $data['zips']);
        }

        // Excluded countries (alternative method)
        if (!empty($data['excluded_countries'])) {
            if (!isset($targeting['excluded_geo_locations'])) {
                $targeting['excluded_geo_locations'] = [];
            }
            $targeting['excluded_geo_locations']['countries'] = $data['excluded_countries'];
        }

        /**
         * LANGUAGES
         */
        if (!empty($data['languages'])) {
            $targeting['locales'] = array_map('intval', $data['languages']);
        }

        /**
         * DETAILED TARGETING (Interests, Behaviors, etc.)
         */
        $flexibleSpec = [];

        // Interests
      // Interests
if (!empty($data['interests'])) {

    $interests = array_values(array_filter($data['interests']));

    $flexibleSpec[] = [
        'interests' => array_map(function ($id) {
            return [
                'id' => (string) $id
            ];
        }, $interests)
    ];
}

        // Behaviors
        if (!empty($data['behaviors'])) {
            $flexibleSpec[] = [
                'behaviors' => array_map(function($behavior) {
                    return ['id' => $behavior, 'name' => ''];
                }, $data['behaviors'])
            ];
        }

        // Life events
        if (!empty($data['life_events'])) {
            $flexibleSpec[] = [
                'life_events' => array_map(function($event) {
                    return ['id' => $event, 'name' => ''];
                }, $data['life_events'])
            ];
        }

        // Industries
        if (!empty($data['industries'])) {
            $flexibleSpec[] = [
                'industries' => array_map(function($industry) {
                    return ['id' => $industry, 'name' => ''];
                }, $data['industries'])
            ];
        }

        // Income
        if (!empty($data['income'])) {
            $flexibleSpec[] = [
                'income' => array_map(function($inc) {
                    return ['id' => $inc, 'name' => ''];
                }, $data['income'])
            ];
        }

        // Family statuses
        if (!empty($data['family_statuses'])) {
            $flexibleSpec[] = [
                'family_statuses' => array_map(function($status) {
                    return ['id' => $status, 'name' => ''];
                }, $data['family_statuses'])
            ];
        }

        // User device
        if (!empty($data['user_device'])) {
            $flexibleSpec[] = [
                'user_device' => array_map(function($device) {
                    return ['id' => $device, 'name' => ''];
                }, $data['user_device'])
            ];
        }

        // User OS
        if (!empty($data['user_os'])) {
            $flexibleSpec[] = [
                'user_os' => array_map(function($os) {
                    return ['id' => $os, 'name' => ''];
                }, $data['user_os'])
            ];
        }

        // Wireless carrier
        if (!empty($data['wireless_carrier'])) {
            $flexibleSpec[] = [
                'wireless_carrier' => array_map(function($carrier) {
                    return ['id' => $carrier, 'name' => ''];
                }, $data['wireless_carrier'])
            ];
        }

        // Custom audiences
        if (!empty($data['custom_audiences'])) {
            $flexibleSpec[] = [
                'custom_audiences' => array_map(function($audience) {
                    return ['id' => $audience, 'name' => ''];
                }, $data['custom_audiences'])
            ];
        }

        // Excluded custom audiences
        if (!empty($data['excluded_custom_audiences'])) {
            $targeting['excluded_custom_audiences'] = array_map(function($audience) {
                return ['id' => $audience, 'name' => ''];
            }, $data['excluded_custom_audiences']);
        }

        // Set flexible_spec if we have any targeting
        if (!empty($flexibleSpec)) {
            $targeting['flexible_spec'] = $flexibleSpec;
        }


        /**
         * CONNECTIONS
         */
        if (!empty($data['connections'])) {
            $connections = [];
            
            foreach ($data['connections'] as $connection) {

    if (is_numeric($connection)) {
        $connections[] = [
            'id' => (string) $connection
        ];
    }

}
            
            if (!empty($connections)) {
                $targeting['connections'] = $connections;
                
                if (!empty($data['connections_type'])) {
                    $targeting['connections_type'] = $data['connections_type'];
                }
                
                if (!empty($data['connections_fields'])) {
                    $targeting['connections_fields'] = $data['connections_fields'];
                }
            }
        }

        /**
         * DEVICE AND PLATFORM TARGETING
         */
        
        // Device platforms
        if (!empty($data['device_platforms'])) {
            $targeting['device_platforms'] = $data['device_platforms'];
        } else {
            $targeting['device_platforms'] = ['mobile', 'desktop'];
        }

        // Publisher platforms and placements
        if (!empty($data['placement_type']) && $data['placement_type'] === 'manual') {
            $publisherPlatforms = $data['publisher_platforms'] ?? ['facebook', 'instagram'];
            $targeting['publisher_platforms'] = $publisherPlatforms;

            // Facebook placements
            if (in_array('facebook', $publisherPlatforms) && !empty($data['facebook_positions'])) {
                $targeting['facebook_positions'] = $data['facebook_positions'];
            }

            // Instagram placements
            if (in_array('instagram', $publisherPlatforms) && !empty($data['instagram_positions'])) {
                $targeting['instagram_positions'] = $data['instagram_positions'];
            }

            // Messenger placements
            if (in_array('messenger', $publisherPlatforms) && !empty($data['messenger_positions'])) {
                $targeting['messenger_positions'] = $data['messenger_positions'];
            }

            // Audience Network placements
            if (in_array('audience_network', $publisherPlatforms) && !empty($data['audience_network_positions'])) {
                $targeting['audience_network_positions'] = $data['audience_network_positions'];
            }
        } else {
            // Automatic placements
            $targeting['publisher_platforms'] = ['facebook', 'instagram'];
        }

        /**
         * Remove empty arrays to clean up the targeting spec
         */
        return $this->cleanTargetingArray($targeting);
    }

    /**
     * Build promoted object
     */
    private function buildPromotedObject(array $data): array
    {
        $promotedObject = [];
        
        switch ($data['promoted_object_type']) {
            case 'page':
                $promotedObject['page_id'] = $data['promoted_object_id'];
                break;
            case 'event':
                $promotedObject['event_id'] = $data['promoted_object_id'];
                break;
            case 'instagram':
                $promotedObject['instagram_account_id'] = $data['promoted_object_id'];
                if (!empty($data['promoted_object_page_id'])) {
                    $promotedObject['page_id'] = $data['promoted_object_page_id'];
                }
                break;
            case 'application':
                $promotedObject['application_id'] = $data['promoted_object_id'];
                if (!empty($data['promoted_object_object_store_url'])) {
                    $promotedObject['object_store_url'] = $data['promoted_object_object_store_url'];
                }
                break;
        }
        
        return $promotedObject;
    }

    /**
     * Clean targeting array by removing empty values
     */
    private function cleanTargetingArray(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->cleanTargetingArray($value);
                if (empty($array[$key])) {
                    unset($array[$key]);
                }
            } elseif ($value === null || $value === '') {
                unset($array[$key]);
            }
        }
        return $array;
    }

    /**
     * Validate targeting for Meta API
     */
    private function validateTargetingForMeta(array $targeting): void
    {
        // Check for geo_locations
        if (empty($targeting['geo_locations'])) {
            throw new \Exception('geo_locations targeting is required');
        }

        // Check for at least one targeting method
        $hasGeo = !empty($targeting['geo_locations']['countries']) || 
                  !empty($targeting['geo_locations']['cities']) || 
                  !empty($targeting['geo_locations']['regions']) || 
                  !empty($targeting['geo_locations']['zips']);
        
        if (!$hasGeo && empty($targeting['geo_locations']['location_types'])) {
            throw new \Exception('At least one geographic location must be specified');
        }

        // Validate location types
        if (isset($targeting['geo_locations']['location_types'])) {
            foreach ($targeting['geo_locations']['location_types'] as $type) {
                if (!in_array($type, ['home', 'recent', 'travel_in'])) {
                    throw new \Exception("Invalid location type: {$type}");
                }
            }
        }

        // Age range validation
        if (isset($targeting['age_min']) && $targeting['age_min'] < 13) {
            throw new \Exception('Minimum age must be at least 13');
        }

        if (isset($targeting['age_max']) && $targeting['age_max'] > 65) {
            throw new \Exception('Maximum age cannot exceed 65');
        }

        // Publisher platforms validation
        if (isset($targeting['publisher_platforms'])) {
            foreach ($targeting['publisher_platforms'] as $platform) {
                if (!in_array($platform, self::PUBLISHER_PLATFORMS)) {
                    throw new \Exception("Invalid publisher platform: {$platform}");
                }
            }
        }

        // Device platforms validation
        if (isset($targeting['device_platforms'])) {
            foreach ($targeting['device_platforms'] as $device) {
                if (!in_array($device, self::DEVICE_PLATFORMS)) {
                    throw new \Exception("Invalid device platform: {$device}");
                }
            }
        }
    }

    /**
     * Edit Ad Set form
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

    /**
     * Update Ad Set
     */
    public function update(Request $request, $id)
    {
        $adset = AdSet::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'daily_budget' => 'required|numeric|min:1',
            'status' => 'nullable|in:ACTIVE,PAUSED',
            'age_min' => 'nullable|integer|min:13|max:65',
            'age_max' => 'nullable|integer|min:13|max:65|gte:age_min',
            'genders' => 'nullable|array',
            'countries' => 'required_without:excluded_countries|array',
            'countries.*' => 'string|size:2|in:' . implode(',', array_keys($this->getTargetingCountries())),
            'excluded_countries' => 'nullable|array',
            'excluded_countries.*' => 'string|size:2|in:' . implode(',', array_keys($this->getTargetingCountries())),
            'languages' => 'nullable|array',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after:start_time',
            'location_types' => 'nullable|array',
            'location_types.*' => 'string|in:home,recent,travel_in',
            'exclude_locations' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        try {
            DB::beginTransaction();

            // Update targeting
            $targeting = json_decode($adset->targeting, true) ?? [];
            $targeting['age_min'] = (int)($data['age_min'] ?? 18);
            $targeting['age_max'] = (int)($data['age_max'] ?? 65);
            
            // Update geo locations
            if (!empty($data['exclude_locations'])) {
                $targeting['excluded_geo_locations']['countries'] = $data['countries'];
                unset($targeting['geo_locations']['countries']);
            } else {
                $targeting['geo_locations']['countries'] = $data['countries'];
            }
            
            // Update excluded countries
            if (!empty($data['excluded_countries'])) {
                if (!isset($targeting['excluded_geo_locations'])) {
                    $targeting['excluded_geo_locations'] = [];
                }
                $targeting['excluded_geo_locations']['countries'] = $data['excluded_countries'];
            }
            
            // Update location types
            if (!empty($data['location_types'])) {
                $targeting['geo_locations']['location_types'] = $data['location_types'];
            }
            
            // Update genders
            if (!empty($data['genders'])) {
                $targeting['genders'] = array_map('intval', array_filter($data['genders'], function($g) {
                    return in_array($g, [1, 2]);
                }));
            } else {
                unset($targeting['genders']);
            }
            
            // Update languages
            if (!empty($data['languages'])) {
                $targeting['locales'] = array_map('intval', $data['languages']);
            } else {
                unset($targeting['locales']);
            }

            // Update on Meta if synced
            if ($adset->meta_id) {
                $metaUpdate = [
                    'name' => $data['name'],
                    'daily_budget' => (int)($data['daily_budget'] * 100),
                    'targeting' => $targeting,
                    'status' => $data['status'] ?? $adset->status,
                ];

                if (!empty($data['start_time'])) {
                    $metaUpdate['start_time'] = Carbon::parse($data['start_time'])->toIso8601String();
                }
                if (!empty($data['end_time'])) {
                    $metaUpdate['end_time'] = Carbon::parse($data['end_time'])->toIso8601String();
                }

                $response = $this->meta->updateAdSet($adset->meta_id, $metaUpdate);

                if (isset($response['error'])) {
                    throw new \Exception($response['error']['message'] ?? 'Failed to update on Meta');
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

        } catch (\Exception $e) {
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

    /**
     * Delete Ad Set
     */
    public function destroy($id)
    {
        $adset = AdSet::findOrFail($id);

        try {
            DB::beginTransaction();

            if ($adset->meta_id) {
                $response = $this->meta->deleteAdSet($adset->meta_id);
                
                if (isset($response['error'])) {
                    throw new \Exception($response['error']['message'] ?? 'Failed to delete from Meta');
                }
            }

            $campaignId = $adset->campaign_id;
            $adset->delete();

            DB::commit();

            return redirect()
                ->route('admin.campaigns.show', $campaignId)
                ->with('success', 'Ad Set deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('AdSet Delete Failed', [
                'adset_id' => $id,
                'error' => $e->getMessage()
            ]);

            return back()->withErrors(['error' => 'Unable to delete Ad Set: ' . $e->getMessage()]);
        }
    }

    /**
     * Activate Ad Set
     */
    public function activate($id)
    {
        return $this->updateStatus($id, 'ACTIVE');
    }

    /**
     * Pause Ad Set
     */
    public function pause($id)
    {
        return $this->updateStatus($id, 'PAUSED');
    }

    /**
     * Update Ad Set status
     */
    private function updateStatus($id, $status)
    {
        $adset = AdSet::findOrFail($id);

        try {
            if ($adset->meta_id) {
                $response = $this->meta->updateAdSet($adset->meta_id, ['status' => $status]);
                
                if (isset($response['error'])) {
                    throw new \Exception($response['error']['message']);
                }
            }

            $adset->update(['status' => $status]);

            return response()->json([
                'success' => true,
                'message' => 'Ad Set ' . strtolower($status)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate Ad Set
     */
    public function duplicate(Request $request, $id)
    {
        $adset = AdSet::with('campaign.adAccount')->findOrFail($id);

        try {
            DB::beginTransaction();

            $newName = $adset->name . ' (Copy)';
            $metaId = null;
            $targeting = json_decode($adset->targeting, true);

            // Create on Meta if original was synced
            if ($adset->meta_id && $adset->campaign->meta_id && $adset->campaign->adAccount) {
                
                $params = [
                    'name' => $newName,
                    'campaign_id' => $adset->campaign->meta_id,
                    'daily_budget' => $adset->daily_budget,
                    'billing_event' => $adset->billing_event,
                    'optimization_goal' => $adset->optimization_goal,
                    'targeting' => $targeting,
                    'status' => 'PAUSED',
                ];

                // Add bid strategy if exists
                if ($adset->bid_strategy) {
                    $params['bid_strategy'] = $adset->bid_strategy;
                }
                
                if ($adset->bid_amount) {
                    $params['bid_amount'] = $adset->bid_amount;
                }

                $response = $this->meta->createAdSet(
                    $adset->campaign->adAccount->meta_id,
                    $params
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
                'bid_amount' => $adset->bid_amount,
                'targeting' => $adset->targeting,
                'status' => 'PAUSED',
                'promoted_object' => $adset->promoted_object,
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

    /**
     * Bulk status update
     */
    public function bulkStatusUpdate(Request $request)
    {
        $request->validate([
            'adset_ids' => 'required|array',
            'adset_ids.*' => 'exists:adsets,id',
            'status' => 'required|in:ACTIVE,PAUSED',
        ]);

        $count = 0;
        $errors = [];

        foreach ($request->adset_ids as $id) {
            try {
                $adset = AdSet::find($id);
                
                if ($adset && $adset->meta_id) {
                    $response = $this->meta->updateAdSet($adset->meta_id, ['status' => $request->status]);
                    
                    if (!isset($response['error'])) {
                        $adset->update(['status' => $request->status]);
                        $count++;
                    } else {
                        $errors[] = "Failed to update Ad Set ID: {$id}";
                    }
                } elseif ($adset) {
                    $adset->update(['status' => $request->status]);
                    $count++;
                }
            } catch (\Exception $e) {
                $errors[] = "Error with Ad Set ID: {$id} - {$e->getMessage()}";
            }
        }

        $message = "{$count} ad sets updated successfully.";
        if (!empty($errors)) {
            $message .= " Errors: " . implode(', ', $errors);
        }

        return response()->json([
            'success' => true,
            'message' => $message
        ]);
    }

    /**
     * Get AdSets by Campaign (AJAX)
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

    /**
     * Sync AdSet with Meta
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

            $targeting = json_decode($adset->targeting, true);
            
            // Validate targeting before sending to Meta
            $this->validateTargetingForMeta($targeting);

            $params = [
                'name' => $adset->name,
                'campaign_id' => $adset->campaign->meta_id,
                'daily_budget' => $adset->daily_budget,
                'billing_event' => $adset->billing_event,
                'optimization_goal' => $adset->optimization_goal,
                'targeting' => $targeting,
                'status' => $adset->status,
            ];

            if ($adset->bid_strategy) {
                $params['bid_strategy'] = $adset->bid_strategy;
            }
            
            if ($adset->bid_amount) {
                $params['bid_amount'] = $adset->bid_amount;
            }

            $response = $this->meta->createAdSet(
                $adset->campaign->adAccount->meta_id,
                $params
            );

            if (empty($response['id'])) {
                $errorMsg = $response['error']['message'] ?? 'Failed to create AdSet on Meta';
                throw new \Exception($errorMsg);
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

    /**
     * Get Ad Set insights
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

    /**
     * Parse targeting error message
     */
    private function getTargetingErrorMessage($errorMessage): string
    {
        $errorMap = [
            'age_range' => 'Please check age range (must be between 13-65)',
            'geo_locations' => 'Please select at least one valid country',
            'publisher_platforms' => 'Please select at least one platform (Facebook/Instagram)',
            'location_types' => 'Location type is invalid. Please check your location selection.',
            'interests' => 'Interest targeting is invalid',
            '1815855' => 'Your targeting criteria is too narrow. Please expand your audience.',
            '1815856' => 'Invalid targeting combination. Please check your selections.',
            '1815857' => 'Some targeting options are unavailable for your selected placements.',
            '1815858' => 'Location targeting is required.',
            '1815859' => 'Age range is invalid.',
            '1815860' => 'Gender selection is invalid.',
            '1815861' => 'Language selection contains invalid locale IDs.',
            '1815862' => 'Interest targeting contains invalid interest IDs.',
            '1815863' => 'Custom audience is invalid or unavailable.',
            '1815864' => 'Excluded locations cannot be the same as targeted locations.',
        ];

        foreach ($errorMap as $key => $message) {
            if (strpos($errorMessage, (string)$key) !== false) {
                return $message;
            }
        }

        return 'Invalid targeting parameters. Please check your selections.';
    }

    /**
     * Get all targeting countries (comprehensive list)
     */
    protected function getTargetingCountries(): array
    {
        return [
            // North America
            'US' => 'United States',
            'CA' => 'Canada',
            'MX' => 'Mexico',
            
            // Europe
            'GB' => 'United Kingdom',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
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
            'IE' => 'Ireland',
            'PL' => 'Poland',
            'CZ' => 'Czech Republic',
            'HU' => 'Hungary',
            'RO' => 'Romania',
            'BG' => 'Bulgaria',
            'HR' => 'Croatia',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'LT' => 'Lithuania',
            'LV' => 'Latvia',
            'EE' => 'Estonia',
            'IS' => 'Iceland',
            'LU' => 'Luxembourg',
            'MT' => 'Malta',
            'CY' => 'Cyprus',
            'AL' => 'Albania',
            'BA' => 'Bosnia and Herzegovina',
            'MK' => 'North Macedonia',
            'ME' => 'Montenegro',
            'RS' => 'Serbia',
            'TR' => 'Turkey',
            
            // Asia Pacific
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'SG' => 'Singapore',
            'MY' => 'Malaysia',
            'ID' => 'Indonesia',
            'PH' => 'Philippines',
            'TH' => 'Thailand',
            'VN' => 'Vietnam',
            'IN' => 'India',
            'PK' => 'Pakistan',
            'BD' => 'Bangladesh',
            'LK' => 'Sri Lanka',
            'NP' => 'Nepal',
            'KH' => 'Cambodia',
            'MM' => 'Myanmar',
            'LA' => 'Laos',
            'TW' => 'Taiwan',
            'HK' => 'Hong Kong',
            'MO' => 'Macau',
            'CN' => 'China',
            
            // Middle East & Africa
            'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia',
            'QA' => 'Qatar',
            'KW' => 'Kuwait',
            'BH' => 'Bahrain',
            'OM' => 'Oman',
            'JO' => 'Jordan',
            'LB' => 'Lebanon',
            'IL' => 'Israel',
            'EG' => 'Egypt',
            'MA' => 'Morocco',
            'DZ' => 'Algeria',
            'TN' => 'Tunisia',
            'LY' => 'Libya',
            'SD' => 'Sudan',
            'ET' => 'Ethiopia',
            'KE' => 'Kenya',
            'TZ' => 'Tanzania',
            'UG' => 'Uganda',
            'RW' => 'Rwanda',
            'ZA' => 'South Africa',
            'NG' => 'Nigeria',
            'GH' => 'Ghana',
            'CI' => 'Ivory Coast',
            'SN' => 'Senegal',
            'CM' => 'Cameroon',
            
            // South America
            'BR' => 'Brazil',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'CO' => 'Colombia',
            'PE' => 'Peru',
            'VE' => 'Venezuela',
            'EC' => 'Ecuador',
            'BO' => 'Bolivia',
            'PY' => 'Paraguay',
            'UY' => 'Uruguay',
            'GY' => 'Guyana',
            'SR' => 'Suriname',
            
            // Central America & Caribbean
            'CR' => 'Costa Rica',
            'PA' => 'Panama',
            'GT' => 'Guatemala',
            'HN' => 'Honduras',
            'NI' => 'Nicaragua',
            'SV' => 'El Salvador',
            'BZ' => 'Belize',
            'DO' => 'Dominican Republic',
            'PR' => 'Puerto Rico',
            'JM' => 'Jamaica',
            'TT' => 'Trinidad and Tobago',
            'BS' => 'Bahamas',
            'BB' => 'Barbados',
            'LC' => 'Saint Lucia',
            'VC' => 'Saint Vincent and the Grenadines',
            'GD' => 'Grenada',
            'AG' => 'Antigua and Barbuda',
            'DM' => 'Dominica',
            'KN' => 'Saint Kitts and Nevis',
            
            // Other
            'RU' => 'Russia',
            'UA' => 'Ukraine',
            'BY' => 'Belarus',
            'MD' => 'Moldova',
            'GE' => 'Georgia',
            'AM' => 'Armenia',
            'AZ' => 'Azerbaijan',
            'KZ' => 'Kazakhstan',
            'UZ' => 'Uzbekistan',
            'TM' => 'Turkmenistan',
            'KG' => 'Kyrgyzstan',
            'TJ' => 'Tajikistan',
            'MN' => 'Mongolia',
        ];
    }

    /**
     * Get targeting languages (Meta locale IDs)
     */
    protected function getTargetingLanguages(): array
    {
        return [
            // Major languages
            '6' => 'English (All)',
            '12' => 'Spanish (All)',
            '16' => 'French (All)',
            '8' => 'German (All)',
            '19' => 'Italian (All)',
            '34' => 'Portuguese (All)',
            '10' => 'Dutch (All)',
            
            // European languages
            '27' => 'Swedish',
            '24' => 'Norwegian',
            '15' => 'Danish',
            '17' => 'Finnish',
            '30' => 'Polish',
            '31' => 'Czech',
            '33' => 'Hungarian',
            '37' => 'Romanian',
            '36' => 'Bulgarian',
            '38' => 'Slovak',
            '54' => 'Slovenian',
            '55' => 'Croatian',
            '56' => 'Serbian',
            '57' => 'Bosnian',
            '58' => 'Albanian',
            '59' => 'Macedonian',
            '60' => 'Montenegrin',
            
            // Eastern European
            '29' => 'Russian',
            '39' => 'Ukrainian',
            '61' => 'Belarusian',
            
            // Baltic
            '62' => 'Lithuanian',
            '63' => 'Latvian',
            '64' => 'Estonian',
            
            // Mediterranean
            '21' => 'Greek',
            '65' => 'Maltese',
            '66' => 'Cypriot',
            
            // Middle Eastern
            '14' => 'Arabic (All)',
            '45' => 'Hebrew',
            '67' => 'Persian',
            '68' => 'Turkish',
            '69' => 'Kurdish',
            
            // South Asian
            '41' => 'Hindi',
            '70' => 'Urdu',
            '71' => 'Bengali',
            '72' => 'Punjabi',
            '73' => 'Tamil',
            '74' => 'Telugu',
            '75' => 'Marathi',
            '76' => 'Gujarati',
            '77' => 'Kannada',
            '78' => 'Malayalam',
            '79' => 'Odia',
            '80' => 'Assamese',
            '81' => 'Sinhala',
            '82' => 'Nepali',
            
            // East Asian
            '26' => 'Japanese',
            '28' => 'Korean',
            '44' => 'Chinese (All)',
            '83' => 'Cantonese',
            '84' => 'Mandarin',
            '85' => 'Taiwanese',
            
            // Southeast Asian
            '46' => 'Thai',
            '47' => 'Vietnamese',
            '48' => 'Indonesian',
            '49' => 'Malay',
            '86' => 'Tagalog',
            '87' => 'Burmese',
            '88' => 'Khmer',
            '89' => 'Lao',
            
            // African
            '50' => 'Swahili',
            '90' => 'Hausa',
            '91' => 'Yoruba',
            '92' => 'Igbo',
            '93' => 'Amharic',
            '94' => 'Somali',
            '95' => 'Shona',
            '96' => 'Zulu',
            '97' => 'Afrikaans',
            
            // Regional English variants
            '98' => 'English (UK)',
            '99' => 'English (US)',
            '100' => 'English (Australia)',
            '101' => 'English (Canada)',
            '102' => 'English (India)',
            
            // Regional Spanish variants
            '103' => 'Spanish (Spain)',
            '104' => 'Spanish (Mexico)',
            '105' => 'Spanish (Argentina)',
            '106' => 'Spanish (Colombia)',
            '107' => 'Spanish (Chile)',
            '108' => 'Spanish (Peru)',
            
            // Regional French variants
            '109' => 'French (France)',
            '110' => 'French (Canada)',
            '111' => 'French (Belgium)',
            '112' => 'French (Switzerland)',
            
            // Regional Portuguese variants
            '113' => 'Portuguese (Brazil)',
            '114' => 'Portuguese (Portugal)',
            
            // Regional German variants
            '115' => 'German (Germany)',
            '116' => 'German (Austria)',
            '117' => 'German (Switzerland)',
            
            // Regional Chinese variants
            '118' => 'Chinese (Simplified)',
            '119' => 'Chinese (Traditional)',
        ];
    }
}