@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto space-y-8">

{{-- HEADER --}}
<div class="flex items-center justify-between">

<div>
<h1 class="text-3xl font-bold text-gray-900">
Ad Sets
</h1>

<p class="text-sm text-gray-500 mt-1">
Manage targeting, budgets and delivery for your campaigns.
</p>
</div>

<div class="flex gap-3">

<a href="{{ route('admin.campaigns.index') }}"
class="bg-gray-600 text-white px-4 py-2 rounded-lg shadow hover:bg-gray-700">
Back to Campaigns
</a>

</div>

</div>



{{-- FILTER BAR --}}
<div class="bg-white p-4 rounded-xl shadow flex items-center justify-between">

<div class="flex gap-3">

<select class="border rounded-lg px-3 py-2 text-sm">
<option>All Status</option>
<option>Active</option>
<option>Paused</option>
<option>Archived</option>
</select>

<select class="border rounded-lg px-3 py-2 text-sm">
<option>Last 30 Days</option>
<option>Last 7 Days</option>
<option>Today</option>
</select>

</div>

<div class="text-sm text-gray-500">
{{ $adsets->total() }} Ad Sets
</div>

</div>



{{-- ADSETS TABLE --}}
<div class="bg-white rounded-xl shadow overflow-hidden">

<table class="w-full text-sm">

<thead class="bg-gray-50 text-gray-600">

<tr>

<th class="p-3 w-10">
<input type="checkbox">
</th>

<th class="text-left">Ad Set</th>

<th>Campaign</th>

<th>Budget</th>

<th>Status</th>

<th>Meta ID</th>

<th class="text-right pr-6">Actions</th>

</tr>

</thead>

<tbody>

@forelse($adsets as $adset)

<tr class="border-t hover:bg-gray-50 transition">

<td class="p-3">
<input type="checkbox">
</td>

<td class="font-medium">
{{ $adset->name }}
</td>

<td>
{{ $adset->campaign->name ?? '-' }}
</td>

<td>
${{ number_format($adset->daily_budget / 100,2) }}
</td>

<td>

<span class="px-2 py-1 text-xs rounded
@if($adset->status=='ACTIVE')
bg-green-100 text-green-700
@elseif($adset->status=='PAUSED')
bg-yellow-100 text-yellow-700
@else
bg-gray-100 text-gray-700
@endif
">

{{ $adset->status }}

</span>

</td>

<td class="text-xs text-gray-500">
{{ $adset->meta_id }}
</td>

<td class="text-right pr-6 space-x-4">

{{-- CREATE AD --}}
<a href="{{ route('admin.ads.create',['adset'=>$adset->id]) }}"
class="text-purple-600 hover:underline text-sm">
Create Ad
</a>

{{-- EDIT ADSET --}}
<a href="{{ route('admin.adsets.edit',$adset->id) }}"
class="text-blue-600 hover:underline text-sm">
Edit
</a>

{{-- VIEW ADS --}}
<a href="{{ route('admin.ads.index',['adset'=>$adset->id]) }}"
class="text-gray-600 hover:underline text-sm">
View Ads
</a>

</td>

</tr>

@empty

<tr>

<td colspan="7" class="p-10 text-center text-gray-500">

<div class="flex flex-col items-center gap-4">

<div class="text-lg font-medium">
No Ad Sets Yet
</div>

<p class="text-sm">
Create an Ad Set from a Campaign first.
</p>

<a href="{{ route('admin.campaigns.index') }}"
class="bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700">
Go to Campaigns
</a>

</div>

</td>

</tr>

@endforelse

</tbody>

</table>

</div>



{{-- PAGINATION --}}
<div>
{{ $adsets->links() }}
</div>

</div>

@endsection