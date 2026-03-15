@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto py-10 space-y-8">


{{-- =========================================================
HEADER
========================================================= --}}
<div class="flex items-center justify-between flex-wrap gap-4">

<div>

<h1 class="text-2xl font-bold text-gray-900">
Campaigns
</h1>

<p class="text-sm text-gray-500 mt-1">
Create campaigns first, then build AdSets, Creatives and Ads.
</p>

</div>


<div class="flex gap-3">

{{-- BACK TO DASHBOARD --}}
<a
href="{{ route('admin.dashboard') }}"
class="inline-flex items-center gap-2 bg-gray-700 text-white px-4 py-2 rounded-lg shadow hover:bg-gray-800 transition"
>

<span>←</span>
<span>Dashboard</span>

</a>


{{-- CREATE CAMPAIGN --}}
<a
href="{{ route('admin.campaigns.create') }}"
class="inline-flex items-center gap-2 bg-blue-600 text-white px-5 py-2 rounded-lg shadow hover:bg-blue-700 transition"
>

<span class="text-lg">＋</span>
<span>New Campaign</span>

</a>

</div>

</div>



{{-- =========================================================
METRICS
========================================================= --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-4">

{{-- TOTAL --}}
<div class="bg-white p-5 rounded-xl shadow border">

<p class="text-sm text-gray-500">
Total Campaigns
</p>

<p class="text-2xl font-bold text-gray-900">
{{ $campaigns->total() ?? 0 }}
</p>

</div>


{{-- ACTIVE --}}
<div class="bg-white p-5 rounded-xl shadow border">

<p class="text-sm text-gray-500">
Active
</p>

<p class="text-2xl font-bold text-green-600">
{{ $campaigns->where('status','ACTIVE')->count() }}
</p>

</div>


{{-- PAUSED --}}
<div class="bg-white p-5 rounded-xl shadow border">

<p class="text-sm text-gray-500">
Paused
</p>

<p class="text-2xl font-bold text-yellow-600">
{{ $campaigns->where('status','PAUSED')->count() }}
</p>

</div>


{{-- ADSETS --}}
<div class="bg-white p-5 rounded-xl shadow border">

<p class="text-sm text-gray-500">
Total AdSets
</p>

<p class="text-2xl font-bold text-purple-600">
{{ $totalAdSets ?? 0 }}
</p>

</div>

</div>



{{-- =========================================================
CAMPAIGNS TABLE
========================================================= --}}
<div class="bg-white border border-gray-200 rounded-2xl shadow overflow-hidden">

<div class="overflow-x-auto">

<table class="min-w-full text-sm">

<thead class="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">

<tr>

<th class="px-6 py-4 text-left">Campaign</th>
<th class="px-6 py-4 text-left">Objective</th>
<th class="px-6 py-4 text-left">Budget</th>
<th class="px-6 py-4 text-left">AdSets</th>
<th class="px-6 py-4 text-left">Status</th>
<th class="px-6 py-4 text-left">Created</th>
<th class="px-6 py-4 text-right">Actions</th>

</tr>

</thead>



<tbody class="divide-y divide-gray-200">

@forelse($campaigns as $campaign)

<tr class="hover:bg-gray-50 transition">


{{-- CAMPAIGN --}}
<td class="px-6 py-4">

<div class="font-medium text-gray-900">

<a
href="{{ route('admin.campaigns.show',$campaign) }}"
class="hover:text-blue-600">

{{ $campaign->name }}

</a>

</div>

@if(!empty($campaign->meta_id))

<div class="text-xs text-gray-400 mt-1">
Meta ID: {{ $campaign->meta_id }}
</div>

@endif

</td>



{{-- OBJECTIVE --}}
<td class="px-6 py-4 text-gray-700">

{{ $campaign->objective ?? 'Not set' }}

</td>



{{-- BUDGET --}}
<td class="px-6 py-4">

@if(!empty($campaign->daily_budget))

<span class="font-medium text-gray-900">

${{ number_format(($campaign->daily_budget ?? 0) / 100,2) }}/day

</span>

@else

<span class="text-gray-400">
No budget
</span>

@endif

</td>



{{-- ADSETS --}}
<td class="px-6 py-4">

<a
href="{{ route('admin.campaigns.adsets.index',$campaign->id) }}"
class="text-purple-600 hover:text-purple-800 font-medium"
>

{{ $campaign->ad_sets_count ?? 0 }}

</a>

</td>



{{-- STATUS --}}
<td class="px-6 py-4">

@if($campaign->status == 'ACTIVE')

<span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
Active
</span>

@elseif($campaign->status == 'PAUSED')

<span class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
Paused
</span>

@else

<span class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
{{ $campaign->status ?? 'Draft' }}
</span>

@endif

</td>



{{-- CREATED --}}
<td class="px-6 py-4 text-gray-500 text-sm">

{{ optional($campaign->created_at)->format('d M Y') }}

</td>



{{-- =========================================================
ACTIONS
========================================================= --}}
<td class="px-6 py-4 text-right">

<div class="flex justify-end gap-3 text-sm">

<a
href="{{ route('admin.campaigns.adsets.index',$campaign->id) }}"
class="text-purple-600 hover:text-purple-800">
AdSets
</a>

<a
href="{{ route('admin.campaigns.adsets.create',$campaign->id) }}"
class="text-blue-600 hover:text-blue-800">
Add
</a>

<a
href="{{ route('admin.creatives.index',['campaign'=>$campaign->id]) }}"
class="text-green-600 hover:text-green-800">
Creatives
</a>

<a
href="{{ route('admin.ads.index',['campaign'=>$campaign->id]) }}"
class="text-indigo-600 hover:text-indigo-800">
Ads
</a>


@if($campaign->status !== 'ACTIVE')

<form
method="POST"
action="{{ route('admin.campaigns.activate',$campaign->id) }}"
class="inline">

@csrf
@method('PATCH')

<button class="text-green-600 hover:text-green-800">
Activate
</button>

</form>

@endif


@if($campaign->status === 'ACTIVE')

<form
method="POST"
action="{{ route('admin.campaigns.pause',$campaign->id) }}"
class="inline">

@csrf
@method('PATCH')

<button class="text-yellow-600 hover:text-yellow-800">
Pause
</button>

</form>

@endif


<form
method="POST"
action="{{ route('admin.campaigns.sync',$campaign->id) }}"
class="inline">

@csrf

<button class="text-blue-600 hover:text-blue-800">
Sync
</button>

</form>


<a
href="{{ route('admin.campaigns.edit',$campaign) }}"
class="text-gray-600 hover:text-gray-900">
Edit
</a>


<form
action="{{ route('admin.campaigns.destroy',$campaign) }}"
method="POST"
class="inline"
onsubmit="return confirm('Delete this campaign?');">

@csrf
@method('DELETE')

<button class="text-red-600 hover:text-red-800">
Delete
</button>

</form>

</div>

</td>

</tr>

@empty



{{-- EMPTY STATE --}}
<tr>

<td colspan="7" class="px-6 py-20 text-center">

<div class="flex flex-col items-center gap-4 text-gray-400">

<div class="text-5xl">
📢
</div>

<p class="text-lg">
No campaigns yet
</p>

<a
href="{{ route('admin.campaigns.create') }}"
class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700">

Create First Campaign

</a>

</div>

</td>

</tr>

@endforelse

</tbody>

</table>

</div>



{{-- PAGINATION --}}
@if($campaigns->hasPages())

<div class="px-6 py-4 border-t bg-gray-50">

{{ $campaigns->links() }}

</div>

@endif

</div>



{{-- =========================================================
META WARNING
========================================================= --}}
@if(!isset($hasAdAccount) || !$hasAdAccount)

<div class="bg-yellow-50 border-l-4 border-yellow-400 p-5 rounded-xl">

<p class="text-sm text-yellow-700">

<strong>Meta Ad Account not connected.</strong>

You can still create campaigns locally for testing.

<a
href="{{ route('admin.accounts.index') }}"
class="underline ml-1">

Connect account →

</a>

</p>

</div>

@endif



</div>

@endsection