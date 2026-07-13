@extends('layouts.admin')

@section('content')

<div class="max-w-7xl mx-auto py-6 space-y-6">

@if(session('success'))
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
@endif

<div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Campaign: {{ $campaign->name }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ $campaign->objective }} · {{ ucfirst($campaign->status) }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.campaigns.adsets.create', $campaign) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">+ Ad set</a>
        <a href="{{ route('admin.creatives.builder', ['campaign_id' => $campaign->id]) }}" class="rounded-xl bg-xander-navy px-4 py-2 text-sm font-semibold text-white">Build creative</a>
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="rounded-xl border bg-white p-4 shadow-sm">
        <p class="text-xs font-semibold uppercase text-slate-500">Meta campaign</p>
        <p class="mt-1 font-mono text-sm">{{ $campaign->meta_id ?: 'Not synced' }}</p>
    </div>
    <div class="rounded-xl border bg-white p-4 shadow-sm">
        <p class="text-xs font-semibold uppercase text-slate-500">Ad sets</p>
        <p class="mt-1 text-2xl font-bold">{{ $campaign->adsets->count() }}</p>
    </div>
    <div class="rounded-xl border bg-white p-4 shadow-sm">
        <p class="text-xs font-semibold uppercase text-slate-500">Channel</p>
        <p class="mt-1 text-sm">{{ $campaign->marketing_channel ?? 'standard' }}</p>
    </div>
    <div class="rounded-xl border bg-white p-4 shadow-sm">
        <p class="text-xs font-semibold uppercase text-slate-500">Page</p>
        <p class="mt-1 font-mono text-sm">{{ $campaign->meta_page_id ?: '—' }}</p>
    </div>
</div>

<h2 class="text-xl font-semibold text-slate-900">Ad sets → creatives → ads</h2>

@if($campaign->adsets->count())

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
<table class="w-full text-sm">
<thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
<tr>
<th class="p-3">Ad set</th>
<th class="p-3">Budget</th>
<th class="p-3">Optimization</th>
<th class="p-3">Meta</th>
<th class="p-3">Creatives</th>
<th class="p-3">Ads</th>
<th class="p-3 text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100">
@foreach($campaign->adsets as $adset)
@php
    $creativesCount = $adset->creatives_count ?? $adset->creatives()->count();
    $adsCount = $adset->ads_count ?? $adset->ads()->count();
@endphp
<tr>
<td class="p-3">
    <div class="font-semibold text-slate-900">{{ $adset->name }}</div>
    <div class="text-xs text-slate-500">{{ $adset->status }}</div>
</td>
<td class="p-3 tabular-nums">{{ number_format(($adset->daily_budget ?? 0) / 100, 2) }}/day</td>
<td class="p-3">
    <div>{{ $adset->optimization_goal ?? '—' }}</div>
    <div class="text-xs text-slate-500">{{ $adset->destination_type ?? '' }}</div>
</td>
<td class="p-3 font-mono text-xs">{{ $adset->meta_id ?: 'Local only' }}</td>
<td class="p-3">{{ $creativesCount }}</td>
<td class="p-3">{{ $adsCount }}</td>
<td class="p-3 text-right">
    <div class="flex flex-wrap justify-end gap-2">
        <a href="{{ route('admin.creatives.builder', ['campaign_id' => $campaign->id, 'adset_id' => $adset->id]) }}" class="rounded-lg bg-xander-navy px-3 py-1.5 text-xs font-semibold text-white">Build creative</a>
        @if($creativesCount > 0 && $adset->meta_id)
        <a href="{{ route('admin.adsets.ads.create', $adset) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700">Create ad</a>
        @endif
    </div>
</td>
</tr>
@endforeach
</tbody>
</table>
</div>

@else
<div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
    <p class="text-slate-600">No ad sets yet. Create an ad set first, then build a creative linked to it.</p>
    <a href="{{ route('admin.campaigns.adsets.create', $campaign) }}" class="mt-4 inline-block rounded-xl bg-xander-navy px-5 py-2.5 text-sm font-semibold text-white">Create ad set</a>
</div>
@endif

</div>

@endsection
