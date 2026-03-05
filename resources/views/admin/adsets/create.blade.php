@extends('layouts.app')

@section('content')

<link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">

<div class="max-w-5xl mx-auto space-y-8">

    {{-- HEADER --}}
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Create Ad Set</h1>
            <p class="text-sm text-gray-500 mt-1">
                Configure targeting, budget and delivery for your campaign
            </p>
        </div>
        <a href="{{ route('admin.adsets.index') }}" 
           class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
            Back
        </a>
    </div>

    {{-- FORM CARD --}}
    <div class="bg-white shadow rounded-2xl p-8">

        @if($errors->any())
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">
                <div class="font-medium mb-2">Please fix the following errors:</div>
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.adsets.store') }}">
            @csrf

            {{-- CAMPAIGN SELECTION --}}
            <div class="mb-6">
                <label class="font-semibold block mb-2 text-gray-700">
                    Campaign <span class="text-red-500">*</span>
                </label>
                <select name="campaign_id" id="campaign-select" class="w-full border rounded-xl px-4 py-3 bg-white" required>
                    <option value="">Select a campaign</option>
                    @foreach($campaigns as $campaign)
                        <option value="{{ $campaign->id }}" {{ old('campaign_id') == $campaign->id ? 'selected' : '' }}>
                            {{ $campaign->name }} ({{ $campaign->objective ?? 'No objective' }})
                        </option>
                    @endforeach
                </select>
                <p class="text-sm text-gray-500 mt-2">
                    The campaign's objective will determine available optimization options
                </p>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                {{-- LEFT COLUMN --}}
                <div class="space-y-6">

                    {{-- AD SET NAME --}}
                    <div>
                        <label class="font-semibold block mb-2 text-gray-700">
                            Ad Set Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" value="{{ old('name') }}" 
                               class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="e.g., USA Students 18-35" required>
                    </div>

                    {{-- BUDGET --}}
                    <div>
                        <label class="font-semibold block mb-2 text-gray-700">
                            Daily Budget ({{ $account_currency ?? 'USD' }}) <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-3 text-gray-500">{{ $account_currency ?? '$' }}</span>
                            <input type="number" name="daily_budget" value="{{ old('daily_budget') }}" 
                                   class="w-full border rounded-xl pl-10 pr-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="50.00" min="1" step="1.00" required>
                        </div>
                        <p class="text-sm text-gray-500 mt-2">Minimum budget: {{ $account_currency ?? '$' }}1.00</p>
                    </div>

                    {{-- BID STRATEGY --}}
                    <div>
    <label class="font-semibold block mb-2 text-gray-700">
        Bid Strategy
    </label>

    <select name="bid_strategy" id="bid-strategy"
        class="w-full border rounded-xl px-4 py-3 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">

        <option value="LOWEST_COST_WITHOUT_CAP"
            {{ old('bid_strategy') == 'LOWEST_COST_WITHOUT_CAP' ? 'selected' : '' }}>
            Lowest Cost (No Cap)
        </option>

        <option value="LOWEST_COST_WITH_BID_CAP"
            {{ old('bid_strategy') == 'LOWEST_COST_WITH_BID_CAP' ? 'selected' : '' }}>
            Lowest Cost (With Bid Cap)
        </option>

        <option value="COST_CAP"
            {{ old('bid_strategy') == 'COST_CAP' ? 'selected' : '' }}>
            Cost Cap
        </option>

    </select>
