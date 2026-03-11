@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto space-y-8">


{{-- =========================================
HEADER
========================================= --}}
<div class="flex items-center justify-between flex-wrap gap-4">

<div>
<h1 class="text-2xl font-bold text-gray-900">
Ads Manager Dashboard
</h1>

<p class="text-sm text-gray-500 mt-1">
Monitor campaigns, creatives, ads performance and Meta delivery status.
</p>
</div>

<div class="flex gap-3">

<a
href="{{ route('admin.campaigns.create') }}"
class="bg-blue-600 text-white px-5 py-2 rounded-lg shadow hover:bg-blue-700"
>
+ Campaign
</a>

<a
href="{{ route('admin.creatives.create') }}"
class="bg-green-600 text-white px-5 py-2 rounded-lg shadow hover:bg-green-700"
>
+ Creative
</a>

<a
href="{{ route('admin.ads.create') }}"
class="bg-indigo-600 text-white px-5 py-2 rounded-lg shadow hover:bg-indigo-700"
>
+ Ad
</a>

</div>

</div>



{{-- =========================================
GLOBAL PERFORMANCE METRICS
========================================= --}}
<div class="grid grid-cols-2 md:grid-cols-6 gap-4">

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-xs text-gray-500">Campaigns</p>
<p class="text-xl font-bold">{{ $campaigns->total() }}</p>
</div>

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-xs text-gray-500">AdSets</p>
<p class="text-xl font-bold text-purple-600">{{ $totalAdSets ?? 0 }}</p>
</div>

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-xs text-gray-500">Creatives</p>
<p class="text-xl font-bold text-indigo-600">{{ $totalCreatives ?? 0 }}</p>
</div>

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-xs text-gray-500">Ads</p>
<p class="text-xl font-bold text-blue-600">{{ $totalAds ?? 0 }}</p>
</div>

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-xs text-gray-500">Spend</p>
<p class="text-xl font-bold text-red-600">
${{ number_format($totalSpend ?? 0,2) }}
</p>
</div>

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-xs text-gray-500">Clicks</p>
<p class="text-xl font-bold text-green-600">
{{ $totalClicks ?? 0 }}
</p>
</div>

</div>



{{-- =========================================
PERFORMANCE CHART
========================================= --}}
<div class="bg-white p-6 rounded-xl shadow border">

<div class="flex justify-between items-center mb-4">

<h2 class="font-semibold text-gray-800">
Performance Overview
</h2>

</div>

<canvas id="performanceChart" height="80"></canvas>

</div>



{{-- =========================================
CAMPAIGNS TABLE
========================================= --}}
<div class="bg-white rounded-xl shadow border overflow-hidden">

<table class="min-w-full text-sm">

<thead class="bg-gray-50 text-gray-600">

<tr>

<th class="px-6 py-3 text-left">Campaign</th>

<th class="px-6 py-3 text-left">Objective</th>

<th class="px-6 py-3 text-left">Budget</th>

<th class="px-6 py-3 text-left">AdSets</th>

<th class="px-6 py-3 text-left">Creative</th>

<th class="px-6 py-3 text-left">Delivery</th>

<th class="px-6 py-3 text-left">Spend</th>

<th class="px-6 py-3 text-left">Clicks</th>

<th class="px-6 py-3 text-left">CTR</th>

<th class="px-6 py-3 text-left">Created</th>

<th class="px-6 py-3 text-right">Actions</th>

</tr>

</thead>


<tbody class="divide-y">

@forelse($campaigns as $campaign)

<tr class="hover:bg-gray-50">

{{-- CAMPAIGN --}}
<td class="px-6 py-4">

<div class="font-medium">

<a
href="{{ route('admin.campaigns.show',$campaign) }}"
class="hover:text-blue-600"
>
{{ $campaign->name }}
</a>

</div>

@if($campaign->meta_id)

<div class="text-xs text-gray-400 mt-1">
Meta ID: {{ $campaign->meta_id }}
</div>

@endif

</td>



{{-- OBJECTIVE --}}
<td class="px-6 py-4">
{{ $campaign->objective ?? 'Not set' }}
</td>



{{-- BUDGET --}}
<td class="px-6 py-4">

@if($campaign->daily_budget)

${{ number_format($campaign->daily_budget/100,2) }}/day

@else

<span class="text-gray-400">No budget</span>

@endif

</td>



{{-- ADSETS --}}
<td class="px-6 py-4">

<a
href="{{ route('admin.campaigns.adsets.index',$campaign->id) }}"
class="text-purple-600 hover:text-purple-800"
>

{{ $campaign->ad_sets_count ?? 0 }}

</a>

</td>



{{-- CREATIVE PREVIEW --}}
<td class="px-6 py-4">

@if($campaign->ads->first()?->creative?->image_url)

<img
src="{{ asset('storage/'.$campaign->ads->first()->creative->image_url) }}"
class="w-14 h-14 rounded object-cover">

@else

<span class="text-gray-400 text-xs">None</span>

@endif

</td>



{{-- DELIVERY STATUS --}}
<td class="px-6 py-4">

@if($campaign->status == 'ACTIVE')

<span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs">
Active
</span>

@elseif($campaign->status == 'PAUSED')

<span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs">
Paused
</span>

@else

<span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">
{{ $campaign->status }}
</span>

@endif

</td>



{{-- SPEND --}}
<td class="px-6 py-4">
${{ number_format($campaign->spend ?? 0,2) }}
</td>



{{-- CLICKS --}}
<td class="px-6 py-4">
{{ $campaign->clicks ?? 0 }}
</td>



{{-- CTR --}}
<td class="px-6 py-4">
{{ $campaign->ctr ?? 0 }}%
</td>



{{-- CREATED --}}
<td class="px-6 py-4 text-gray-500">
{{ optional($campaign->created_at)->format('d M Y') }}
</td>



{{-- ACTIONS --}}
<td class="px-6 py-4 text-right space-x-3">

<a
href="{{ route('admin.campaigns.adsets.index',$campaign->id) }}"
class="text-purple-600"
>
AdSets
</a>

<a
href="{{ route('admin.creatives.index',['campaign'=>$campaign->id]) }}"
class="text-green-600"
>
Creatives
</a>

<a
href="{{ route('admin.ads.index',['campaign'=>$campaign->id]) }}"
class="text-indigo-600"
>
Ads
</a>

</td>

</tr>

@empty

<tr>

<td colspan="11" class="text-center py-16 text-gray-400">

No campaigns yet

</td>

</tr>

@endforelse

</tbody>

</table>


@if($campaigns->hasPages())

<div class="p-4 border-t">
{{ $campaigns->links() }}
</div>

@endif

</div>



{{-- =========================================
META ACCOUNT WARNING
========================================= --}}
@if(!isset($hasAdAccount) || !$hasAdAccount)

<div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">

<p class="text-sm text-yellow-700">

<strong>Meta Ad Account not connected.</strong>

<a
href="{{ route('admin.accounts.index') }}"
class="underline ml-1"
>
Connect account →
</a>

</p>

</div>

@endif


</div>



{{-- =========================================
CHART SCRIPT (READY FOR META INSIGHTS)
========================================= --}}

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const ctx = document.getElementById('performanceChart');

new Chart(ctx,{

type:'line',

data:{
labels:@json($chartLabels ?? []),
datasets:[

{
label:'Spend',
data:@json($chartSpend ?? []),
borderWidth:2
},

{
label:'Clicks',
data:@json($chartClicks ?? []),
borderWidth:2
}

]
}

});

</script>

@endsection