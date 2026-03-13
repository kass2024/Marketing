@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto py-10 space-y-8">

{{-- HEADER --}}
<div class="flex items-center justify-between flex-wrap gap-4">

<div>
<h1 class="text-2xl font-bold text-gray-900">
Ads Manager
</h1>

<p class="text-sm text-gray-500">
Create, publish and monitor ad delivery performance.
</p>
</div>

<div class="flex gap-3">

<a
href="{{ route('admin.dashboard') }}"
class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800">
Dashboard
</a>

<a
href="{{ route('admin.ads.create') }}"
class="inline-flex items-center gap-2 bg-blue-600 text-white px-5 py-2 rounded-lg shadow hover:bg-blue-700">

<span>＋</span>
<span>Create Ad</span>

</a>

</div>

</div>



{{-- ALERTS --}}
@if(session('success'))
<div class="bg-green-100 text-green-700 p-4 rounded-lg">
{{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="bg-red-100 text-red-700 p-4 rounded-lg">
{{ session('error') }}
</div>
@endif



{{-- METRICS --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-4">

<div class="bg-white p-6 rounded-xl shadow border">
<p class="text-sm text-gray-500">Total Ads</p>
<p class="text-2xl font-bold">{{ $ads->total() }}</p>
</div>

<div class="bg-white p-6 rounded-xl shadow border">
<p class="text-sm text-gray-500">Active Ads</p>
<p class="text-2xl font-bold text-green-600">
{{ $ads->getCollection()->where('status','ACTIVE')->count() }}
</p>
</div>

<div class="bg-white p-6 rounded-xl shadow border">
<p class="text-sm text-gray-500">Total Spend</p>
<p class="text-2xl font-bold text-blue-600">
${{ number_format($ads->getCollection()->sum('spend'),2) }}
</p>
</div>

<div class="bg-white p-6 rounded-xl shadow border">
<p class="text-sm text-gray-500">Total Clicks</p>
<p class="text-2xl font-bold text-purple-600">
{{ number_format($ads->getCollection()->sum('clicks')) }}
</p>
</div>

</div>



{{-- TABLE --}}
<div class="bg-white rounded-xl shadow overflow-hidden">

<table class="min-w-full text-sm">

<thead class="bg-gray-50 text-gray-600">

<tr>
<th class="px-6 py-3 text-left">Ad</th>
<th class="px-6 py-3 text-left">Creative</th>
<th class="px-6 py-3 text-left">AdSet</th>
<th class="px-6 py-3 text-left">Delivery</th>
<th class="px-6 py-3 text-left">Impressions</th>
<th class="px-6 py-3 text-left">Clicks</th>
<th class="px-6 py-3 text-left">CTR</th>
<th class="px-6 py-3 text-left">Spend</th>
<th class="px-6 py-3 text-right">Actions</th>
</tr>

</thead>


<tbody class="divide-y">

@forelse($ads as $ad)

<tr class="hover:bg-gray-50">

{{-- AD --}}
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
src="{{ $ad->creative->image_url }}"
class="w-10 h-10 rounded object-cover border">
@endif

<div class="text-sm font-medium">
{{ $ad->creative->name }}
</div>

</div>

@endif

</td>



{{-- ADSET --}}
<td class="px-6 py-4">
{{ $ad->adSet?->name ?? '-' }}
</td>



{{-- DELIVERY STATUS --}}
<td class="px-6 py-4">

@switch($ad->status)

@case('ACTIVE')
<span class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded">
Active
</span>
@break

@case('PAUSED')
<span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs rounded">
Paused
</span>
@break

@case('PENDING_REVIEW')
<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded">
In Review
</span>
@break

@case('DISAPPROVED')
<span class="px-2 py-1 bg-red-100 text-red-700 text-xs rounded">
Disapproved
</span>
@break

@default
<span class="px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded">
Draft
</span>

@endswitch

</td>



{{-- IMPRESSIONS --}}
<td class="px-6 py-4">
{{ number_format($ad->impressions ?? 0) }}
</td>



{{-- CLICKS --}}
<td class="px-6 py-4">
{{ number_format($ad->clicks ?? 0) }}
</td>



{{-- CTR --}}
<td class="px-6 py-4">

@php
$ctr = $ad->ctr ?? 0;
@endphp

<span class="font-semibold
@if($ctr > 3) text-green-600
@elseif($ctr > 1) text-yellow-600
@else text-gray-600
@endif">

{{ number_format($ctr,2) }}%

</span>

</td>



{{-- SPEND --}}
<td class="px-6 py-4 font-semibold text-gray-800">
${{ number_format($ad->spend ?? 0,2) }}
</td>


{{-- ACTIONS --}}
<td class="px-6 py-4 text-right whitespace-nowrap">

<div class="flex justify-end gap-3 text-sm">

<a href="{{ route('admin.ads.preview',$ad) }}"
class="text-indigo-600 hover:text-indigo-800">
Preview
</a>

<a href="{{ route('admin.ads.edit',$ad) }}"
class="text-blue-600 hover:text-blue-800">
Edit
</a>


{{-- PUBLISH --}}
@if($ad->status !== 'ACTIVE')

<form method="POST"
action="{{ route('admin.ads.activate',$ad) }}">

@csrf
@method('PATCH')

<button
type="submit"
class="text-green-600 hover:text-green-800 font-medium">

Publish

</button>

</form>

@endif


{{-- PAUSE --}}
@if($ad->status === 'ACTIVE')

<form method="POST"
action="{{ route('admin.ads.pause',$ad) }}">

@csrf
@method('PATCH')

<button
type="submit"
class="text-yellow-600 hover:text-yellow-800 font-medium">

Pause

</button>

</form>

@endif


<form method="POST"
action="{{ route('admin.ads.sync',$ad) }}">
@csrf

<button class="text-gray-600 hover:text-gray-800">
Sync
</button>

</form>


<form method="POST"
action="{{ route('admin.ads.duplicate',$ad) }}">
@csrf

<button class="text-purple-600 hover:text-purple-800">
Duplicate
</button>

</form>


<form method="POST"
action="{{ route('admin.ads.destroy',$ad) }}">
@csrf
@method('DELETE')

<button
onclick="return confirm('Delete this ad?')"
class="text-red-600 hover:text-red-800">

Delete

</button>

</form>

</div>

</td>

</tr>

@empty

<tr>

<td colspan="9" class="text-center py-16 text-gray-400">

<div class="flex flex-col items-center gap-4">

<p class="text-lg">
No ads created yet
</p>

<a
href="{{ route('admin.ads.create') }}"
class="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700">

Create Your First Ad

</a>

</div>

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