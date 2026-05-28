@extends('layouts.admin')

@section('title', 'Ads')

@section('content')

<div class="mx-auto max-w-[1600px] space-y-6 sm:space-y-8">

@if($errors->any())
<div class="bg-red-100 border border-red-200 text-red-700 p-4 rounded-lg">
<ul class="list-disc pl-5 space-y-1">
@foreach($errors->all() as $error)
<li>{{ $error }}</li>
@endforeach
</ul>
</div>
@endif


{{-- HEADER --}}
<div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Ads Manager</h1>
        <p class="mt-1 text-sm text-slate-600">
            Create, publish and monitor ad delivery performance.
        </p>
        <p class="mt-2 inline-flex items-center gap-2 text-xs text-slate-500">
            <span id="live-indicator" class="inline-flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse" aria-hidden="true"></span>
            <span id="live-status">Live from Meta — updating…</span>
        </p>
    </div>
    <div class="flex flex-shrink-0 flex-wrap items-center gap-2 sm:gap-3">
        <a
            href="{{ route('admin.adsets.index') }}"
            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-xander-navy/25 hover:bg-slate-50 hover:text-xander-navy"
        >
            Ad sets
        </a>
        <form method="POST" action="{{ route('admin.ads.enable-instagram-all') }}" class="m-0" onsubmit="return confirm('Update ALL existing campaigns, ad sets, creatives, and ads on Meta for Instagram delivery?');">
            @csrf
            <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-fuchsia-200 bg-fuchsia-50 px-4 py-2.5 text-sm font-semibold text-fuchsia-900 shadow-sm transition hover:bg-fuchsia-100">
                Enable IG (all existing)
            </button>
        </form>
        <a
            href="{{ route('admin.ads.create') }}"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-xander-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-xander-secondary"
        >
            <span class="text-lg leading-none">+</span>
            Create ad
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


{{-- METRICS --}}
<div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
    <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total ads</p>
        <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900" id="metric-total-ads">{{ $metrics['total_ads'] }}</p>
    </div>
    <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Active</p>
        <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-600" id="metric-active-ads">{{ $metrics['active_ads'] }}</p>
    </div>
    <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total spend</p>
        <p class="mt-1 text-2xl font-bold tabular-nums text-xander-navy" id="metric-total-spend">${{ number_format($metrics['total_spend'], 2) }}</p>
    </div>
    <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Clicks</p>
        <p class="mt-1 text-2xl font-bold tabular-nums text-slate-800" id="metric-total-clicks">{{ number_format($metrics['total_clicks']) }}</p>
    </div>
</div>


{{-- TABLE: horizontal scroll + sticky Actions column --}}
<div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/5">
    <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
        <table class="w-full min-w-[1180px] border-collapse text-left text-sm text-slate-700">

<thead>
<tr class="border-b border-slate-200 bg-slate-50/95 text-xs font-semibold uppercase tracking-wide text-slate-500">
<th class="whitespace-nowrap px-4 py-3 lg:px-5">Ad</th>
<th class="whitespace-nowrap px-4 py-3 lg:px-5">Creative</th>
<th class="min-w-[8rem] whitespace-nowrap px-4 py-3 lg:px-5">Ad set</th>
<th class="whitespace-nowrap px-4 py-3 lg:px-5">Delivery</th>
<th class="min-w-[9rem] whitespace-nowrap px-4 py-3 lg:px-5">Platforms</th>
<th class="whitespace-nowrap px-4 py-3 text-right tabular-nums lg:px-5">Impr.</th>
<th class="whitespace-nowrap px-4 py-3 text-right tabular-nums lg:px-5">Clicks</th>
<th class="whitespace-nowrap px-4 py-3 text-right tabular-nums lg:px-5">CTR</th>
<th class="whitespace-nowrap px-4 py-3 text-right tabular-nums lg:px-5">Spend</th>
<th class="whitespace-nowrap px-4 py-3 text-right tabular-nums lg:px-5">Today</th>
<th class="whitespace-nowrap px-4 py-3 text-right tabular-nums lg:px-5">Budget</th>
<th class="min-w-[6rem] whitespace-nowrap px-4 py-3 lg:px-5">Reason</th>
<th class="sticky right-0 z-20 min-w-[10.5rem] whitespace-nowrap border-l border-slate-200 bg-slate-50/95 px-4 py-3 text-right shadow-[-12px_0_24px_-12px_rgba(15,23,42,0.12)] backdrop-blur-sm lg:min-w-[11rem] lg:px-5">Actions</th>
</tr>
</thead>

