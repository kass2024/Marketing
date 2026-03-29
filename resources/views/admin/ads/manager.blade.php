@extends('layouts.admin')

@section('title','Ads Manager')

@section('content')

<div class="max-w-7xl mx-auto space-y-6">

{{-- HEADER --}}
<div class="flex justify-between items-center flex-wrap gap-4">

<div>
<h1 class="text-2xl font-semibold text-gray-900">
Ads Manager
</h1>

<p class="text-sm text-gray-500">
Manage Campaigns, Ad Sets and Ads in one workspace.
</p>
</div>

<div class="flex gap-3 flex-wrap">

<a href="{{ route('admin.campaigns.create') }}"
class="bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700">
+ Campaign
</a>

<a id="createAdSetBtn"
class="bg-green-600 text-white px-4 py-2 rounded-lg shadow opacity-50 cursor-not-allowed pointer-events-none">
+ Ad Set
</a>

<a id="createAdBtn"
class="bg-purple-600 text-white px-4 py-2 rounded-lg shadow opacity-50 cursor-not-allowed pointer-events-none">
+ Ad
</a>

</div>

</div>



{{-- MAIN GRID --}}
<div class="grid grid-cols-3 gap-6">

<div class="col-span-2 space-y-6">


{{-- FILTER BAR --}}
<div class="flex justify-between items-center bg-white p-4 rounded-xl shadow flex-wrap gap-3">

<div class="flex gap-3">

<select class="border rounded-lg px-3 py-2 text-sm">
<option>All Campaigns</option>
<option>Active</option>
<option>Paused</option>
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



{{-- CAMPAIGNS TABLE --}}
<div class="bg-white rounded-xl shadow overflow-hidden">

<div class="p-4 border-b font-semibold">
Campaigns
</div>

<table class="w-full text-sm">

<thead class="bg-gray-50 text-gray-600">

<tr>
<th class="p-3 w-10"><input type="checkbox"></th>
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
class="border-t hover:bg-blue-50 cursor-pointer campaign-row"
data-id="{{ $campaign->id }}">

<td class="p-3">
<input type="checkbox" onclick="event.stopPropagation()">
</td>

<td class="font-medium">
{{ $campaign->name }}
</td>

<td>{{ $campaign->objective }}</td>

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
@endif">

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



{{-- ADSETS --}}
<div id="adsets-container"></div>



{{-- ADS --}}
<div id="ads-container"></div>


</div>



{{-- PREVIEW PANEL --}}
<div>

<div class="bg-white rounded-xl shadow p-4 sticky top-6">

<h2 class="font-semibold mb-4">
Ad Preview
</h2>

<div id="ad-preview">

<div class="text-gray-400 text-sm">
Select an Ad to preview
</div>

</div>

</div>

</div>


</div>


</div>



<script>

let selectedCampaign = null;
let selectedAdset = null;

const adsetsContainer = document.getElementById('adsets-container');
const adsContainer = document.getElementById('ads-container');
const preview = document.getElementById('ad-preview');

const adsetBtn = document.getElementById('createAdSetBtn');
const adBtn = document.getElementById('createAdBtn');



/*
|--------------------------------------------------------------------------
| CAMPAIGN CLICK
|--------------------------------------------------------------------------
*/

document.addEventListener('click', function(e){

const row = e.target.closest('.campaign-row');
if(!row) return;

document.querySelectorAll('.campaign-row')
.forEach(r=>r.classList.remove('bg-blue-100'));

row.classList.add('bg-blue-100');

selectedCampaign = row.dataset.id;

enableAdSetButton();
loadAdsets(selectedCampaign);

});



function enableAdSetButton(){

adsetBtn.classList.remove(
'opacity-50',
'cursor-not-allowed',
'pointer-events-none'
);

adsetBtn.href =
`/admin/campaigns/${selectedCampaign}/adsets/create`;

}



/*
|--------------------------------------------------------------------------
| LOAD ADSETS
|--------------------------------------------------------------------------
*/

