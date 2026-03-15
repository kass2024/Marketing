@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto py-10 space-y-8">

@if($errors->any())
<div class="bg-red-100 border border-red-200 text-red-700 p-4 rounded-lg">
<ul class="list-disc pl-5 space-y-1">
@foreach($errors->all() as $error)
<li>{{ $error }}</li>
@endforeach
</ul>
</div>
@endif


{{-- =========================================================
HEADER
========================================================= --}}
<div class="flex items-center justify-between flex-wrap gap-4">

<div>
<h1 class="text-2xl font-bold text-gray-900">Ads Manager</h1>
<p class="text-sm text-gray-500">
Create, publish and monitor ad delivery performance.
</p>
</div>

<div class="flex gap-3">

<a href="{{ route('admin.adsets.index') }}"
class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800">
Back to Ad Sets
</a>

<a href="{{ route('admin.ads.create') }}"
class="inline-flex items-center gap-2 bg-blue-600 text-white px-5 py-2 rounded-lg shadow hover:bg-blue-700">
<span>＋</span>
<span>Create Ad</span>
</a>

</div>
</div>

{{-- =========================================================
ALERTS
========================================================= --}}
@if(session('success'))
<div class="bg-green-100 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
{{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
{{ session('error') }}
</div>
@endif


{{-- =========================================================
METRICS
========================================================= --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-5">

<div class="bg-white p-6 rounded-xl shadow border">
<p class="text-sm text-gray-500">Total Ads</p>
<p class="text-2xl font-bold" id="metric-total-ads">
{{ $ads->total() }}
</p>
</div>

<div class="bg-white p-6 rounded-xl shadow border">
<p class="text-sm text-gray-500">Active Ads</p>
<p class="text-2xl font-bold text-green-600" id="metric-active-ads">
{{ $ads->getCollection()->where('status','ACTIVE')->count() }}
</p>
</div>

<div class="bg-white p-6 rounded-xl shadow border">
<p class="text-sm text-gray-500">Total Spend</p>
<p class="text-2xl font-bold text-blue-600" id="metric-total-spend">
${{ number_format($ads->getCollection()->sum('spend'),2) }}
</p>
</div>

<div class="bg-white p-6 rounded-xl shadow border">
<p class="text-sm text-gray-500">Total Clicks</p>
<p class="text-2xl font-bold text-purple-600" id="metric-total-clicks">
{{ number_format($ads->getCollection()->sum('clicks')) }}
</p>
</div>

</div>


{{-- =========================================================
TABLE
========================================================= --}}
<div class="bg-white rounded-xl shadow overflow-hidden">

<table class="min-w-full text-sm">

<thead class="bg-gray-50 text-gray-600">
<tr>
<th class="px-6 py-3 text-left">Ad</th>
<th class="px-6 py-3 text-left">Creative</th>
<th class="px-6 py-3 text-left">AdSet</th>
<th class="px-6 py-3 text-left">Delivery</th>
<th class="px-6 py-3 text-left">Impressions</th>
<th class="px-6 py-3 text-left">Clicks</th>
<th class="px-6 py-3 text-left">CTR</th>
<th class="px-6 py-3 text-left">Spend</th>
<th class="px-6 py-3 text-left">Today</th>
<th class="px-6 py-3 text-left">Budget</th>
<th class="px-6 py-3 text-left">Reason</th>
<th class="px-6 py-3 text-right">Actions</th>
</tr>
</thead>

<tbody class="divide-y">

@forelse($ads as $ad)

<tr class="hover:bg-gray-50 transition">


{{-- AD --}}
<td class="px-6 py-4">
<div class="font-medium text-gray-900">{{ $ad->name }}</div>
@if($ad->meta_ad_id)
<div class="text-xs text-gray-400 mt-1">
Meta ID: {{ $ad->meta_ad_id }}
</div>
@endif
</td>


{{-- CREATIVE --}}
<td class="px-6 py-4">

@if($ad->creative)
<div class="flex items-center gap-3">

@if($ad->creative->image_url)
<img src="{{ $ad->creative->image_url }}"
class="w-10 h-10 rounded object-cover border">
@endif

<div class="text-sm font-medium">
{{ $ad->creative->name }}
</div>

</div>
@endif

</td>


{{-- ADSET --}}
<td class="px-6 py-4">
{{ $ad->adSet?->name ?? '-' }}
</td>


{{-- STATUS --}}
<td class="px-6 py-4" id="status-{{ $ad->id }}">

@switch($ad->status)

@case('ACTIVE')
<span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs">Active</span>
@break

@case('PAUSED')
<span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs">Paused</span>
@break

@case('PENDING_REVIEW')
<span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs">In Review</span>
@break

@case('DISAPPROVED')
<span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs">Disapproved</span>
@break

@default
<span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">Draft</span>

@endswitch

</td>


{{-- IMPRESSIONS --}}
<td class="px-6 py-4" id="imp-{{ $ad->id }}">
{{ number_format($ad->impressions ?? 0) }}
</td>


{{-- CLICKS --}}
<td class="px-6 py-4" id="clk-{{ $ad->id }}">
{{ number_format($ad->clicks ?? 0) }}
</td>


{{-- CTR --}}
<td class="px-6 py-4">
@php $ctr = $ad->ctr ?? 0; @endphp
<span class="font-semibold
@if($ctr > 3) text-green-600
@elseif($ctr > 1) text-yellow-600
@else text-gray-600
@endif">
{{ number_format($ctr,2) }}%
</span>
</td>


{{-- SPEND --}}
<td class="px-6 py-4 font-semibold text-gray-800" id="spend-{{ $ad->id }}">
${{ number_format($ad->spend ?? 0,2) }}
</td>


{{-- TODAY --}}
<td class="px-6 py-4 font-semibold text-blue-600" id="today-{{ $ad->id }}">
${{ number_format($ad->daily_spend ?? 0,2) }}
</td>


{{-- BUDGET --}}
<td class="px-6 py-4 text-gray-700 font-medium">
${{ number_format($ad->daily_budget ?? 0,2) }}
</td>


{{-- REASON --}}
<td class="px-6 py-4">

@if($ad->pause_reason === 'budget_limit')
<span class="inline-block bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-medium">
Budget Limit
</span>

@elseif($ad->pause_reason === 'manual')
<span class="inline-block bg-gray-200 text-gray-700 px-2 py-1 rounded text-xs font-medium">
Manual
</span>

@else
<span class="text-gray-400 text-xs">—</span>
@endif

</td>


{{-- ACTIONS --}}
<td class="px-6 py-4">
<div class="flex flex-wrap gap-2 justify-end text-xs font-medium">

<a href="{{ route('admin.ads.preview',$ad) }}"
class="px-2 py-1 bg-indigo-50 text-indigo-600 rounded hover:bg-indigo-100">
Preview
</a>

<a href="{{ route('admin.ads.edit',$ad) }}"
class="px-2 py-1 bg-blue-50 text-blue-600 rounded hover:bg-blue-100">
Edit
</a>

@if($ad->status !== 'ACTIVE')
<form method="POST" action="{{ route('admin.ads.publish',$ad->id) }}">
@csrf
<button type="submit"
class="px-2 py-1 bg-green-50 text-green-600 rounded hover:bg-green-100">
Publish
</button>
</form>
@endif

@if($ad->status === 'ACTIVE')
<form method="POST" action="{{ route('admin.ads.pause',$ad->id) }}">
@csrf
@method('PATCH')
<button type="submit"
class="px-2 py-1 bg-yellow-50 text-yellow-700 rounded hover:bg-yellow-100">
Pause
</button>
</form>
@endif

<form method="POST" action="{{ route('admin.ads.sync',$ad->id) }}">
@csrf
<button class="px-2 py-1 bg-gray-50 text-gray-600 rounded hover:bg-gray-100">
Sync
</button>
</form>

<form method="POST" action="{{ route('admin.ads.duplicate',$ad->id) }}">
@csrf
<button class="px-2 py-1 bg-purple-50 text-purple-600 rounded hover:bg-purple-100">
Duplicate
</button>
</form>

<form method="POST" action="{{ route('admin.ads.destroy',$ad->id) }}">
@csrf
@method('DELETE')
<button onclick="return confirm('Delete this ad?')"
class="px-2 py-1 bg-red-50 text-red-600 rounded hover:bg-red-100">
Delete
</button>
</form>

</div>
</td>

</tr>

@empty

<tr>
<td colspan="12" class="text-center py-16 text-gray-400">
<div class="flex flex-col items-center gap-4">
<p class="text-lg">No ads created yet</p>
<a href="{{ route('admin.ads.create') }}"
class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700">
Create Your First Ad
</a>
</div>
</td>
</tr>

@endforelse

</tbody>
</table>


@if($ads->hasPages())
<div class="p-4 border-t">
{{ $ads->links() }}
</div>
@endif

</div>
</div>
{{-- =========================================================
LIVE AJAX DASHBOARD UPDATE
========================================================= --}}
<script>

(function(){

let running = false;

/* =============================
   FORMATTERS
============================= */

function money(v){
    return '$' + Number(v || 0).toFixed(2);
}

function number(v){
    return Number(v || 0).toLocaleString();
}


/* =============================
   STATUS BADGE
============================= */

function renderStatus(status){

    switch(status){

        case 'ACTIVE':
            return '<span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs">Active</span>';

        case 'PAUSED':
            return '<span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs">Paused</span>';

        case 'PENDING_REVIEW':
            return '<span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs">In Review</span>';

        case 'DISAPPROVED':
            return '<span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs">Disapproved</span>';

        default:
            return '<span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">Draft</span>';

    }

}


/* =============================
   MAIN REFRESH FUNCTION
============================= */

async function refreshAdsDashboard(){

    if(running) return;

    running = true;

    try{

        const response = await fetch(
            "{{ route('admin.ads.live') }}?t="+Date.now(),
            {
                headers:{
                    'X-Requested-With':'XMLHttpRequest'
                }
            }
        );

        if(!response.ok){
            throw new Error('Network response failed');
        }

        const data = await response.json();

        /* =============================
           UPDATE METRICS
        ============================= */

        const totalAds = document.getElementById('metric-total-ads');
        const activeAds = document.getElementById('metric-active-ads');
        const totalSpend = document.getElementById('metric-total-spend');
        const totalClicks = document.getElementById('metric-total-clicks');

        if(totalAds) totalAds.textContent = number(data.metrics.total_ads);
        if(activeAds) activeAds.textContent = number(data.metrics.active_ads);
        if(totalSpend) totalSpend.textContent = money(data.metrics.total_spend);
        if(totalClicks) totalClicks.textContent = number(data.metrics.total_clicks);


        /* =============================
           UPDATE TABLE ROWS
        ============================= */

        data.ads.forEach(ad => {

            const imp = document.getElementById('imp-'+ad.id);
            const clk = document.getElementById('clk-'+ad.id);
            const spn = document.getElementById('spend-'+ad.id);
            const tdy = document.getElementById('today-'+ad.id);
            const sts = document.getElementById('status-'+ad.id);

            if(imp) imp.textContent = number(ad.impressions);
            if(clk) clk.textContent = number(ad.clicks);
            if(spn) spn.textContent = money(ad.spend);
            if(tdy) tdy.textContent = money(ad.daily_spend);

            if(sts){
                sts.innerHTML = renderStatus(ad.status);
            }

        });

        console.log('Ads dashboard refreshed', data);

    }
    catch(e){

        console.warn('Live dashboard update failed', e);

    }

    running = false;

}


/* =============================
   START
============================= */

refreshAdsDashboard();


/* =============================
   AUTO REFRESH (5s)
============================= */

setInterval(refreshAdsDashboard, 5000);


})();

</script>
@endsection