<tbody class="divide-y divide-slate-100">

@forelse($ads as $ad)

<tr class="group transition-colors hover:bg-slate-50/80">


{{-- AD --}}
<td class="max-w-[14rem] px-4 py-3 align-top lg:max-w-[18rem] lg:px-5">
    <div class="truncate font-medium text-slate-900" title="{{ $ad->name }}">{{ $ad->name }}</div>
    @if($ad->meta_ad_id)
        <div class="mt-0.5 truncate text-xs text-slate-400">ID {{ $ad->meta_ad_id }}</div>
    @endif
</td>

{{-- CREATIVE --}}
<td class="px-4 py-3 align-top lg:px-5">
    @if($ad->creative)
        <div class="flex min-w-0 max-w-[12rem] items-center gap-2 sm:max-w-[14rem]">
            @if($ad->creative->image_url)
                <div class="relative h-10 w-10 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                    <img
                        src="{{ $ad->creative->image_url }}"
                        alt=""
                        class="h-full w-full object-cover"
                        loading="lazy"
                        onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden')"
                    >
                    <div class="hidden h-full w-full items-center justify-center bg-slate-200 text-[10px] font-medium text-slate-500" aria-hidden="true">—</div>
                </div>
            @else
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50 text-xs text-slate-400" title="No image">N/A</div>
            @endif
            <span class="min-w-0 truncate text-sm font-medium text-slate-800" title="{{ $ad->creative->name }}">{{ $ad->creative->name }}</span>
        </div>
    @else
        <span class="text-slate-400">—</span>
    @endif
</td>

{{-- ADSET --}}
<td class="max-w-[10rem] px-4 py-3 align-top lg:px-5">
    <span class="line-clamp-2 text-slate-700" title="{{ $ad->adSet?->name }}">{{ $ad->adSet?->name ?? '—' }}</span>
</td>

{{-- STATUS --}}
<td class="whitespace-nowrap px-4 py-3 align-top lg:px-5" id="status-{{ $ad->id }}">

@switch($ad->status)

@case('ACTIVE')
<span class="inline-flex rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-600/15">Active</span>
@break

@case('PAUSED')
<span class="inline-flex rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-600/15">Paused</span>
@break

@case('PENDING_REVIEW')
<span class="inline-flex rounded-md bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-800 ring-1 ring-sky-600/15">In review</span>
@break

@case('DISAPPROVED')
<span class="inline-flex rounded-md bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-800 ring-1 ring-red-600/15">Disapproved</span>
@break

@default
<span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-400/20">Draft</span>

@endswitch

</td>

{{-- PLATFORMS --}}
<td class="min-w-[9rem] px-4 py-3 align-top lg:px-5" id="platforms-{{ $ad->id }}">
@php
    $placementDelivery = is_array($ad->placement_delivery ?? null) ? $ad->placement_delivery : [];
    $igImp = (int) ($placementDelivery['instagram']['impressions'] ?? 0);
    $fbImp = (int) ($placementDelivery['facebook']['impressions'] ?? 0);
    $igClicks = (int) ($placementDelivery['instagram']['clicks'] ?? 0);
    $targetLabels = $ad->adSet?->placementTargetLabels() ?? [];
    $targetsIg = $ad->adSet?->targetsInstagram() ?? false;
