@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto space-y-8 py-10">


{{-- =========================================================
HEADER
========================================================= --}}
<div class="flex items-center justify-between flex-wrap gap-4">

<div>
<h1 class="text-3xl font-bold text-gray-900">
Ad Sets
</h1>

<p class="text-sm text-gray-500 mt-1">
Manage targeting, budgets and delivery settings.
</p>
</div>

<div class="flex gap-3">

<a
href="{{ route('admin.dashboard') }}"
class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition">
Dashboard
</a>

<a
href="{{ route('admin.campaigns.index') }}"
class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
Back to Campaigns
</a>

</div>

</div>



{{-- =========================================================
METRICS
========================================================= --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-4">

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-sm text-gray-500">Total Ad Sets</p>
<p class="text-xl font-bold">
{{ $adsets->total() ?? $adsets->count() }}
</p>
</div>

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-sm text-gray-500">Active</p>
<p class="text-xl font-bold text-green-600">
{{ $adsets->where('status','ACTIVE')->count() }}
</p>
</div>

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-sm text-gray-500">Paused</p>
<p class="text-xl font-bold text-yellow-600">
{{ $adsets->where('status','PAUSED')->count() }}
</p>
</div>

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-sm text-gray-500">Draft</p>
<p class="text-xl font-bold text-gray-600">
{{ $adsets->where('status','DRAFT')->count() }}
</p>
</div>

</div>



{{-- =========================================================
FILTER BAR
========================================================= --}}
<div class="bg-white p-4 rounded-xl shadow flex items-center justify-between flex-wrap gap-3">

<div class="flex gap-3">

<select class="border rounded-lg px-3 py-2 text-sm">
<option>All Status</option>
<option value="ACTIVE">Active</option>
<option value="PAUSED">Paused</option>
<option value="DRAFT">Draft</option>
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



{{-- =========================================================
TABLE
========================================================= --}}
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



{{-- NAME --}}
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
Daily Budget
</div>

@else

<span class="text-gray-400">
No Budget
</span>

@endif

</td>



{{-- STATUS --}}
<td>

@switch($adset->status)

@case('ACTIVE')
<span class="px-2 py-1 text-xs rounded bg-green-100 text-green-700">
Active
</span>
@break

@case('PAUSED')
<span class="px-2 py-1 text-xs rounded bg-yellow-100 text-yellow-700">
Paused
</span>
@break

@case('ARCHIVED')
<span class="px-2 py-1 text-xs rounded bg-gray-300 text-gray-700">
Archived
</span>
@break

@default
<span class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-700">
Draft
</span>

@endswitch

</td>



{{-- META ID --}}
<td class="text-xs text-gray-600 font-mono">

{{ $adset->meta_id ?? '-' }}

</td>



{{-- =========================================================
ACTIONS
========================================================= --}}
<td class="text-right pr-6 space-x-2 whitespace-nowrap">


<a
href="{{ route('admin.ads.create',['adset'=>$adset->id]) }}"
class="text-purple-600 hover:text-purple-800 text-sm">
Create Ad
</a>


<a
href="{{ route('admin.ads.index',['adset'=>$adset->id]) }}"
class="text-gray-600 hover:text-gray-800 text-sm">
View Ads
</a>


<a
href="{{ route('admin.adsets.edit',$adset->id) }}"
class="text-blue-600 hover:text-blue-800 text-sm">
Edit
</a>



@if($adset->status=='PAUSED' || $adset->status=='DRAFT')

<form method="POST"
action="{{ route('admin.adsets.activate',$adset) }}"
class="inline">

@csrf
@method('PATCH')

<button class="text-green-600 hover:text-green-800 text-sm">
Activate
</button>

</form>

@endif



@if($adset->status=='ACTIVE')

<form method="POST"
action="{{ route('admin.adsets.pause',$adset) }}"
class="inline">

@csrf
@method('PATCH')

<button class="text-yellow-600 hover:text-yellow-800 text-sm">
Pause
</button>

</form>

@endif



<form method="POST"
action="{{ route('admin.adsets.duplicate',$adset) }}"
class="inline">

@csrf

<button class="text-indigo-600 hover:text-indigo-800 text-sm">
Duplicate
</button>

</form>



<form method="POST"
action="{{ route('admin.adsets.sync',$adset) }}"
class="inline">

@csrf

<button class="text-gray-600 hover:text-gray-800 text-sm">
Sync
</button>

</form>



<form method="POST"
action="{{ route('admin.adsets.destroy',$adset) }}"
class="inline">

@csrf
@method('DELETE')

<button
onclick="return confirm('Delete this Ad Set?')"
class="text-red-600 hover:text-red-800 text-sm">
Delete
</button>

</form>


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
class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
Go to Campaigns
</a>

</div>

</td>

</tr>

@endforelse

</tbody>

</table>

</div>



{{-- =========================================================
PAGINATION
========================================================= --}}
@if(method_exists($adsets,'links'))

<div>
{{ $adsets->links() }}
</div>

@endif


</div>

@endsection