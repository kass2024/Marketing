@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto space-y-8">

{{-- ================= HEADER ================= --}}
<div class="flex justify-between items-center">

<div>
<h1 class="text-3xl font-bold text-gray-900">
Ads Manager
</h1>

<p class="text-sm text-gray-500 mt-1">
Manage Campaigns, Ad Sets and Ads in one workspace.
</p>
</div>

<div class="flex gap-3">

<a href="{{ route('admin.campaigns.create') }}"
class="bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700">
+ Campaign
</a>

<button
class="bg-green-600 text-white px-4 py-2 rounded-lg shadow opacity-70 cursor-not-allowed">
+ Ad Set
</button>

<button
class="bg-purple-600 text-white px-4 py-2 rounded-lg shadow opacity-70 cursor-not-allowed">
+ Ad
</button>

</div>

</div>



{{-- ================= FILTER BAR ================= --}}
<div class="flex items-center justify-between bg-white p-4 rounded-xl shadow">

<div class="flex gap-3">

<select class="border rounded-lg px-3 py-2 text-sm">
<option>All Campaigns</option>
<option>Active</option>
<option>Paused</option>
<option>Completed</option>
</select>

<select class="border rounded-lg px-3 py-2 text-sm">
<option>Last 30 Days</option>
<option>Last 7 Days</option>
<option>Today</option>
</select>

</div>

<div class="text-sm text-gray-500">
{{ $campaigns->count() }} Campaigns
</div>

</div>



{{-- ================= CAMPAIGNS ================= --}}
<div class="bg-white rounded-xl shadow overflow-hidden">

<div class="p-4 border-b font-semibold">
Campaigns
</div>

<table class="w-full text-sm">

<thead class="bg-gray-50 text-gray-600">

<tr>
<th class="p-3 w-10">
<input type="checkbox">
</th>

<th class="text-left">Campaign</th>
<th>Objective</th>
<th>Budget</th>
<th>Status</th>
<th>Spend</th>
<th>Clicks</th>
<th>CTR</th>
<th></th>
</tr>

</thead>

<tbody>

@foreach($campaigns as $campaign)

<tr
class="border-t hover:bg-gray-50 cursor-pointer campaign-row"
data-id="{{ $campaign->id }}"
>

<td class="p-3">
<input type="checkbox" onclick="event.stopPropagation()">
</td>

<td class="font-medium">
{{ $campaign->name }}
</td>

<td>
{{ $campaign->objective }}
</td>

<td>
${{ number_format(($campaign->daily_budget ?? 0) / 100,2) }}
</td>

<td>

<span class="px-2 py-1 text-xs rounded
@if($campaign->status == 'ACTIVE')
bg-green-100 text-green-700
@elseif($campaign->status == 'PAUSED')
bg-yellow-100 text-yellow-700
@else
bg-gray-100 text-gray-700
@endif
">

{{ $campaign->status }}

</span>

</td>

<td>${{ number_format($campaign->spend ?? 0,2) }}</td>
<td>{{ $campaign->clicks ?? 0 }}</td>
<td>{{ $campaign->ctr ?? '0%' }}</td>

<td>
<a href="{{ route('admin.campaigns.edit',$campaign->id) }}"
class="text-blue-600 hover:underline"
onclick="event.stopPropagation()">
Edit
</a>
</td>

</tr>

@endforeach

</tbody>

</table>

</div>



{{-- ================= ADSETS SECTION ================= --}}
<div id="adsets-container"></div>



{{-- ================= ADS SECTION ================= --}}
<div id="ads-container"></div>

</div>



{{-- ================= SCRIPT ================= --}}
<script>

const adsetsContainer = document.getElementById('adsets-container');
const adsContainer = document.getElementById('ads-container');


/*
|--------------------------------------------------------------------------
| Campaign Click
|--------------------------------------------------------------------------
*/

document.addEventListener('click', function(e){

const row = e.target.closest('.campaign-row');

if(!row) return;

const campaignId = row.dataset.id;

adsetsContainer.innerHTML =
`<div class="p-6 text-gray-500">Loading Ad Sets...</div>`;

adsContainer.innerHTML = '';

fetch(`/admin/campaigns/${campaignId}/adsets`)
.then(res => res.json())
.then(renderAdsets)
.catch(() => {
adsetsContainer.innerHTML =
`<div class="p-6 text-red-500">Failed to load Ad Sets</div>`;
});

});


/*
|--------------------------------------------------------------------------
| Render AdSets
|--------------------------------------------------------------------------
*/

function renderAdsets(adsets){

let html = `
<div class="bg-white rounded-xl shadow mt-8 overflow-hidden">

<div class="p-4 border-b font-semibold">
Ad Sets
</div>

<table class="w-full text-sm">

<thead class="bg-gray-50">
<tr>
<th>Name</th>
<th>Budget</th>
<th>Status</th>
</tr>
</thead>

<tbody>
`;

if(adsets.length === 0){

html += `
<tr>
<td colspan="3" class="p-4 text-gray-400 text-center">
No Ad Sets found
</td>
</tr>
`;

}

adsets.forEach(adset => {

html += `
<tr
class="border-t hover:bg-gray-50 cursor-pointer adset-row"
data-id="${adset.id}">

<td>${adset.name}</td>

<td>$${(adset.daily_budget / 100).toFixed(2)}</td>

<td>${adset.status}</td>

</tr>
`;

});

html += `
</tbody>
</table>
</div>
`;

adsetsContainer.innerHTML = html;

}



/*
|--------------------------------------------------------------------------
| AdSet Click
|--------------------------------------------------------------------------
*/

document.addEventListener('click', function(e){

const row = e.target.closest('.adset-row');

if(!row) return;

const adsetId = row.dataset.id;

adsContainer.innerHTML =
`<div class="p-6 text-gray-500">Loading Ads...</div>`;

fetch(`/admin/adsets/${adsetId}/ads`)
.then(res => res.json())
.then(renderAds)
.catch(() => {

adsContainer.innerHTML =
`<div class="p-6 text-red-500">Failed to load Ads</div>`;

});

});


/*
|--------------------------------------------------------------------------
| Render Ads
|--------------------------------------------------------------------------
*/

function renderAds(ads){

let html = `
<div class="bg-white rounded-xl shadow mt-8 overflow-hidden">

<div class="p-4 border-b font-semibold">
Ads
</div>

<table class="w-full text-sm">

<thead class="bg-gray-50">
<tr>
<th>Name</th>
<th>Status</th>
<th>Impressions</th>
<th>Clicks</th>
<th>Spend</th>
</tr>
</thead>

<tbody>
`;

if(ads.length === 0){

html += `
<tr>
<td colspan="5" class="p-4 text-gray-400 text-center">
No Ads found
</td>
</tr>
`;

}

ads.forEach(ad => {

html += `
<tr class="border-t">

<td>${ad.name}</td>

<td>${ad.status}</td>

<td>${ad.impressions ?? 0}</td>

<td>${ad.clicks ?? 0}</td>

<td>$${parseFloat(ad.spend ?? 0).toFixed(2)}</td>

</tr>
`;

});

html += `
</tbody>
</table>
</div>
`;

adsContainer.innerHTML = html;

}

</script>

@endsection