</div>

                    {{-- OPTIMIZATION GOAL --}}
                    <div>
                        <label class="font-semibold block mb-2 text-gray-700">
                            Optimization Goal
                        </label>
                        <select name="optimization_goal" id="optimization-goal" class="w-full border rounded-xl px-4 py-3 bg-white">
                            <option value="REACH">Reach</option>
                            <option value="IMPRESSIONS">Impressions</option>
                            <option value="LANDING_PAGE_VIEWS">Landing Page Views</option>
                            <option value="LINK_CLICKS">Link Clicks</option>
                            <option value="LEAD">Leads</option>
                            <option value="CONVERSIONS">Conversions</option>
                            <option value="VALUE">Value</option>
                        </select>
                    </div>

                    {{-- BILLING EVENT --}}
                    <div>
                        <label class="font-semibold block mb-2 text-gray-700">
                            Billing Event
                        </label>
                        <select name="billing_event" id="billing-event" class="w-full border rounded-xl px-4 py-3 bg-white">
                            <option value="IMPRESSIONS">Impressions</option>
                            <option value="LINK_CLICKS">Link Clicks</option>
                            <option value="PAGE_LIKES">Page Likes</option>
                            <option value="POST_ENGAGEMENT">Post Engagement</option>
                            <option value="LEAD">Lead</option>
                            <option value="THRUPLAY">ThruPlay</option>
                        </select>
                    </div>
                </div>

                {{-- RIGHT COLUMN --}}
                <div class="space-y-6">
                    {{-- SCHEDULING --}}
                    <div class="border rounded-xl p-4 bg-gray-50">
                        <h3 class="font-semibold mb-3 text-gray-700">Schedule</h3>
                        
                        <div class="space-y-3">
                            <label class="flex items-center space-x-3">
                                <input type="radio" name="schedule_type" value="now" checked class="text-blue-600">
                                <span>Run continuously starting now</span>
                            </label>
                            
                            <label class="flex items-center space-x-3">
                                <input type="radio" name="schedule_type" value="start_end" class="text-blue-600">
                                <span>Set start and end dates</span>
                            </label>
                        </div>

                        <div class="mt-4 space-y-3 schedule-dates hidden">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                <input type="datetime-local" name="start_time" class="w-full border rounded-lg px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">End Date (Optional)</label>
                                <input type="datetime-local" name="end_time" class="w-full border rounded-lg px-3 py-2">
                            </div>
                        </div>
                    </div>

                  {{-- STATUS --}}
