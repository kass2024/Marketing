@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto py-10 space-y-10">


{{-- HEADER --}}
<div class="flex items-center justify-between flex-wrap gap-4">

<div>

<h1 class="text-3xl font-bold text-gray-900">
Ad Insight Dashboard
</h1>

<p class="text-sm text-gray-500 mt-1">
Monitor performance, audience engagement and delivery diagnostics
</p>

</div>

<div class="flex gap-3">

<a href="{{ route('admin.ads.index') }}"
class="bg-gray-800 text-white px-5 py-2 rounded-lg hover:bg-black transition">
Back to Ads
</a>

</div>

</div>



{{-- AD INFORMATION --}}
<div class="bg-white rounded-2xl shadow border p-6">

<div class="grid grid-cols-2 md:grid-cols-4 gap-6">

<div>
<p class="text-xs text-gray-500">Ad Name</p>
<p class="font-semibold text-gray-900">{{ $ad->name }}</p>
</div>

<div>
<p class="text-xs text-gray-500">Status</p>

@if($ad->status === 'ACTIVE')
<span class="bg-green-100 text-green-700 text-xs px-3 py-1 rounded-full">
Active
</span>
@else
<span class="bg-yellow-100 text-yellow-700 text-xs px-3 py-1 rounded-full">
Paused
</span>
@endif

</div>

<div>
<p class="text-xs text-gray-500">Ad Set</p>
<p class="font-medium text-gray-900">
{{ $ad->adSet->name ?? '-' }}
</p>
</div>

<div>
<p class="text-xs text-gray-500">Campaign</p>
<p class="font-medium text-gray-900">
{{ $ad->adSet->campaign->name ?? '-' }}
</p>
</div>

</div>

</div>



