@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto py-10 space-y-8">

{{-- HEADER --}}
<div class="flex items-center justify-between">

<div>
<h1 class="text-3xl font-bold text-gray-900">
Ad Insight Dashboard
</h1>

<p class="text-sm text-gray-500">
Monitor performance and delivery of this advertisement
</p>
</div>

<a href="{{ route('admin.ads.index') }}"
class="bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-black transition">
Back
</a>

</div>



{{-- AD INFO --}}
<div class="bg-white rounded-2xl shadow border p-6">

<div class="grid grid-cols-2 md:grid-cols-4 gap-6">

<div>
<p class="text-xs text-gray-500">Ad Name</p>
<p class="font-semibold text-gray-900">
{{ $ad->name }}
</p>
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
<p class="text-gray-900 font-medium">
{{ $ad->adSet->name ?? '-' }}
</p>
</div>

<div>
<p class="text-xs text-gray-500">Campaign</p>
<p class="text-gray-900 font-medium">
{{ $ad->adSet->campaign->name ?? '-' }}
</p>
</div>

</div>

</div>



{{-- PERFORMANCE METRICS --}}
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



{{-- BUDGET MONITOR --}}
<div class="bg-white border rounded-2xl shadow p-6">

<h2 class="text-lg font-semibold text-gray-900 mb-4">
Budget Monitoring
</h2>

<div class="grid md:grid-cols-3 gap-6">

<div>
<p class="text-xs text-gray-500">Daily Budget</p>
<p class="font-semibold text-gray-900">
${{ number_format($ad->daily_budget ?? 0,2) }}
</p>
</div>

<div>
<p class="text-xs text-gray-500">Today's Spend</p>
<p class="font-semibold text-gray-900">
${{ number_format($ad->daily_spend ?? 0,2) }}
</p>
</div>

<div>
<p class="text-xs text-gray-500">Remaining Budget</p>

<p class="font-semibold text-gray-900">

${{ number_format(
max(($ad->daily_budget ?? 0) - ($ad->daily_spend ?? 0),0),
2
) }}

</p>

</div>

</div>

</div>



{{-- DELIVERY DIAGNOSTICS --}}
<div class="bg-white border rounded-2xl shadow p-6">

<h2 class="text-lg font-semibold text-gray-900 mb-4">
Delivery Diagnostics
</h2>

<div class="space-y-3 text-sm">

@if($ad->status === 'ACTIVE')

<div class="text-green-600">
✓ Ad is currently delivering normally
</div>

@else

<div class="text-yellow-600">
⚠ Ad is paused
</div>

@endif


@if(($ad->daily_spend ?? 0) >= ($ad->daily_budget ?? 0))

<div class="text-red-600">
⚠ Daily budget limit reached
</div>

@endif


@if($ad->impressions > 0 && (($ad->clicks / $ad->impressions) * 100) < 1)

<div class="text-orange-600">
⚠ Low CTR – consider improving creative
</div>

@endif

</div>

</div>



{{-- PERFORMANCE BREAKDOWN --}}
<div class="bg-white border rounded-2xl shadow p-6">

<h2 class="text-lg font-semibold text-gray-900 mb-4">
Performance Breakdown
</h2>

<table class="w-full text-sm">

<thead class="text-gray-500 border-b">
<tr>
<th class="text-left py-2">Metric</th>
<th class="text-right">Value</th>
</tr>
</thead>

<tbody class="divide-y">

<tr>
<td class="py-2">Cost Per Click</td>

<td class="text-right">

@if($ad->clicks > 0)
${{ number_format($ad->spend / $ad->clicks,2) }}
@else
$0.00
@endif

</td>
</tr>

<tr>
<td class="py-2">CPM</td>

<td class="text-right">

@if($ad->impressions > 0)
${{ number_format(($ad->spend / $ad->impressions) * 1000,2) }}
@else
$0.00
@endif

</td>
</tr>

<tr>
<td class="py-2">Clicks</td>
<td class="text-right">
{{ number_format($ad->clicks ?? 0) }}
</td>
</tr>

<tr>
<td class="py-2">Impressions</td>
<td class="text-right">
{{ number_format($ad->impressions ?? 0) }}
</td>
</tr>

</tbody>

</table>

</div>



{{-- CREATIVE PREVIEW --}}
@if($ad->creative)

<div class="bg-white border rounded-2xl shadow p-6">

<h2 class="text-lg font-semibold text-gray-900 mb-4">
Creative Preview
</h2>

<div class="max-w-md border rounded-lg overflow-hidden">

@if($ad->creative->image_url)

<img
src="{{ str_starts_with($ad->creative->image_url,'http') 
? $ad->creative->image_url 
: asset('storage/'.$ad->creative->image_url) }}"
class="w-full"
/>

@endif

<div class="p-4 space-y-2">

@if($ad->creative->headline)
<p class="font-semibold">
{{ $ad->creative->headline }}
</p>
@endif

@if($ad->creative->body)
<p class="text-sm text-gray-600">
{{ $ad->creative->body }}
</p>
@endif

</div>

</div>

</div>

@endif


</div>

@endsection