@endphp
    <div class="space-y-1">
        @if(count($targetLabels))
            <div class="text-[11px] text-slate-500" title="Ad set placement settings">
                Target: {{ implode(', ', $targetLabels) }}
            </div>
        @endif
        @if($igImp > 0)
            <span class="inline-flex rounded-md bg-fuchsia-50 px-2 py-0.5 text-xs font-semibold text-fuchsia-800 ring-1 ring-fuchsia-600/15">
                IG live · {{ number_format($igImp) }} impr.
            </span>
        @elseif($fbImp > 0 && $targetsIg)
            <span class="inline-flex rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-600/15">
                FB only · IG pending
            </span>
            @if($ad->meta_ad_id)
            <form method="POST" action="{{ route('admin.ads.enable-instagram', $ad) }}" class="m-0">
                @csrf
                <button type="submit" class="text-[11px] font-semibold text-fuchsia-700 underline">Enable IG</button>
            </form>
            @endif
        @elseif($fbImp > 0)
            <span class="inline-flex rounded-md bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-800 ring-1 ring-sky-600/15">Facebook only</span>
        @elseif($targetsIg)
            <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-400/20">IG targeted · no data yet</span>
        @else
            <span class="text-xs text-slate-400">—</span>
        @endif
    </div>
</td>


{{-- IMPRESSIONS --}}
<td class="whitespace-nowrap px-4 py-3 text-right tabular-nums lg:px-5" id="imp-{{ $ad->id }}">{{ number_format($ad->impressions ?? 0) }}</td>

{{-- CLICKS --}}
<td class="whitespace-nowrap px-4 py-3 text-right tabular-nums lg:px-5" id="clk-{{ $ad->id }}">{{ number_format($ad->clicks ?? 0) }}</td>

{{-- CTR --}}
<td class="whitespace-nowrap px-4 py-3 text-right tabular-nums lg:px-5" id="ctr-{{ $ad->id }}">
@php $ctr = $ad->ctr ?? 0; @endphp
<span class="font-semibold ctr-value @if($ctr > 3) text-emerald-600 @elseif($ctr > 1) text-amber-600 @else text-slate-600 @endif">{{ number_format($ctr,2) }}%</span>
</td>

{{-- SPEND --}}
<td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-slate-800 lg:px-5" id="spend-{{ $ad->id }}">${{ number_format($ad->spend ?? 0,2) }}</td>

{{-- TODAY --}}
<td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-xander-secondary lg:px-5" id="today-{{ $ad->id }}">${{ number_format($ad->displayDailySpend(), 2) }}</td>

{{-- BUDGET --}}
<td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums text-slate-700 lg:px-5">${{ number_format($ad->daily_budget ?? 0,2) }}</td>

{{-- REASON --}}
<td class="px-4 py-3 align-top lg:px-5">

@if($ad->pause_reason === 'budget_limit')
<span class="inline-flex rounded-md bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-800 ring-1 ring-red-600/15">Budget limit</span>
@elseif($ad->pause_reason === 'manual')
<span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-400/20">Manual</span>
@else
<span class="text-xs text-slate-400">—</span>
@endif
</td>