{{-- CORE PERFORMANCE --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-6">

<div class="bg-white border rounded-2xl shadow p-6">
<p class="text-xs text-gray-500">Impressions</p>
<p class="text-3xl font-bold text-gray-900">
{{ number_format($ad->impressions ?? 0) }}
</p>
</div>

<div class="bg-white border rounded-2xl shadow p-6">
<p class="text-xs text-gray-500">Clicks</p>
<p class="text-3xl font-bold text-blue-600">
{{ number_format($ad->clicks ?? 0) }}
</p>
</div>

<div class="bg-white border rounded-2xl shadow p-6">
<p class="text-xs text-gray-500">CTR</p>

<p class="text-3xl font-bold text-purple-600">

@if($ad->impressions > 0)
{{ round(($ad->clicks / $ad->impressions) * 100,2) }}%
@else
0%
@endif

</p>

</div>

<div class="bg-white border rounded-2xl shadow p-6">
<p class="text-xs text-gray-500">Total Spend</p>
<p class="text-3xl font-bold text-green-600">
${{ number_format($ad->spend ?? 0,2) }}
</p>
</div>

</div>



{{-- COST METRICS --}}
<div class="bg-white border rounded-2xl shadow p-6">

<h2 class="text-lg font-semibold text-gray-900 mb-4">
Cost Performance
</h2>

<div class="grid md:grid-cols-3 gap-6">

<div>
<p class="text-xs text-gray-500">Cost Per Click</p>

<p class="font-semibold text-gray-900">

@if($ad->clicks > 0)
${{ number_format($ad->spend / $ad->clicks,2) }}
@else
$0.00
@endif

</p>
</div>

<div>
<p class="text-xs text-gray-500">CPM</p>

<p class="font-semibold text-gray-900">

@if($ad->impressions > 0)
${{ number_format(($ad->spend / $ad->impressions) * 1000,2) }}
@else
$0.00
@endif

</p>

</div>

<div>
<p class="text-xs text-gray-500">Daily Budget</p>
<p class="font-semibold text-gray-900">
${{ number_format($ad->daily_budget ?? 0,2) }}
</p>
</div>

</div>

</div>



{{-- DELIVERY DIAGNOSTICS --}}
<div class="bg-white border rounded-2xl shadow p-6">

<h2 class="text-lg font-semibold text-gray-900 mb-4">
Delivery Diagnostics
</h2>

<div class="space-y-2 text-sm">

@if($ad->status === 'ACTIVE')
<div class="text-green-600">✓ Ad is delivering normally</div>
@endif

@if($ad->status !== 'ACTIVE')
<div class="text-yellow-600">⚠ Ad is paused</div>
@endif

@if(($ad->daily_spend ?? 0) >= ($ad->daily_budget ?? 0))
<div class="text-red-600">⚠ Budget limit reached</div>
@endif

@if($ad->impressions > 0 && (($ad->clicks / $ad->impressions) * 100) < 1)
<div class="text-orange-600">⚠ Low CTR – consider improving creative</div>
@endif

</div>

</div>



{{-- AUDIENCE INSIGHTS --}}
<div class="bg-white border rounded-2xl shadow p-6">

<h2 class="text-lg font-semibold text-gray-900 mb-6">
Audience Insights
</h2>

<div class="grid md:grid-cols-3 gap-6">

<div>

<h3 class="text-sm font-semibold mb-2">Top Countries</h3>

@forelse($audience['countries'] ?? [] as $country => $impressions)

<div class="flex justify-between text-sm">
    <span>{{ $country }}</span>
    <span>{{ number_format($impressions) }}</span>
</div>

@empty
<p class="text-sm text-gray-400">No data</p>
@endforelse

</div>


<div>

<h3 class="text-sm font-semibold mb-2">Age Groups</h3>

@foreach($audience['age'] ?? [] as $age => $impressions)

<div class="flex justify-between text-sm">
    <span>{{ $age }}</span>
    <span>{{ number_format($impressions) }}</span>
</div>

@endforeach

</div>


<div>

<h3 class="text-sm font-semibold mb-2">Gender</h3>

@foreach($audience['gender'] ?? [] as $gender => $impressions)

<div class="flex justify-between text-sm">
    <span>{{ ucfirst($gender) }}</span>
    <span>{{ number_format($impressions) }}</span>
</div>

@endforeach

</div>

</div>

</div>



{{-- DEVICE PERFORMANCE --}}
<div class="bg-white border rounded-2xl shadow p-6">

<h2 class="text-lg font-semibold mb-4">
Device Performance
</h2>

<table class="w-full text-sm">

<thead class="text-gray-500 border-b">
<tr>
<th class="text-left py-2">Device</th>
<th class="text-right">Impressions</th>
<th class="text-right">Clicks</th>
</tr>
</thead>

<tbody class="divide-y">

@forelse($devices ?? [] as $device)

<tr>

<td class="py-2">{{ $device['device'] }}</td>

<td class="text-right">
{{ number_format($device['impressions']) }}
</td>

<td class="text-right">
{{ number_format($device['clicks']) }}
</td>

</tr>

@empty

<tr>
<td colspan="3" class="text-center text-gray-400 py-4">
No device data
</td>
</tr>

@endforelse

</tbody>

</table>

</div>



{{-- PLACEMENT PERFORMANCE --}}
<div class="bg-white border rounded-2xl shadow p-6">

<h2 class="text-lg font-semibold mb-4">
Placement Performance
</h2>

<table class="w-full text-sm">

<thead class="text-gray-500 border-b">

<tr>
<th class="text-left py-2">Placement</th>
<th class="text-right">Impressions</th>
<th class="text-right">Clicks</th>
</tr>

</thead>

<tbody class="divide-y">

@forelse($placements ?? [] as $placement)

<tr>

<td class="py-2">{{ $placement['placement'] }}</td>

<td class="text-right">
{{ number_format($placement['impressions']) }}
</td>

<td class="text-right">
{{ number_format($placement['clicks']) }}
</td>

</tr>

@empty

<tr>
<td colspan="3" class="text-center text-gray-400 py-4">
No placement data
</td>
</tr>

@endforelse

</tbody>

</table>

</div>
{{-- CREATIVE PREVIEW --}}
@if($ad->creative)

<div class="bg-white border rounded-2xl shadow p-6">

<h2 class="text-lg font-semibold mb-6">🎨 Creative Preview</h2>

<div class="max-w-md mx-auto bg-gray-50 border rounded-2xl overflow-hidden shadow-sm">

@php
$image = $ad->creative->image_url;

if ($image && !str_starts_with($image, 'http')) {
    $image = asset('storage/creatives/' . basename($image));
}
@endphp

@if($image)
<img 
    src="{{ $image }}" 
    class="w-full h-64 object-cover"
    onerror="this.src='https://via.placeholder.com/400x300?text=No+Image'"
>
@else
<div class="h-64 flex items-center justify-center text-gray-400">
No image available
</div>
@endif

<div class="p-5 space-y-3">

@if($ad->creative->headline)
<p class="font-semibold text-gray-900 text-lg">
{{ $ad->creative->headline }}
</p>
@endif

@if($ad->creative->body)
<p class="text-sm text-gray-600">
{{ $ad->creative->body }}
</p>
@endif

<button class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition">
Learn More
</button>

</div>

</div>

</div>

@endif

</div>

@endsection