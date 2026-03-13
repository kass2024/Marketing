@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto space-y-8">


{{-- =========================================================
HEADER
========================================================= --}}
<div class="flex items-center justify-between flex-wrap gap-4">

<div>

<h1 class="text-2xl font-bold text-gray-900">
Campaigns
</h1>

<p class="text-sm text-gray-500 mt-1">
Create campaigns first, then add AdSets, Creatives and Ads.
</p>

</div>

<a
href="{{ route('admin.campaigns.create') }}"
class="inline-flex items-center gap-2 bg-blue-600 text-white px-5 py-2 rounded-lg shadow hover:bg-blue-700 transition"
>

<span class="text-lg">＋</span>
<span>New Campaign</span>

</a>

</div>



{{-- =========================================================
METRICS
========================================================= --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-4">

<div class="bg-white p-5 rounded-xl shadow border">

<p class="text-sm text-gray-500">
Total Campaigns
</p>

<p class="text-xl font-bold">
{{ $campaigns->total() ?? 0 }}
</p>

</div>


<div class="bg-white p-5 rounded-xl shadow border">

<p class="text-sm text-gray-500">
Active
</p>

<p class="text-xl font-bold text-green-600">
{{ $campaigns->where('status','ACTIVE')->count() }}
</p>

</div>


<div class="bg-white p-5 rounded-xl shadow border">

<p class="text-sm text-gray-500">
Paused
</p>

<p class="text-xl font-bold text-yellow-600">
{{ $campaigns->where('status','PAUSED')->count() }}
</p>

</div>


<div class="bg-white p-5 rounded-xl shadow border">

<p class="text-sm text-gray-500">
Ad Sets
</p>

<p class="text-xl font-bold text-purple-600">
{{ $totalAdSets ?? 0 }}
</p>

</div>

</div>



{{-- =========================================================
CAMPAIGNS TABLE
========================================================= --}}
<div class="bg-white rounded-xl shadow overflow-hidden">

<table class="min-w-full text-sm">

<thead class="bg-gray-50 text-gray-600">

<tr>

<th class="px-6 py-3 text-left">Campaign</th>

<th class="px-6 py-3 text-left">Objective</th>

<th class="px-6 py-3 text-left">Budget</th>

<th class="px-6 py-3 text-left">AdSets</th>

<th class="px-6 py-3 text-left">Status</th>

<th class="px-6 py-3 text-left">Created</th>

<th class="px-6 py-3 text-right">Actions</th>

</tr>

</thead>



<tbody class="divide-y">

@forelse($campaigns as $campaign)

<tr class="hover:bg-gray-50 transition">

{{-- Campaign --}}
<td class="px-6 py-4">

<div class="font-medium">

<a
href="{{ route('admin.campaigns.show',$campaign) }}"
class="hover:text-blue-600"
>

{{ $campaign->name }}

</a>

</div>

@if(!empty($campaign->meta_id))

<div class="text-xs text-gray-400 mt-1">

Meta ID: {{ $campaign->meta_id }}

</div>

@endif

</td>



{{-- Objective --}}
<td class="px-6 py-4">

{{ $campaign->objective ?? 'Not set' }}

</td>



{{-- Budget --}}
<td class="px-6 py-4">

@if(!empty($campaign->daily_budget))

${{ number_format(($campaign->daily_budget ?? 0) / 100,2) }}/day

@else

<span class="text-gray-400">
No budget
</span>

@endif

</td>



{{-- AdSets --}}
<td class="px-6 py-4">

<a
href="{{ route('admin.campaigns.adsets.index',$campaign->id) }}"
class="text-purple-600 hover:text-purple-800 font-medium"
>

{{ $campaign->ad_sets_count ?? 0 }}

</a>

</td>



{{-- Status --}}
<td class="px-6 py-4">

@if($campaign->status == 'ACTIVE')

<span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-medium">
Active
</span>

@elseif($campaign->status == 'PAUSED')

<span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs font-medium">
Paused
</span>

@else

<span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs font-medium">
{{ $campaign->status ?? 'Draft' }}
</span>

@endif

</td>



{{-- Created --}}
<td class="px-6 py-4 text-gray-500">

{{ optional($campaign->created_at)->format('d M Y') }}

</td>



{{-- =========================================================
ACTIONS
========================================================= --}}
<td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">

<a
href="{{ route('admin.campaigns.adsets.index',$campaign->id) }}"
class="text-purple-600 hover:text-purple-800 font-medium"
>
AdSets
</a>


<a
href="{{ route('admin.campaigns.adsets.create',$campaign->id) }}"
class="text-blue-600 hover:text-blue-800"
>
Add AdSet
</a>


<a
href="{{ route('admin.creatives.index',['campaign'=>$campaign->id]) }}"
class="text-green-600 hover:text-green-800"
>
Creatives
</a>


<a
href="{{ route('admin.ads.index',['campaign'=>$campaign->id]) }}"
class="text-indigo-600 hover:text-indigo-800"
>
Ads
</a>


{{-- Activate --}}
@if($campaign->status !== 'ACTIVE')

<form
method="POST"
action="{{ route('admin.campaigns.activate',$campaign->id) }}"
class="inline">

@csrf

<button class="text-green-600 hover:text-green-800 font-medium">
Activate
</button>

</form>

@endif



{{-- Pause --}}
@if($campaign->status === 'ACTIVE')

<form
method="POST"
action="{{ route('admin.campaigns.pause',$campaign->id) }}"
class="inline">

@csrf

<button class="text-yellow-600 hover:text-yellow-800">
Pause
</button>

</form>

@endif



{{-- Sync --}}
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
class="text-gray-600 hover:text-gray-900"
>
Edit
</a>



<form
action="{{ route('admin.campaigns.destroy',$campaign) }}"
method="POST"
class="inline"
onsubmit="return confirm('Delete this campaign?');">

@csrf
@method('DELETE')

<button
class="text-red-600 hover:text-red-800"
type="submit">

Delete

</button>

</form>

</td>

</tr>

@empty


<tr>

<td colspan="7" class="text-center py-16">

<div class="text-gray-400 text-lg">
No campaigns yet
</div>

<a
href="{{ route('admin.campaigns.create') }}"
class="mt-4 inline-block bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700"
>

Create First Campaign

</a>

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



{{-- =========================================================
META WARNING
========================================================= --}}
@if(!isset($hasAdAccount) || !$hasAdAccount)

<div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">

<p class="text-sm text-yellow-700">

<strong>Meta Ad Account not connected.</strong>

You can still create campaigns locally for testing.

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

@endsection