<div>
    <label class="font-semibold block mb-2 text-gray-700">Initial Status</label>
    <div class="flex space-x-4">
        <label class="flex items-center space-x-2">
            <input type="radio" name="status" value="ACTIVE" checked class="text-blue-600">
            <span>Active (Start immediately)</span>
        </label>
        <label class="flex items-center space-x-2">
            <input type="radio" name="status" value="PAUSED" class="text-blue-600">
            <span>Paused (Create but don't run)</span>
        </label>
    </div>
</div>

                    {{-- PROMOTED OBJECT --}}
                    <div class="border rounded-xl p-4 bg-gray-50">
                        <h3 class="font-semibold mb-3 text-gray-700">Promoted Object (Optional)</h3>
                        <select name="promoted_object_type" id="promoted-object" class="w-full border rounded-lg px-3 py-2 mb-3">
                            <option value="">None</option>
                            <option value="page">Facebook Page</option>
                            <option value="event">Event</option>
                            <option value="instagram">Instagram Account</option>
                        </select>
                        <input type="text" name="promoted_object_id" placeholder="Enter ID" 
                               class="w-full border rounded-lg px-3 py-2">
                    </div>
                </div>
            </div>

            {{-- TARGETING SECTION --}}
            <div class="mt-8 border-t pt-6">
                <h2 class="text-xl font-semibold mb-4 text-gray-800">Audience Targeting</h2>
                
                <div class="grid md:grid-cols-2 gap-6">
                    
                    {{-- AGE RANGE --}}
                    <div>
                        <label class="font-semibold block mb-2 text-gray-700">
                            Age Range
                        </label>
                        <div class="flex items-center space-x-3">
                            <input type="number" name="age_min" value="{{ old('age_min', 18) }}" 
                                   class="w-24 border rounded-xl px-4 py-3 text-center" min="13" max="65">
                            <span>to</span>
                            <input type="number" name="age_max" value="{{ old('age_max', 65) }}" 
                                   class="w-24 border rounded-xl px-4 py-3 text-center" min="13" max="65">
                        </div>
                    </div>

                    {{-- GENDER --}}
                    <div>
                        <label class="font-semibold block mb-2 text-gray-700">
                            Gender
                        </label>
                        <select name="genders[]" multiple id="gender-select" class="w-full border rounded-xl px-4 py-3 bg-white">
                            <option value="1">Male</option>
                            <option value="2">Female</option>
                            <option value="0">All (Default)</option>
                        </select>
                    </div>

                    {{-- LOCATIONS --}}
                    <div class="md:col-span-2">
                        <label class="font-semibold block mb-2 text-gray-700">
                            Locations <span class="text-red-500">*</span>
                        </label>
                        
                        <div class="mb-3">
                            <select name="location_type" class="border rounded-lg px-3 py-2 text-sm">
                                <option value="living_or_recent">People living in or recently in this location</option>
                                <option value="living">People living in this location</option>
                                <option value="recent">People recently in this location</option>
                                <option value="traveling">People traveling in this location</option>
                            </select>
                        </div>

                        <select name="countries[]" multiple id="country-select" class="w-full border rounded-xl px-4 py-3 bg-white" required>
                            @foreach($countries as $code => $country)
                                <option value="{{ $code }}" {{ in_array($code, old('countries', [])) ? 'selected' : '' }}>
                                    {{ $country }}
                                </option>
                            @endforeach
                        </select>
                        
                        <div class="mt-3">
                            <label class="flex items-center space-x-2 text-sm text-gray-600">
                                <input type="checkbox" name="exclude_locations" value="1" class="rounded">
                                <span>Exclude people in these locations</span>
                            </label>
                        </div>
                    </div>

                    {{-- LANGUAGES --}}
                    <div>
                        <label class="font-semibold block mb-2 text-gray-700">
                            Languages
                        </label>
                        <select name="languages[]" multiple id="language-select" class="w-full border rounded-xl px-4 py-3 bg-white">
                            @foreach($languages ?? ['en' => 'English', 'fr' => 'French', 'es' => 'Spanish'] as $code => $language)
                                <option value="{{ $code }}">{{ $language }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- INTERESTS --}}
                    <div>
                        <label class="font-semibold block mb-2 text-gray-700">
                            Detailed Targeting (Interests)
                        </label>
                        <select name="interests[]" multiple id="interest-select" class="w-full border rounded-xl px-4 py-3 bg-white">
                            <option value="6003139266461">Study abroad</option>
                            <option value="6003159268461">International students</option>
                            <option value="6003139294461">Scholarships</option>
                            <option value="6003139298461">University</option>
                            <option value="6003139299461">College</option>
                            <option value="6003159269461">Visa application</option>
                            <option value="6003139280461">Education abroad</option>
                            <option value="6003139281461">Student visa</option>
                            <option value="6003159270461">Work abroad</option>
                            <option value="6003159271461">Immigration</option>
                        </select>
                        <p class="text-sm text-gray-500 mt-2">Select interests to target (max 10)</p>
                    </div>

                    {{-- CONNECTIONS --}}
                    <div>
                        <label class="font-semibold block mb-2 text-gray-700">
                            Connections
                        </label>
                        <select name="connections_type" class="w-full border rounded-xl px-4 py-3 bg-white mb-2">
                            <option value="">Targeting</option>
                            <option value="and">People who are connected to</option>
                            <option value="or">People who are not connected to</option>
                        </select>
                        <select name="connections[]" multiple id="connection-select" class="w-full border rounded-xl px-4 py-3 bg-white">
                            <option value="page">Your Page</option>
                            <option value="event">Your Event</option>
                            <option value="app">Your App</option>
                        </select>
                    </div>

                    {{-- DEVICE TARGETING --}}
                    <div>
                        <label class="font-semibold block mb-2 text-gray-700">
                            Device Targeting
                        </label>
                        <select name="device_platforms[]" multiple id="device-select" class="w-full border rounded-xl px-4 py-3 bg-white">
                            <option value="mobile">Mobile (All)</option>
                            <option value="desktop">Desktop</option>
                            <option value="feature_phone">Feature Phones</option>
                        </select>
                        
                        <div class="mt-3">
                            <select name="publisher_platforms[]" multiple id="platform-select" class="w-full border rounded-xl px-4 py-3 bg-white">
                                <option value="facebook">Facebook</option>
                                <option value="instagram">Instagram</option>
                                <option value="messenger">Messenger</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="audience_network">Audience Network</option>
                            </select>
                        </div>
                    </div>

                    {{-- FLEXIBLE SPEC --}}
                    <div class="md:col-span-2">
                        <label class="font-semibold block mb-2 text-gray-700">
                            Flexible Targeting (JSON)
                        </label>
                        <textarea name="flexible_spec" rows="2" class="w-full border rounded-xl px-4 py-3 font-mono text-sm"
                                  placeholder='[{"interests":[{"id":"6003139266461","name":"Study abroad"}]}]'>{{ old('flexible_spec') }}</textarea>
                        <p class="text-sm text-gray-500 mt-2">Advanced: Raw targeting JSON for Meta API</p>
                    </div>
                </div>
            </div>

            {{-- AD PLACEMENT SECTION --}}
            <div class="mt-8 border-t pt-6">
                <h2 class="text-xl font-semibold mb-4 text-gray-800">Ad Placements</h2>
                
                <div class="space-y-4">
                    <label class="flex items-center space-x-3">
                        <input type="radio" name="placement_type" value="automatic" checked class="text-blue-600">
                        <span class="font-medium">Automatic Placements (Recommended)</span>
                        <span class="text-sm text-gray-500 ml-2">- Show ads where they're likely to perform best</span>
                    </label>
                    
                    <label class="flex items-center space-x-3">
                        <input type="radio" name="placement_type" value="manual" class="text-blue-600">
                        <span class="font-medium">Manual Placements</span>
                        <span class="text-sm text-gray-500 ml-2">- Choose where to show your ads</span>
                    </label>
                </div>

                <div id="manual-placements" class="mt-4 grid grid-cols-2 md:grid-cols-3 gap-4 hidden">
                    <div>
                        <h3 class="font-medium mb-2">Facebook</h3>
                        <div class="space-y-2">
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="facebook_positions[]" value="feed" class="rounded">
                                <span class="text-sm">Feed</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="facebook_positions[]" value="video_feeds" class="rounded">
                                <span class="text-sm">Video Feeds</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="facebook_positions[]" value="marketplace" class="rounded">
                                <span class="text-sm">Marketplace</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="facebook_positions[]" value="right_column" class="rounded">
                                <span class="text-sm">Right Column</span>
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="font-medium mb-2">Instagram</h3>
                        <div class="space-y-2">
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="instagram_positions[]" value="stream" class="rounded">
                                <span class="text-sm">Feed</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="instagram_positions[]" value="story" class="rounded">
                                <span class="text-sm">Stories</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="instagram_positions[]" value="reels" class="rounded">
                                <span class="text-sm">Reels</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="instagram_positions[]" value="explore" class="rounded">
                                <span class="text-sm">Explore</span>
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="font-medium mb-2">Other</h3>
                        <div class="space-y-2">
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="messenger_positions[]" value="messenger_home" class="rounded">
                                <span class="text-sm">Messenger Inbox</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="messenger_positions[]" value="story" class="rounded">
                                <span class="text-sm">Messenger Stories</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="audience_network_positions[]" value="native" class="rounded">
                                <span class="text-sm">Audience Network</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- META CONNECTION --}}
            <div class="mt-8 border-t pt-6">
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <div class="flex items-start space-x-3">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div class="flex-1">
                            <h3 class="font-semibold text-blue-800">Meta API Integration</h3>
                            <p class="text-sm text-blue-700 mt-1">
                                This ad set will be created in Meta Ads Manager under the selected campaign.
                                @if(isset($ad_account))
                                    Using account: <span class="font-mono">{{ $ad_account->ad_account_id }}</span>
                                @endif
                            </p>
                            <div class="mt-3 text-xs text-blue-600 space-y-1">
                                <p>✓ Campaign must be active on Meta</p>
                                <p>✓ Daily budget will be set in {{ $account_currency ?? 'USD' }}</p>
                                <p>✓ Targeting will be validated by Meta API</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- FORM ACTIONS --}}
            <div class="mt-8 flex justify-end space-x-3">
                <a href="{{ route('admin.adsets.index') }}" 
                   class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" 
                        class="bg-blue-600 text-white px-8 py-3 rounded-xl shadow hover:bg-blue-700 transition font-medium">
                    Create Ad Set
                </button>
            </div>

        </form>
    </div>