{{-- ACTIONS: sticky + vertical stack so nothing clips --}}
<td class="sticky right-0 z-10 min-w-[10.5rem] border-l border-slate-200 bg-white px-3 py-3 align-top shadow-[-12px_0_24px_-12px_rgba(15,23,42,0.1)] backdrop-blur-[2px] transition-colors group-hover:bg-slate-50/95 lg:min-w-[11rem] lg:px-4">
    <div class="flex flex-col items-stretch gap-1.5">
        <a href="{{ route('admin.ads.preview',$ad) }}" class="rounded-lg bg-slate-50 px-2.5 py-1.5 text-center text-xs font-semibold text-xander-navy ring-1 ring-slate-200/80 transition hover:bg-white hover:ring-xander-navy/25">Preview</a>
        <a href="{{ route('admin.ads.edit',$ad) }}" class="rounded-lg bg-slate-50 px-2.5 py-1.5 text-center text-xs font-semibold text-xander-secondary ring-1 ring-slate-200/80 transition hover:bg-white hover:ring-xander-navy/25">Edit</a>
        @if($ad->status !== 'ACTIVE')
            <form method="POST" action="{{ route('admin.ads.publish',$ad->id) }}" class="m-0">
                @csrf
                <button type="submit" class="w-full rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-600/15 transition hover:bg-emerald-100">
                    @if($ad->pause_reason === 'budget_limit')
                        Publish again
                    @else
                        Publish
                    @endif
                </button>
            </form>
        @endif
        @if($ad->status === 'ACTIVE')
            <form method="POST" action="{{ route('admin.ads.pause',$ad->id) }}" class="m-0">
                @csrf
                @method('PATCH')
                <button type="submit" class="w-full rounded-lg bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-900 ring-1 ring-amber-600/15 transition hover:bg-amber-100">Pause</button>
            </form>
        @endif
        @if($ad->meta_ad_id)
        <form method="POST" action="{{ route('admin.ads.enable-instagram', $ad) }}" class="m-0">
            @csrf
            <button type="submit" class="w-full rounded-lg bg-fuchsia-50 px-2.5 py-1.5 text-xs font-semibold text-fuchsia-800 ring-1 ring-fuchsia-600/15 transition hover:bg-fuchsia-100">Enable IG</button>
        </form>
        @endif
        <form method="POST" action="{{ route('admin.ads.sync',$ad->id) }}" class="m-0">
            @csrf
            <button type="submit" class="w-full rounded-lg bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200/80 transition hover:bg-white">Sync</button>
        </form>
        <form method="POST" action="{{ route('admin.ads.duplicate',$ad->id) }}" class="m-0">
            @csrf
            <button type="submit" class="w-full rounded-lg bg-violet-50 px-2.5 py-1.5 text-xs font-semibold text-violet-800 ring-1 ring-violet-600/15 transition hover:bg-violet-100">Duplicate</button>
        </form>
        <form method="POST" action="{{ route('admin.ads.destroy',$ad->id) }}" class="m-0" onsubmit="return confirm('Delete this ad?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="w-full rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 ring-1 ring-red-600/15 transition hover:bg-red-100">Delete</button>
        </form>
    </div>
</td>

</tr>

@empty

<tr>
<td colspan="13" class="px-4 py-16 text-center text-slate-500">
<div class="flex flex-col items-center gap-4">
<p class="text-lg font-medium text-slate-700">No ads yet</p>
<a href="{{ route('admin.ads.create') }}" class="inline-flex rounded-xl bg-xander-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-xander-secondary">Create your first ad</a>
</div>
</td>
</tr>

@endforelse

</tbody>
</table>
    </div>

@if($ads->hasPages())
    <div class="border-t border-slate-200 bg-slate-50/50 px-4 py-3">
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
const REFRESH_MS = 20000;
const FETCH_TIMEOUT_MS = 45000;

/* =============================
   FORMATTERS
============================= */

function money(v){
    return '$' + Number(v || 0).toFixed(2);
}

function number(v){
    return Number(v || 0).toLocaleString();
}

function setLiveStatus(ok, refreshedAt, warning){
    const status = document.getElementById('live-status');
    const dot = document.getElementById('live-indicator');

    if(!status || !dot){
        return;
    }

    if(ok){
        dot.className = 'inline-flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse';
        const time = refreshedAt ? new Date(refreshedAt).toLocaleTimeString() : new Date().toLocaleTimeString();
        const suffix = warning ? ' — ' + warning : '';
        status.textContent = 'Live from Meta — updated ' + time + ' (auto every ' + (REFRESH_MS / 1000) + 's)' + suffix;
    } else {
        dot.className = 'inline-flex h-2 w-2 rounded-full bg-amber-500 animate-pulse';
        status.textContent = 'Reconnecting to Meta live feed…';
    }
}


/* =============================
   STATUS BADGE
============================= */

function renderStatus(status){

    switch(status){

        case 'ACTIVE':
            return '<span class="inline-flex rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-600/15">Active</span>';

        case 'PAUSED':
            return '<span class="inline-flex rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-600/15">Paused</span>';

        case 'PENDING_REVIEW':
            return '<span class="inline-flex rounded-md bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-800 ring-1 ring-sky-600/15">In review</span>';

        case 'DISAPPROVED':
            return '<span class="inline-flex rounded-md bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-800 ring-1 ring-red-600/15">Disapproved</span>';

        default:
            return '<span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-400/20">Draft</span>';

    }

}


