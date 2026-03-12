@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto space-y-8">


{{-- HEADER --}}
<div class="flex items-center justify-between flex-wrap gap-4">

<div>
<h1 class="text-2xl font-bold text-gray-900">
Ads Manager
</h1>

<p class="text-sm text-gray-500 mt-1">
Manage ads, creatives and delivery performance.
</p>
</div>

<a
href="{{ route('admin.ads.create') }}"
class="inline-flex items-center gap-2 bg-blue-600 text-white px-5 py-2 rounded-lg shadow hover:bg-blue-700 transition">

<span class="text-lg">＋</span>
<span>Create Ad</span>

</a>

</div>



{{-- METRICS --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-4">

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-sm text-gray-500">Total Ads</p>
<p class="text-xl font-bold">{{ $ads->total() ?? 0 }}</p>
</div>

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-sm text-gray-500">Active Ads</p>
<p class="text-xl font-bold text-green-600">
{{ $ads->where('status','ACTIVE')->count() }}
</p>
</div>

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-sm text-gray-500">Total Spend</p>
<p class="text-xl font-bold text-blue-600">
${{ number_format($ads->sum('spend'),2) }}
</p>
</div>

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-sm text-gray-500">Total Clicks</p>
<p class="text-xl font-bold text-purple-600">
{{ number_format($ads->sum('clicks')) }}
</p>
</div>

</div>



{{-- SUCCESS MESSAGE --}}
@if(session('success'))

<div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
{{ session('success') }}
</div>

@endif



{{-- TABLE --}}
<div class="bg-white rounded-xl shadow overflow-hidden">

<table class="min-w-full text-sm">

<thead class="bg-gray-50 text-gray-600">

<tr>

<th class="px-6 py-3 text-left">Ad</th>
<th class="px-6 py-3 text-left">Creative</th>
<th class="px-6 py-3 text-left">AdSet</th>
<th class="px-6 py-3 text-left">Status</th>
<th class="px-6 py-3 text-left">Impressions</th>
<th class="px-6 py-3 text-left">Clicks</th>
<th class="px-6 py-3 text-left">CTR</th>
<th class="px-6 py-3 text-left">Spend</th>
<th class="px-6 py-3 text-right">Actions</th>

</tr>

</thead>



<tbody class="divide-y">

@forelse($ads as $ad)

<tr class="hover:bg-gray-50 transition">


{{-- AD NAME --}}
<td class="px-6 py-4">

<div class="font-medium text-gray-900">
{{ $ad->name }}
</div>

@if($ad->meta_ad_id)
<div class="text-xs text-gray-400 mt-1">
Meta ID: {{ $ad->meta_ad_id }}
</div>
@endif

</td>



{{-- CREATIVE --}}
<td class="px-6 py-4">

@if($ad->creative)

<div class="flex items-center gap-3">

@if($ad->creative->image_url)

<img
src="{{ asset('storage/'.$ad->creative->image_url) }}"
class="w-10 h-10 rounded object-cover">

@endif

<div>

<div class="font-medium text-gray-800 text-sm">
{{ $ad->creative->name }}
</div>

</div>

</div>

@else

<span class="text-gray-400">No Creative</span>

@endif

</td>



{{-- ADSET --}}
<td class="px-6 py-4">

{{ $ad->adSet->name ?? '-' }}

</td>



{{-- STATUS --}}
<td class="px-6 py-4">

@switch($ad->status)

@case('ACTIVE')
<span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs">
Active
</span>
@break

@case('PAUSED')
<span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs">
Paused
</span>
@break

@case('PENDING_REVIEW')
<span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs">
In Review
</span>
@break

@case('DISAPPROVED')
<span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs">
Disapproved
</span>
@break

@case('ARCHIVED')
<span class="bg-gray-200 text-gray-700 px-2 py-1 rounded text-xs">
Archived
</span>
@break

@default
<span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">
Draft
</span>

@endswitch

</td>



{{-- IMPRESSIONS --}}
<td class="px-6 py-4">
{{ number_format($ad->impressions) }}
</td>



{{-- CLICKS --}}
<td class="px-6 py-4">
{{ number_format($ad->clicks) }}
</td>



{{-- CTR --}}
<td class="px-6 py-4">
{{ $ad->ctr }}%
</td>



{{-- SPEND --}}
<td class="px-6 py-4">
${{ number_format($ad->spend,2) }}
</td>



{{-- ACTIONS --}}
<td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">


<a
href="{{ route('admin.ads.preview',$ad) }}"
class="text-indigo-600 hover:text-indigo-800">
Preview
</a>


<a
href="{{ route('admin.ads.edit',$ad) }}"
class="text-blue-600 hover:text-blue-800">
Edit
</a>



@if($ad->status == 'DRAFT')

<form method="POST"
action="{{ route('admin.ads.activate',$ad) }}"
class="inline">

@csrf
@method('PATCH')

<button class="text-green-600 hover:text-green-800">
Publish
</button>

</form>

@endif



@if($ad->status == 'ACTIVE')

<form method="POST"
action="{{ route('admin.ads.pause',$ad) }}"
class="inline">

@csrf
@method('PATCH')

<button class="text-yellow-600 hover:text-yellow-800">
Pause
</button>

</form>

@endif



<form method="POST"
action="{{ route('admin.ads.duplicate',$ad) }}"
class="inline">

@csrf

<button class="text-purple-600 hover:text-purple-800">
Duplicate
</button>

</form>



<form method="POST"
action="{{ route('admin.ads.sync',$ad) }}"
class="inline">

@csrf

<button class="text-gray-600 hover:text-gray-800">
Sync
</button>

</form>



<form method="POST"
action="{{ route('admin.ads.destroy',$ad) }}"
class="inline">

@csrf
@method('DELETE')

<button
onclick="return confirm('Delete this ad?')"
class="text-red-600 hover:text-red-800">

Delete

</button>

</form>


</td>

</tr>

@empty

<tr>

<td colspan="9" class="text-center py-16">

<div class="text-gray-400 text-lg mb-3">
No ads found
</div>

<a
href="{{ route('admin.ads.create') }}"
class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700">

Create First Ad

</a>

</td>

</tr>

@endforelse

</tbody>

</table>



@if($ads->hasPages())

<div class="p-4 border-t">
{{ $ads->links() }}
</div>

@endif


</div>

</div>

@endsection