</div>

{{-- TOM SELECT & UI SCRIPTS --}}
<script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Tom Select for multi-selects
        new TomSelect("#campaign-select", {
            plugins: ['dropdown_input'],
            maxOptions: 100
        });

        new TomSelect("#country-select", {
            plugins: ['remove_button', 'dropdown_input'],
            maxItems: null,
            placeholder: 'Select countries to target'
        });

        new TomSelect("#gender-select", {
            plugins: ['remove_button'],
            maxItems: 2
        });

        new TomSelect("#language-select", {
            plugins: ['remove_button'],
            maxItems: null,
            placeholder: 'Select languages'
        });

        new TomSelect("#interest-select", {
            plugins: ['remove_button'],
            maxItems: 10,
            placeholder: 'Select interests (max 10)'
        });

        new TomSelect("#connection-select", {
            plugins: ['remove_button'],
            maxItems: null
        });

        new TomSelect("#device-select", {
            plugins: ['remove_button'],
            maxItems: null
        });

        new TomSelect("#platform-select", {
            plugins: ['remove_button'],
            maxItems: null
        });

        // Schedule toggle
        const scheduleRadios = document.querySelectorAll('input[name="schedule_type"]');
        const scheduleDates = document.querySelector('.schedule-dates');
        
        scheduleRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'start_end') {
                    scheduleDates.classList.remove('hidden');
                } else {
                    scheduleDates.classList.add('hidden');
                }
            });
        });

        // Placement toggle
        const placementRadios = document.querySelectorAll('input[name="placement_type"]');
        const manualPlacements = document.getElementById('manual-placements');
        
        placementRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'manual') {
                    manualPlacements.classList.remove('hidden');
                } else {
                    manualPlacements.classList.add('hidden');
                }
            });
        });

        // Budget validation
        const budgetInput = document.querySelector('input[name="daily_budget"]');
        if (budgetInput) {
            budgetInput.addEventListener('change', function() {
                const value = parseFloat(this.value);
                if (value < 1) {
                    this.value = 1;
                }
            });
        }

        // Age validation
        const ageMin = document.querySelector('input[name="age_min"]');
        const ageMax = document.querySelector('input[name="age_max"]');
        
        if (ageMin && ageMax) {
            ageMin.addEventListener('change', function() {
                if (parseInt(this.value) < 13) this.value = 13;
                if (parseInt(this.value) > 65) this.value = 65;
                if (parseInt(ageMax.value) < parseInt(this.value)) {
                    ageMax.value = this.value;
                }
            });
            
            ageMax.addEventListener('change', function() {
                if (parseInt(this.value) < 13) this.value = 13;
                if (parseInt(this.value) > 65) this.value = 65;
                if (parseInt(ageMin.value) > parseInt(this.value)) {
                    ageMin.value = this.value;
                }
            });
        }

        // JSON validation for flexible spec
        const flexibleSpec = document.querySelector('textarea[name="flexible_spec"]');
        if (flexibleSpec) {
            flexibleSpec.addEventListener('blur', function() {
                try {
                    if (this.value.trim()) {
                        JSON.parse(this.value);
                        this.classList.remove('border-red-500');
                    }
                } catch (e) {
                    this.classList.add('border-red-500');
                }
            });
        }
    });
</script>

@endsection