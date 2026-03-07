@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto space-y-8 py-10">

{{-- ================= HEADER ================= --}}

<div class="flex items-center justify-between flex-wrap gap-4">

<div>
<h1 class="text-3xl font-bold text-gray-900">
Ad Sets
</h1>

<p class="text-sm text-gray-500 mt-1">
Manage targeting, budgets and delivery for your campaigns.
</p>
</div>

<a
href="{{ route('admin.campaigns.index') }}"
class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition"

>

Back to Campaigns </a>

</div>

{{-- ================= FILTER BAR ================= --}}

<div class="bg-white p-4 rounded-xl shadow flex items-center justify-between flex-wrap gap-3">

<div class="flex gap-3">

<select class="border rounded-lg px-3 py-2 text-sm">
<option>All Status</option>
<option value="ACTIVE">ACTIVE</option>
<option value="PAUSED">PAUSED</option>
<option value="DRAFT">DRAFT</option>
</select>

<select class="border rounded-lg px-3 py-2 text-sm">
<option>Last 30 Days</option>
<option>Last 7 Days</option>
<option>Today</option>
</select>

</div>

<div class="text-sm text-gray-500">
{{ $adsets->total() ?? $adsets->count() }} Ad Sets
</div>

</div>

{{-- ================= TABLE ================= --}}

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

{{-- ADSET NAME --}}

<td class="font-medium">

{{ $adset->name }}

<div class="text-xs text-gray-400 mt-1">
ID: {{ $adset->id }}
</div>

</td>

{{-- CAMPAIGN --}}

<td>
{{ $adset->campaign->name ?? '-' }}
</td>

{{-- BUDGET --}}

<td>

@if($adset->daily_budget)

${{ number_format($adset->daily_budget / 100,2) }}

<div class="text-xs text-gray-400">
Daily
</div>

@else

<span class="text-gray-400">
No Budget
</span>

@endif

</td>

{{-- STATUS --}}

<td>

<span class="px-2 py-1 text-xs rounded font-medium

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

{{-- META ID --}}

<td class="text-xs text-gray-600 font-mono">

{{ $adset->meta_id ?? '-' }}

</td>

{{-- ACTIONS --}}

<td class="text-right pr-6 space-x-4 whitespace-nowrap">

<a
href="{{ route('admin.ads.create',['adset'=>$adset->id]) }}"
class="text-purple-600 hover:text-purple-800 text-sm"

>

Create Ad </a>

<a
href="{{ route('admin.adsets.edit',$adset->id) }}"
class="text-blue-600 hover:text-blue-800 text-sm"

>

Edit </a>

<a
href="{{ route('admin.ads.index',['adset'=>$adset->id]) }}"
class="text-gray-600 hover:text-gray-800 text-sm"

>

View Ads </a>

</td>

</tr>

@empty

<tr>

<td colspan="7" class="p-12 text-center text-gray-500">

<div class="flex flex-col items-center gap-4">

<div class="text-lg font-medium">
No Ad Sets Found
</div>

<p class="text-sm">
Create an Ad Set from a Campaign to start running ads.
</p>

<a
href="{{ route('admin.campaigns.index') }}"
class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"

>

Go to Campaigns </a>

</div>

</td>

</tr>

@endforelse

</tbody>

</table>

</div>

{{-- ================= PAGINATION ================= --}}
@if(method_exists($adsets,'links'))

<div>
{{ $adsets->links() }}
</div>

@endif

</div>

@endsection
