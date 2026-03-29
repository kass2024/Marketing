@extends('layouts.admin')

@section('title', 'Create ad set')

@section('content')

<link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">

<div class="mx-auto max-w-6xl space-y-8">

<div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Create ad set</h1>
        <p class="mt-1 text-sm text-slate-600">Meta validated audience configuration.</p>
    </div>
    <a href="{{ route('admin.campaigns.index') }}" class="inline-flex shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-xander-navy/25 hover:text-xander-navy">Campaigns</a>
</div>

{{-- ERRORS --}}
@if($errors->any())

<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">

<ul class="list-disc ml-6">

@foreach ($errors->all() as $error)

<li>{{ $error }}</li>

@endforeach

</ul>

</div>

@endif



<div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm ring-1 ring-slate-900/5 sm:p-8">

<form method="POST"
action="{{ route('admin.adsets.store') }}"
id="adsetForm">

@csrf


{{-- CAMPAIGN --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Campaign</label>

<select name="campaign_id"
id="campaign-select"
class="w-full rounded-xl border border-slate-200 px-4 py-3 shadow-sm focus:border-xander-navy focus:ring-2 focus:ring-xander-navy/20"
required>

<option value="">Select campaign</option>

@foreach($campaigns as $campaign)

<option
value="{{ $campaign->id }}"
data-objective="{{ $campaign->objective }}">

{{ $campaign->name }} ({{ $campaign->objective }})

</option>

@endforeach

</select>

<p id="objective-info"
class="mt-2 hidden text-xs text-xander-secondary"></p>

</div>



{{-- ADSET NAME --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Ad Set Name</label>

<input
type="text"
name="name"
class="w-full rounded-xl border border-slate-200 px-4 py-3 shadow-sm focus:border-xander-navy focus:ring-2 focus:ring-xander-navy/20"
required>

</div>



{{-- BUDGET --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Daily Budget ($)</label>

<input
type="number"
name="daily_budget"
value="10"
min="5"
class="w-full rounded-xl border border-slate-200 px-4 py-3 shadow-sm focus:border-xander-navy focus:ring-2 focus:ring-xander-navy/20"
required>

<p class="text-xs text-gray-500 mt-1">
Minimum recommended: $5/day
</p>

</div>



{{-- OPTIMIZATION --}}
<div class="mb-6">

<label class="font-semibold block mb-2">
Optimization Goal
</label>

<select
name="optimization_goal"
id="optimization-goal"
class="w-full rounded-xl border border-slate-200 px-4 py-3 shadow-sm focus:border-xander-navy focus:ring-2 focus:ring-xander-navy/20"
required>

<option value="LINK_CLICKS">Link Clicks</option>
<option value="LANDING_PAGE_VIEWS">Landing Page Views</option>
<option value="REACH">Reach</option>
<option value="IMPRESSIONS">Impressions</option>
<option value="LEAD_GENERATION">Lead Generation</option>
<option value="OFFSITE_CONVERSIONS">Conversions</option>
<option value="POST_ENGAGEMENT">Post Engagement</option>

</select>

</div>



{{-- BID STRATEGY --}}
<div class="mb-6">

<label class="font-semibold block mb-2">
Bid Strategy
</label>

<input type="hidden" name="bid_strategy" value="LOWEST_COST_WITHOUT_CAP">

<div class="w-full border rounded-xl px-4 py-3 bg-slate-50 text-slate-700 text-sm">
Lowest cost without bid cap <span class="text-slate-500">(recommended — bid cap requires a bid amount in Meta)</span>
</div>

</div>



{{-- FACEBOOK PAGE --}}
<div class="mb-6">

<label class="font-semibold block mb-2">
Facebook Page
</label>

<select
name="page_id"
class="w-full rounded-xl border border-slate-200 px-4 py-3 shadow-sm focus:border-xander-navy focus:ring-2 focus:ring-xander-navy/20"
required>

<option value="">Select Page</option>

@foreach($pages as $page)

<option value="{{ $page['id'] }}">
{{ $page['name'] }}
</option>

@endforeach

</select>

</div>



{{-- AGE --}}
<div class="grid grid-cols-2 gap-4 mb-6">

<div>

<label class="font-semibold block mb-2">
Min Age
</label>

<input
type="number"
name="age_min"
value="18"
min="18"
max="65"
class="w-full border rounded-xl px-4 py-3">

</div>

<div>

<label class="font-semibold block mb-2">
Max Age
</label>

<input
type="number"
name="age_max"
value="65"
min="18"
max="65"
class="w-full border rounded-xl px-4 py-3">

</div>

</div>



{{-- GENDER --}}
<div class="mb-6">

<label class="font-semibold block mb-2">
Gender
</label>

<select
name="genders[]"
multiple
id="gender-select"
class="w-full border rounded-xl px-4 py-3">

<option value="1">Male</option>
<option value="2">Female</option>

</select>

</div>



{{-- COUNTRIES --}}
<div class="mb-6">

<label class="font-semibold block mb-2">
Countries
</label>

<select
name="countries[]"
multiple
id="country-select"
class="w-full rounded-xl border border-slate-200 px-4 py-3 shadow-sm focus:border-xander-navy focus:ring-2 focus:ring-xander-navy/20"
required>

@foreach($countries as $code => $country)

<option value="{{ $code }}">
{{ $country }}
</option>

@endforeach

</select>

</div>



{{-- LANGUAGES --}}
<div class="mb-6">

<label class="font-semibold block mb-2">
Languages
</label>

<select
name="languages[]"
multiple
id="language-select"
class="w-full border rounded-xl px-4 py-3">

@foreach($languages as $id => $language)

<option value="{{ $id }}">
{{ $language }}
</option>

@endforeach

</select>

</div>



{{-- INTERESTS --}}
<div class="mb-6">

<label class="font-semibold block mb-2">
Interest Targeting
</label>

<select
name="interests[]"
id="interest-select"
multiple
class="w-full border rounded-xl px-4 py-3"></select>

</div>



{{-- PLACEMENT STRATEGY --}}
<div class="mb-6">

<label class="font-semibold block mb-2">
Placement Strategy
</label>

<select
name="placement_type"
id="placement-type"
class="w-full rounded-xl border border-slate-200 px-4 py-3 shadow-sm focus:border-xander-navy focus:ring-2 focus:ring-xander-navy/20"
required>

<option value="automatic">
Automatic (Recommended)
</option>

<option value="manual">
Manual Placements
</option>

</select>

</div>



{{-- PLATFORMS --}}
<div class="mb-6 hidden"
id="platform-section">

<label class="font-semibold block mb-2">
Publisher Platforms
</label>

<select
name="publisher_platforms[]"
multiple
id="platform-select"
class="w-full rounded-xl border border-slate-200 px-4 py-3 shadow-sm focus:border-xander-navy focus:ring-2 focus:ring-xander-navy/20">

<option value="facebook">Facebook</option>
<option value="instagram">Instagram</option>
<option value="messenger">Messenger</option>
<option value="audience_network">Audience Network</option>

</select>

</div>



<div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
    <a href="{{ route('admin.adsets.index') }}" class="text-center text-sm font-semibold text-slate-600 transition hover:text-xander-navy sm:text-left">Cancel</a>
    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-xander-navy px-8 py-3 font-semibold text-white shadow-sm transition hover:bg-xander-secondary sm:w-auto">
        Create ad set
    </button>
</div>

</form>

</div>

</div>



<script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>

<script>

new TomSelect("#country-select",{plugins:['remove_button']});
new TomSelect("#gender-select",{plugins:['remove_button']});
new TomSelect("#language-select",{plugins:['remove_button']});
new TomSelect("#platform-select",{plugins:['remove_button']});



let interestSelect = new TomSelect("#interest-select",{

plugins:['remove_button'],
valueField:'id',
labelField:'name',
searchField:'name',

load:function(query,callback){

if(query.length < 2) return callback();

fetch("/admin/meta/interests?q="+query)
.then(res=>res.json())
.then(data=>callback(data.data ?? []))
.catch(()=>callback());

}

});



const rules = {

TRAFFIC: "LINK_CLICKS",
OUTCOME_TRAFFIC: "LINK_CLICKS",
AWARENESS: "REACH",
OUTCOME_AWARENESS: "REACH",
ENGAGEMENT: "POST_ENGAGEMENT",
OUTCOME_ENGAGEMENT: "POST_ENGAGEMENT",
LEADS: "LEAD_GENERATION",
OUTCOME_LEADS: "LEAD_GENERATION",
SALES: "OFFSITE_CONVERSIONS",
OUTCOME_SALES: "OFFSITE_CONVERSIONS"

};



document.getElementById("campaign-select")
.addEventListener("change",function(){

let obj = this.selectedOptions[0].dataset.objective;

let goal = rules[obj] ?? "LINK_CLICKS";

document.getElementById("optimization-goal").value = goal;

document.getElementById("objective-info").classList.remove("hidden");

document.getElementById("objective-info").innerText =
"Optimization automatically configured for "+obj;

});



document.getElementById("placement-type")
.addEventListener("change",function(){

let section=document.getElementById("platform-section");

if(this.value==="manual"){
section.classList.remove("hidden");
}else{
section.classList.add("hidden");
}

});



document.getElementById("adsetForm")
.addEventListener("submit",function(e){

let min=parseInt(document.querySelector("[name='age_min']").value);
let max=parseInt(document.querySelector("[name='age_max']").value);

if(min > max){

e.preventDefault();

alert("Minimum age cannot be greater than maximum age");

}

});

</script>

@endsection