function renderCtr(ctr){
    const value = Number(ctr || 0);
    let color = 'text-slate-600';

    if(value > 3){
        color = 'text-emerald-600';
    } else if(value > 1){
        color = 'text-amber-600';
    }

    return '<span class="font-semibold ctr-value ' + color + '">' + value.toFixed(2) + '%</span>';
}

function renderPlatforms(placement){
    if(!placement){
        return '<span class="text-xs text-slate-400">—</span>';
    }

    const targets = Array.isArray(placement.targets) ? placement.targets : [];
    const targetLine = targets.length
        ? '<div class="text-[11px] text-slate-500">Target: ' + targets.join(', ') + '</div>'
        : '';

    const igImp = Number(placement.instagram_impressions || 0);
    const fbImp = Number(placement.facebook_impressions || 0);
    const targetsIg = !!placement.targets_instagram;

    let badge = '<span class="text-xs text-slate-400">—</span>';

    if(igImp > 0){
        badge = '<span class="inline-flex rounded-md bg-fuchsia-50 px-2 py-0.5 text-xs font-semibold text-fuchsia-800 ring-1 ring-fuchsia-600/15">IG live · ' + igImp.toLocaleString() + ' impr.</span>';
    } else if(fbImp > 0 && targetsIg){
        badge = '<span class="inline-flex rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-600/15">FB only · IG pending</span>';
    } else if(fbImp > 0){
        badge = '<span class="inline-flex rounded-md bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-800 ring-1 ring-sky-600/15">Facebook only</span>';
    } else if(targetsIg){
        badge = '<span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-400/20">IG targeted · no data yet</span>';
    }

    return '<div class="space-y-1">' + targetLine + badge + '</div>';
}


/* =============================
   MAIN REFRESH FUNCTION
============================= */

async function refreshAdsDashboard(){

    if(running) return;

    running = true;
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), FETCH_TIMEOUT_MS);

    try{

        const response = await fetch(
            "{{ route('admin.ads.live') }}?t="+Date.now(),
            {
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal,
                headers:{
                    'X-Requested-With':'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            }
        );

        clearTimeout(timeoutId);

        const contentType = response.headers.get('content-type') || '';
        const raw = await response.text();
        let data;

        try {
            data = JSON.parse(raw);
        } catch (parseError) {
            throw new Error('Live endpoint returned non-JSON response');
        }

        if(!response.ok && (!data || !Array.isArray(data.ads))){
            throw new Error((data && data.error) || 'Live refresh failed');
        }

        if(!data || !Array.isArray(data.ads)){
            throw new Error('Live refresh returned invalid payload');
        }

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
            const ctr = document.getElementById('ctr-'+ad.id);
            const spn = document.getElementById('spend-'+ad.id);
            const tdy = document.getElementById('today-'+ad.id);
            const sts = document.getElementById('status-'+ad.id);
            const plt = document.getElementById('platforms-'+ad.id);

            if(imp) imp.textContent = number(ad.impressions);
            if(clk) clk.textContent = number(ad.clicks);
            if(ctr) ctr.innerHTML = renderCtr(ad.ctr);
            if(spn) spn.textContent = money(ad.spend);
            if(tdy) tdy.textContent = money(ad.daily_spend);

            if(sts){
                sts.innerHTML = renderStatus(ad.status);
            }

            if(plt && ad.placement){
                plt.innerHTML = renderPlatforms(ad.placement);
            }

        });

        const warning = data.warning || (data.meta_synced === false ? 'using saved metrics' : '');
        setLiveStatus(true, data.refreshed_at, warning);

    }
    catch(e){

        console.warn('Live dashboard update failed', e);
        if(e && e.name === 'AbortError'){
            setLiveStatus(true, null, 'refresh slow — showing last saved metrics');
        } else {
            setLiveStatus(false);
        }

    }
    finally {
        clearTimeout(timeoutId);
        running = false;
    }

}


/* =============================
   START
============================= */

refreshAdsDashboard();

setInterval(refreshAdsDashboard, REFRESH_MS);

document.addEventListener('visibilitychange', function(){
    if(document.visibilityState === 'visible'){
        refreshAdsDashboard();
    }
});


})();

</script>
@endsection