function loadAdsets(campaignId){

adsetsContainer.innerHTML =
`<div class="p-6 text-gray-500">Loading Ad Sets...</div>`;

adsContainer.innerHTML = '';
preview.innerHTML = '';

fetch(`/admin/campaigns/${campaignId}/adsets`)
.then(res => res.json())
.then(renderAdsets);

}



function renderAdsets(adsets){

let html = `
<div class="bg-white rounded-xl shadow mt-6 overflow-hidden">

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

if(!adsets.length){

html += `
<tr>
<td colspan="3" class="p-4 text-gray-400 text-center">
No Ad Sets found
</td>
</tr>
`;

}

adsets.forEach(adset=>{

html += `
<tr class="border-t hover:bg-blue-50 cursor-pointer adset-row"
data-id="${adset.id}">

<td>${adset.name}</td>
<td>$${((adset.daily_budget || 0) / 100).toFixed(2)}</td>
<td>${adset.status}</td>

</tr>
`;

});

html += `</tbody></table></div>`;

adsetsContainer.innerHTML = html;

}



/*
|--------------------------------------------------------------------------
| ADSET CLICK
|--------------------------------------------------------------------------
*/

document.addEventListener('click', function(e){

const row = e.target.closest('.adset-row');
if(!row) return;

document.querySelectorAll('.adset-row')
.forEach(r=>r.classList.remove('bg-blue-100'));

row.classList.add('bg-blue-100');

selectedAdset = row.dataset.id;

enableAdButton();
loadAds(selectedAdset);

});



function enableAdButton(){

adBtn.classList.remove(
'opacity-50',
'cursor-not-allowed',
'pointer-events-none'
);

adBtn.href =
`/admin/adsets/${selectedAdset}/ads/create`;

}



/*
|--------------------------------------------------------------------------
| LOAD ADS
|--------------------------------------------------------------------------
*/

function loadAds(adsetId){

adsContainer.innerHTML =
`<div class="p-6 text-gray-500">Loading Ads...</div>`;

fetch(`/admin/adsets/${adsetId}/ads`)
.then(res=>res.json())
.then(renderAds);

}



function renderAds(ads){

let html = `
<div class="bg-white rounded-xl shadow mt-6 overflow-hidden">

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

if(!ads.length){

html += `
<tr>
<td colspan="5" class="p-4 text-gray-400 text-center">
No Ads found
</td>
</tr>
`;

}

ads.forEach(ad=>{

html += `
<tr class="border-t cursor-pointer ad-row"
data-id="${ad.id}">

<td>${ad.name}</td>
<td>${ad.status}</td>
<td>${ad.impressions ?? 0}</td>
<td>${ad.clicks ?? 0}</td>
<td>$${parseFloat(ad.spend ?? 0).toFixed(2)}</td>

</tr>
`;

});

html += `</tbody></table></div>`;

adsContainer.innerHTML = html;

}



/*
|--------------------------------------------------------------------------
| AD PREVIEW
|--------------------------------------------------------------------------
*/

document.addEventListener('click', function(e){

const row = e.target.closest('.ad-row');
if(!row) return;

const adId = row.dataset.id;

preview.innerHTML = `
<div class="text-gray-500 text-sm">
Loading preview...
</div>
`;

fetch(`/admin/ads/${adId}/preview`)
.then(res=>res.json())
.then(data=>{

preview.innerHTML = `
<div class="border rounded-lg overflow-hidden bg-white">

${data.image_url ? `<img src="${data.image_url}" class="w-full">` : ''}

<div class="p-4">

<h3 class="font-semibold mb-2">
${data.title || ''}
</h3>

<p class="text-sm text-gray-600 mb-3">
${data.body || ''}
</p>

${data.call_to_action ?
`<button class="bg-blue-600 text-white px-4 py-2 rounded text-sm">
${data.call_to_action}
</button>` : ''}

</div>

</div>
`;

});

});

</script>

@endsection