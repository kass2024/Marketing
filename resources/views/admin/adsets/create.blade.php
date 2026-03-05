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
           class="bg-gray-600 text-white px-4 py-3 rounded-xl hover:bg-gray-700 transition font-medium">
            Back
        </a>
    </div>

    {{-- FORM CARD --}}
    <div class="bg-white shadow-sm border border-gray-200 rounded-2xl p-8">

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

        <form method="POST" action="{{ route('admin.adsets.store') }}" id="adsetForm">
            @csrf

            {{-- CAMPAIGN SELECTION --}}
            <div class="mb-6">
                <label class="font-semibold block mb-2 text-gray-700">
                    Campaign <span class="text-red-500">*</span>
                </label>
                <select name="campaign_id" id="campaign-select" class="w-full border border-gray-300 rounded-xl px-4 py-3 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
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
                               class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                                   class="w-full border border-gray-300 rounded-xl pl-10 pr-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                            class="w-full border border-gray-300 rounded-xl px-4 py-3 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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
                        
                        {{-- Bid Amount (shown when cap strategies selected) --}}
                        <div id="bid-amount-field" class="mt-3 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Bid Amount ({{ $account_currency ?? 'USD' }})
                            </label>
                            <input type="number" name="bid_amount" value="{{ old('bid_amount') }}" 
                                   class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Enter bid amount" min="0.01" step="0.01">
                            <p class="text-xs text-gray-500 mt-1">Maximum amount you're willing to pay per result</p>
                        </div>
                    </div>

                    {{-- OPTIMIZATION GOAL --}}
                    <div>
                        <label class="font-semibold block mb-2 text-gray-700">
                            Optimization Goal
                        </label>
                        <select name="optimization_goal" id="optimization-goal" class="w-full border border-gray-300 rounded-xl px-4 py-3 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="REACH" {{ old('optimization_goal') == 'REACH' ? 'selected' : '' }}>Reach</option>
                            <option value="IMPRESSIONS" {{ old('optimization_goal') == 'IMPRESSIONS' ? 'selected' : '' }}>Impressions</option>
                            <option value="LANDING_PAGE_VIEWS" {{ old('optimization_goal') == 'LANDING_PAGE_VIEWS' ? 'selected' : '' }}>Landing Page Views</option>
                            <option value="LINK_CLICKS" {{ old('optimization_goal') == 'LINK_CLICKS' ? 'selected' : '' }}>Link Clicks</option>
                            <option value="LEAD" {{ old('optimization_goal') == 'LEAD' ? 'selected' : '' }}>Leads</option>
                            <option value="CONVERSIONS" {{ old('optimization_goal') == 'CONVERSIONS' ? 'selected' : '' }}>Conversions</option>
                            <option value="VALUE" {{ old('optimization_goal') == 'VALUE' ? 'selected' : '' }}>Value</option>
                        </select>
                    </div>

                    {{-- BILLING EVENT --}}
                    <div>
                        <label class="font-semibold block mb-2 text-gray-700">
                            Billing Event
                        </label>
                        <select name="billing_event" id="billing-event" class="w-full border border-gray-300 rounded-xl px-4 py-3 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="IMPRESSIONS" {{ old('billing_event') == 'IMPRESSIONS' ? 'selected' : '' }}>Impressions</option>
                            <option value="LINK_CLICKS" {{ old('billing_event') == 'LINK_CLICKS' ? 'selected' : '' }}>Link Clicks</option>
                            <option value="PAGE_LIKES" {{ old('billing_event') == 'PAGE_LIKES' ? 'selected' : '' }}>Page Likes</option>
                            <option value="POST_ENGAGEMENT" {{ old('billing_event') == 'POST_ENGAGEMENT' ? 'selected' : '' }}>Post Engagement</option>
                            <option value="LEAD" {{ old('billing_event') == 'LEAD' ? 'selected' : '' }}>Lead</option>
                            <option value="THRUPLAY" {{ old('billing_event') == 'THRUPLAY' ? 'selected' : '' }}>ThruPlay</option>
                        </select>
                    </div>
                </div>

                {{-- RIGHT COLUMN --}}
                <div class="space-y-6">
                    {{-- SCHEDULING --}}
                    <div class="border border-gray-200 rounded-xl p-4 bg-gray-50">
                        <h3 class="font-semibold mb-3 text-gray-700">Schedule</h3>
                        
                        <div class="space-y-3">
                            <label class="flex items-center space-x-3">
                                <input type="radio" name="schedule_type" value="now" {{ old('schedule_type', 'now') == 'now' ? 'checked' : '' }} class="text-blue-600">
                                <span>Run continuously starting now</span>
                            </label>
                            
                            <label class="flex items-center space-x-3">
                                <input type="radio" name="schedule_type" value="start_end" {{ old('schedule_type') == 'start_end' ? 'checked' : '' }} class="text-blue-600">
                                <span>Set start and end dates</span>
                            </label>
                        </div>

                        <div class="mt-4 space-y-3 schedule-dates {{ old('schedule_type') == 'start_end' ? '' : 'hidden' }}">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                <input type="datetime-local" name="start_time" value="{{ old('start_time') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">End Date (Optional)</label>
                                <input type="datetime-local" name="end_time" value="{{ old('end_time') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>

                    {{-- STATUS --}}
                    <div>
                        <label class="font-semibold block mb-2 text-gray-700">Initial Status</label>
                        <div class="flex space-x-4">
                            <label class="flex items-center space-x-2">
                                <input type="radio" name="status" value="ACTIVE" {{ old('status', 'ACTIVE') == 'ACTIVE' ? 'checked' : '' }} class="text-blue-600">
                                <span>Active (Start immediately)</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="radio" name="status" value="PAUSED" {{ old('status') == 'PAUSED' ? 'checked' : '' }} class="text-blue-600">
                                <span>Paused (Create but don't run)</span>
                            </label>
                        </div>
                    </div>

                    {{-- PROMOTED OBJECT --}}
                    <div class="border border-gray-200 rounded-xl p-4 bg-gray-50">
                        <h3 class="font-semibold mb-3 text-gray-700">Promoted Object (Optional)</h3>
                        <select name="promoted_object_type" id="promoted-object-type" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">None</option>
                            <option value="page" {{ old('promoted_object_type') == 'page' ? 'selected' : '' }}>Facebook Page</option>
                            <option value="event" {{ old('promoted_object_type') == 'event' ? 'selected' : '' }}>Event</option>
                            <option value="instagram" {{ old('promoted_object_type') == 'instagram' ? 'selected' : '' }}>Instagram Account</option>
                            <option value="application" {{ old('promoted_object_type') == 'application' ? 'selected' : '' }}>Application</option>
                        </select>
                        
                        <div id="promoted-object-id-field">
                            <input type="text" name="promoted_object_id" value="{{ old('promoted_object_id') }}" placeholder="Enter ID" 
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div id="promoted-object-page-field" class="mt-3 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Associated Page ID (for Instagram)</label>
                            <input type="text" name="promoted_object_page_id" value="{{ old('promoted_object_page_id') }}" placeholder="Enter Page ID" 
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                </div>
            </div>

            {{-- TARGETING SECTION --}}
            <div class="mt-8 border-t border-gray-200 pt-6">
                <h2 class="text-xl font-semibold mb-4 text-gray-800">Audience Targeting</h2>
                
                <div class="grid md:grid-cols-2 gap-6">
                    
                    {{-- AGE RANGE --}}
                    <div>
                        <label class="font-semibold block mb-2 text-gray-700">
                            Age Range
                        </label>
                        <div class="flex items-center space-x-3">
                            <input type="number" name="age_min" value="{{ old('age_min', 18) }}" 
                                   class="w-24 border border-gray-300 rounded-xl px-4 py-3 text-center focus:ring-2 focus:ring-blue-500 focus:border-blue-500" min="13" max="65">
                            <span>to</span>
                            <input type="number" name="age_max" value="{{ old('age_max', 65) }}" 
                                   class="w-24 border border-gray-300 rounded-xl px-4 py-3 text-center focus:ring-2 focus:ring-blue-500 focus:border-blue-500" min="13" max="65">
                        </div>
                    </div>

                    {{-- GENDER --}}
                    <div>
                        <label class="font-semibold block mb-2 text-gray-700">
                            Gender
                        </label>
                        <select name="genders[]" multiple id="gender-select" class="w-full border border-gray-300 rounded-xl px-4 py-3 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="1" {{ in_array(1, old('genders', [])) ? 'selected' : '' }}>Male</option>
                            <option value="2" {{ in_array(2, old('genders', [])) ? 'selected' : '' }}>Female</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Leave empty to target all genders</p>
                    </div>

                    {{-- LOCATIONS - FIXED: Using 'countries[]' not 'locations[countries][]' --}}
                    <div class="md:col-span-2">
                        <label class="font-semibold block mb-2 text-gray-700">
                            Locations <span class="text-red-500">*</span>
                        </label>

                        {{-- Location Type --}}
                        <div class="mb-3">
                            <select name="location_type" id="location-type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="living_or_recent" {{ old('location_type', 'living_or_recent') == 'living_or_recent' ? 'selected' : '' }}>People who live in or recently visited this location</option>
                                <option value="living" {{ old('location_type') == 'living' ? 'selected' : '' }}>People who live in this location</option>
                                <option value="recent" {{ old('location_type') == 'recent' ? 'selected' : '' }}>People recently in this location</option>
                                <option value="traveling" {{ old('location_type') == 'traveling' ? 'selected' : '' }}>People traveling in this location</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">
                                Defines how Meta interprets the selected locations
                            </p>
                        </div>

                        {{-- Country Selection - FIXED: Using 'countries[]' to match controller --}}
                        <select name="countries[]" multiple id="country-select" class="w-full border border-gray-300 rounded-xl px-4 py-3 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            @foreach($countries as $code => $country)
                                <option value="{{ $code }}" {{ in_array($code, old('countries', [])) ? 'selected' : '' }}>
                                    {{ $country }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Select one or more countries to target</p>

                        {{-- Location Exclusion --}}
                        <div class="mt-3">
                            <label class="flex items-center space-x-2 text-sm text-gray-600">
                                <input type="checkbox" name="exclude_locations" value="1" {{ old('exclude_locations') ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span>Exclude people in these locations</span>
                            </label>
                        </div>
                        
                        {{-- Excluded Countries (Alternative) --}}
                        <div id="excluded-countries-field" class="mt-3 {{ old('exclude_locations') ? 'hidden' : '' }}">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Exclude Specific Countries</label>
                            <select name="excluded_countries[]" multiple id="excluded-countries-select" class="w-full border border-gray-300 rounded-xl px-4 py-3 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                @foreach($countries as $code => $country)
                                    <option value="{{ $code }}" {{ in_array($code, old('excluded_countries', [])) ? 'selected' : '' }}>
                                        {{ $country }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Select countries to exclude from targeting</p>
                        </div>
                    </div>

                    {{-- LANGUAGES - FIXED: Using 'languages[]' to match controller --}}
                    <div>
                        <label class="font-semibold block mb-2 text-gray-700">
                            Languages
                        </label>
                        <select name="languages[]" multiple id="language-select" class="w-full border border-gray-300 rounded-xl px-4 py-3 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            @foreach($languages as $code => $language)
                                <option value="{{ $code }}" {{ in_array($code, old('languages', [])) ? 'selected' : '' }}>
                                    {{ $language }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Using Meta locale IDs</p>
                    </div>

                    {{-- INTERESTS --}}
<div>
    <label class="font-semibold block mb-2 text-gray-700">
        Detailed Targeting (Interests)
    </label>

    <select
        name="interests[]"
        id="interest-select"
        multiple
        placeholder="Search interests like 'Scholarship', 'Study abroad'"
        class="w-full border border-gray-300 rounded-xl px-4 py-3 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
    </select>

    <p class="text-sm text-gray-500 mt-2">
        Start typing to search Meta interests (max 10)
    </p>

    {{-- Restore old selected values --}}
    @if(old('interests'))
        @foreach(old('interests') as $interest)
            <input type="hidden" name="interests[]" value="{{ $interest }}">
        @endforeach
    @endif
</div>
                    {{-- CONNECTIONS - FIXED: Using proper field names --}}
                    <div>
                        <label class="font-semibold block mb-2 text-gray-700">
                            Connections
                        </label>
                        <select name="connections_type" class="w-full border border-gray-300 rounded-xl px-4 py-3 bg-white mb-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="and" {{ old('connections_type', 'and') == 'and' ? 'selected' : '' }}>People who are connected to</option>
                            <option value="or" {{ old('connections_type') == 'or' ? 'selected' : '' }}>People who are connected to (OR)</option>
                            <option value="not" {{ old('connections_type') == 'not' ? 'selected' : '' }}>People who are NOT connected to</option>
                        </select>
                        <select name="connections[]" multiple id="connection-select" class="w-full border border-gray-300 rounded-xl px-4 py-3 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="page" {{ in_array('page', old('connections', [])) ? 'selected' : '' }}>Your Page</option>
                            <option value="event" {{ in_array('event', old('connections', [])) ? 'selected' : '' }}>Your Event</option>
                            <option value="app" {{ in_array('app', old('connections', [])) ? 'selected' : '' }}>Your App</option>
                        </select>
                    </div>

                    {{-- DEVICE TARGETING - FIXED: Using proper field names --}}
                    <div>
                        <label class="font-semibold block mb-2 text-gray-700">
                            Device Targeting
                        </label>
                        <select name="device_platforms[]" multiple id="device-select" class="w-full border border-gray-300 rounded-xl px-4 py-3 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="mobile" {{ in_array('mobile', old('device_platforms', [])) ? 'selected' : '' }}>Mobile</option>
                            <option value="desktop" {{ in_array('desktop', old('device_platforms', [])) ? 'selected' : '' }}>Desktop</option>
                        </select>
                        
                        <div class="mt-3">
                            <select name="publisher_platforms[]" multiple id="platform-select" class="w-full border border-gray-300 rounded-xl px-4 py-3 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="facebook" {{ in_array('facebook', old('publisher_platforms', [])) ? 'selected' : '' }}>Facebook</option>
                                <option value="instagram" {{ in_array('instagram', old('publisher_platforms', [])) ? 'selected' : '' }}>Instagram</option>
                                <option value="messenger" {{ in_array('messenger', old('publisher_platforms', [])) ? 'selected' : '' }}>Messenger</option>
                                <option value="whatsapp" {{ in_array('whatsapp', old('publisher_platforms', [])) ? 'selected' : '' }}>WhatsApp</option>
                                <option value="audience_network" {{ in_array('audience_network', old('publisher_platforms', [])) ? 'selected' : '' }}>Audience Network</option>
                            </select>
                        </div>
                    </div>

                    {{-- FLEXIBLE SPEC --}}
                    <div class="md:col-span-2">
                        <label class="font-semibold block mb-2 text-gray-700">
                            Flexible Targeting (JSON)
                        </label>
                        <textarea name="flexible_spec" id="flexible-spec" rows="2" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-mono text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder='{"interests":[{"id":"6003139266461","name":"Study abroad"}]}'>{{ old('flexible_spec', '{"interests":[]}') }}</textarea>
                        <p class="text-sm text-gray-500 mt-2">Advanced: Raw targeting JSON for Meta API - Must be valid JSON object</p>
                        <p class="text-xs text-red-500 mt-1 hidden" id="json-error">Invalid JSON format</p>
                    </div>

                    {{-- HIDDEN TARGETING FIELD (optional, can be removed if not needed) --}}
                    {{-- <input type="hidden" name="targeting_json" id="targeting-json"> --}}
                </div>
            </div>

            {{-- AD PLACEMENT SECTION --}}
            <div class="mt-8 border-t border-gray-200 pt-6">
                <h2 class="text-xl font-semibold mb-4 text-gray-800">Ad Placements</h2>
                
                <div class="space-y-4">
                    <label class="flex items-center space-x-3">
                        <input type="radio" name="placement_type" value="automatic" {{ old('placement_type', 'automatic') == 'automatic' ? 'checked' : '' }} class="text-blue-600">
                        <span class="font-medium">Automatic Placements (Recommended)</span>
                        <span class="text-sm text-gray-500 ml-2">- Show ads where they're likely to perform best</span>
                    </label>
                    
                    <label class="flex items-center space-x-3">
                        <input type="radio" name="placement_type" value="manual" {{ old('placement_type') == 'manual' ? 'checked' : '' }} class="text-blue-600">
                        <span class="font-medium">Manual Placements</span>
                        <span class="text-sm text-gray-500 ml-2">- Choose where to show your ads</span>
                    </label>
                </div>

                <div id="manual-placements" class="mt-4 grid grid-cols-2 md:grid-cols-3 gap-4 {{ old('placement_type') == 'manual' ? '' : 'hidden' }}">
                    <div>
                        <h3 class="font-medium mb-2">Facebook</h3>
                        <div class="space-y-2">
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="facebook_positions[]" value="feed" {{ in_array('feed', old('facebook_positions', [])) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm">Feed</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="facebook_positions[]" value="video_feeds" {{ in_array('video_feeds', old('facebook_positions', [])) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm">Video Feeds</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="facebook_positions[]" value="marketplace" {{ in_array('marketplace', old('facebook_positions', [])) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm">Marketplace</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="facebook_positions[]" value="right_column" {{ in_array('right_column', old('facebook_positions', [])) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm">Right Column</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="facebook_positions[]" value="instant_article" {{ in_array('instant_article', old('facebook_positions', [])) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm">Instant Articles</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="facebook_positions[]" value="in_stream" {{ in_array('in_stream', old('facebook_positions', [])) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm">In-Stream Videos</span>
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="font-medium mb-2">Instagram</h3>
                        <div class="space-y-2">
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="instagram_positions[]" value="stream" {{ in_array('stream', old('instagram_positions', [])) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm">Feed</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="instagram_positions[]" value="story" {{ in_array('story', old('instagram_positions', [])) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm">Stories</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="instagram_positions[]" value="reels" {{ in_array('reels', old('instagram_positions', [])) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm">Reels</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="instagram_positions[]" value="explore" {{ in_array('explore', old('instagram_positions', [])) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm">Explore</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="instagram_positions[]" value="shop" {{ in_array('shop', old('instagram_positions', [])) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm">Shop</span>
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="font-medium mb-2">Messenger</h3>
                        <div class="space-y-2">
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="messenger_positions[]" value="messenger_home" {{ in_array('messenger_home', old('messenger_positions', [])) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm">Inbox</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="messenger_positions[]" value="story" {{ in_array('story', old('messenger_positions', [])) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm">Stories</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="messenger_positions[]" value="sponsored_messages" {{ in_array('sponsored_messages', old('messenger_positions', [])) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm">Sponsored Messages</span>
                            </label>
                        </div>
                        
                        <h3 class="font-medium mb-2 mt-4">Audience Network</h3>
                        <div class="space-y-2">
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="audience_network_positions[]" value="native" {{ in_array('native', old('audience_network_positions', [])) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm">Native</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="audience_network_positions[]" value="banner" {{ in_array('banner', old('audience_network_positions', [])) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm">Banner</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="audience_network_positions[]" value="interstitial" {{ in_array('interstitial', old('audience_network_positions', [])) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm">Interstitial</span>
                            </label>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="audience_network_positions[]" value="rewarded_video" {{ in_array('rewarded_video', old('audience_network_positions', [])) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600">
                                <span class="text-sm">Rewarded Video</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- META CONNECTION --}}
            <div class="mt-8 border-t border-gray-200 pt-6">
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <div class="flex items-start space-x-3">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div class="flex-1">
                            <h3 class="font-semibold text-blue-800">Meta API Integration</h3>
                            <p class="text-sm text-blue-700 mt-1">
                                This ad set will be created in Meta Ads Manager under the selected campaign.
                                @if(isset($adAccount))
                                    Using account: <span class="font-mono">{{ $adAccount->ad_account_id }}</span>
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
                   class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition font-medium">
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
        // Initialize Tom Select for multi-selects with proper sync
        const campaignSelect = new TomSelect("#campaign-select", {
            plugins: ['dropdown_input'],
            maxOptions: 100
        });

        // FIXED: Using 'countries[]' to match controller
        const countrySelect = new TomSelect("#country-select", {
            plugins: ['remove_button', 'dropdown_input'],
            maxItems: null,
            placeholder: 'Select countries to target',
            onInitialize: function() {
                this.sync();
            },
            onChange: function(value) {
                this.sync();
            }
        });

        // Excluded countries select
        const excludedCountrySelect = new TomSelect("#excluded-countries-select", {
            plugins: ['remove_button', 'dropdown_input'],
            maxItems: null,
            placeholder: 'Select countries to exclude',
            onInitialize: function() {
                this.sync();
            }
        });

        const genderSelect = new TomSelect("#gender-select", {
            plugins: ['remove_button'],
            maxItems: 2,
            placeholder: 'Select genders',
            onInitialize: function() {
                this.sync();
            }
        });

        // FIXED: Using 'languages[]' to match controller
        const languageSelect = new TomSelect("#language-select", {
            plugins: ['remove_button'],
            maxItems: null,
            placeholder: 'Select languages',
            onInitialize: function() {
                this.sync();
            }
        });

        // FIXED: Using 'interests[]' to match controller
       const interestSelect = new TomSelect("#interest-select", {
    plugins: ['remove_button'],
    maxItems: 10,
    valueField: 'id',
    labelField: 'name',
    searchField: 'name',
    create: false,

    load: function(query, callback) {

        if (!query.length) return callback();

        fetch(`/admin/meta/interests?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(json => {

                if (!json.data) return callback();

                callback(json.data.map(item => ({
                    id: item.id,
                    name: item.name
                })));

            })
            .catch(() => callback());
    }
});

        const connectionSelect = new TomSelect("#connection-select", {
            plugins: ['remove_button'],
            maxItems: null,
            placeholder: 'Select connections',
            onInitialize: function() {
                this.sync();
            }
        });

        const deviceSelect = new TomSelect("#device-select", {
            plugins: ['remove_button'],
            maxItems: null,
            placeholder: 'Select devices',
            onInitialize: function() {
                this.sync();
            }
        });

        const platformSelect = new TomSelect("#platform-select", {
            plugins: ['remove_button'],
            maxItems: null,
            placeholder: 'Select platforms',
            onInitialize: function() {
                this.sync();
            }
        });

        // Ensure all Tom Select instances sync before form submission
        document.getElementById('adsetForm').addEventListener('submit', function(e) {
            [countrySelect, genderSelect, languageSelect, interestSelect, connectionSelect, deviceSelect, platformSelect, excludedCountrySelect].forEach(select => {
                if (select && select.sync) {
                    select.sync();
                }
            });
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

        // Bid strategy toggle
        const bidStrategy = document.getElementById('bid-strategy');
        const bidAmountField = document.getElementById('bid-amount-field');
        
        if (bidStrategy) {
            bidStrategy.addEventListener('change', function() {
                if (this.value === 'LOWEST_COST_WITH_BID_CAP' || this.value === 'COST_CAP') {
                    bidAmountField.classList.remove('hidden');
                } else {
                    bidAmountField.classList.add('hidden');
                }
            });
            
            // Trigger on load if needed
            if (bidStrategy.value === 'LOWEST_COST_WITH_BID_CAP' || bidStrategy.value === 'COST_CAP') {
                bidAmountField.classList.remove('hidden');
            }
        }

        // Promoted object toggle
        const promotedObjectType = document.getElementById('promoted-object-type');
        const promotedObjectPageField = document.getElementById('promoted-object-page-field');
        
        if (promotedObjectType) {
            promotedObjectType.addEventListener('change', function() {
                if (this.value === 'instagram') {
                    promotedObjectPageField.classList.remove('hidden');
                } else {
                    promotedObjectPageField.classList.add('hidden');
                }
            });
        }

        // Location exclusion toggle
        const excludeLocations = document.querySelector('input[name="exclude_locations"]');
        const excludedCountriesField = document.getElementById('excluded-countries-field');
        
        if (excludeLocations) {
            excludeLocations.addEventListener('change', function() {
                if (this.checked) {
                    excludedCountriesField.classList.add('hidden');
                } else {
                    excludedCountriesField.classList.remove('hidden');
                }
            });
        }

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
        const flexibleSpec = document.getElementById('flexible-spec');
        const jsonError = document.getElementById('json-error');
        
        if (flexibleSpec) {
            flexibleSpec.addEventListener('input', function() {
                try {
                    if (this.value.trim()) {
                        JSON.parse(this.value);
                        this.classList.remove('border-red-500');
                        jsonError.classList.add('hidden');
                    } else {
                        this.classList.remove('border-red-500');
                        jsonError.classList.add('hidden');
                    }
                } catch (e) {
                    this.classList.add('border-red-500');
                    jsonError.classList.remove('hidden');
                }
            });
        }
    });
</script>

<style>
    /* Meta Ads UI X styles */
    .ts-wrapper {
        border: 0 !important;
    }
    
    .ts-control {
        @apply border border-gray-300 rounded-xl px-4 py-3 bg-white min-h-[3.5rem] shadow-sm;
    }
    
    .ts-dropdown {
        @apply rounded-xl shadow-lg border border-gray-200;
    }
    
    .ts-dropdown .active {
        @apply bg-blue-50;
    }
    
    .ts-wrapper.multi .ts-control > div {
        @apply bg-blue-100 text-blue-800 rounded-lg px-2 py-1 text-sm;
    }
    
    .ts-wrapper.multi .ts-control > div .remove {
        @apply text-blue-600 hover:text-blue-800 ml-1;
    }
    
    /* Form styles */
    .meta-card {
        @apply bg-white rounded-2xl shadow-sm border border-gray-200;
    }
    
    .meta-input {
        @apply border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 rounded-xl;
    }
    
    .meta-label {
        @apply text-sm font-medium text-gray-700 mb-1;
    }
    
    .meta-helper {
        @apply text-xs text-gray-500 mt-1;
    }
    
    .meta-section {
        @apply border-b border-gray-200 pb-6 mb-6;
    }
    
    .meta-section-title {
        @apply text-lg font-semibold text-gray-900 mb-4;
    }
</style>
@endsection