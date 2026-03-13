@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto space-y-8 py-10">

{{-- =========================================================
HEADER
========================================================= --}}
<div class="flex items-center justify-between flex-wrap gap-4">

<div>
<h1 class="text-2xl font-bold text-gray-900">
Creative Library
</h1>

<p class="text-sm text-gray-500">
Monitor and manage Meta ad creatives synced with Facebook Ads.
</p>
</div>

<div class="flex gap-3">

<a href="{{ route('admin.dashboard') }}"
class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800">
Dashboard
</a>

<a href="{{ route('admin.creatives.create') }}"
class="inline-flex items-center gap-2 bg-blue-600 text-white px-5 py-2 rounded-lg shadow hover:bg-blue-700">

<span>＋</span>
<span>Create Creative</span>

</a>

</div>

</div>



{{-- =========================================================
METRICS
========================================================= --}}
<div class="grid md:grid-cols-5 gap-4">

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-sm text-gray-500">Total</p>
<p class="text-xl font-bold">
{{ $creatives->count() }}
</p>
</div>

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-sm text-gray-500">Approved</p>
<p class="text-xl font-bold text-green-600">
{{ $creatives->where('review_status','APPROVED')->count() }}
</p>
</div>

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-sm text-gray-500">Pending Review</p>
<p class="text-xl font-bold text-yellow-600">
{{ $creatives->where('review_status','PENDING_REVIEW')->count() }}
</p>
</div>

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-sm text-gray-500">Rejected</p>
<p class="text-xl font-bold text-red-600">
{{ $creatives->where('review_status','DISAPPROVED')->count() }}
</p>
</div>

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-sm text-gray-500">Active</p>
<p class="text-xl font-bold text-blue-600">
{{ $creatives->where('effective_status','ACTIVE')->count() }}
</p>
</div>

</div>



{{-- =========================================================
CREATIVE TABLE
========================================================= --}}
<div class="bg-white rounded-xl shadow overflow-hidden">

<table class="min-w-full text-sm">

<thead class="bg-gray-50 text-gray-600">

<tr>

<th class="px-6 py-3 text-left">Preview</th>
<th class="px-6 py-3 text-left">Creative</th>
<th class="px-6 py-3 text-left">Headline</th>
<th class="px-6 py-3 text-left">Review</th>
<th class="px-6 py-3 text-left">Delivery</th>
<th class="px-6 py-3 text-left">Spend</th>
<th class="px-6 py-3 text-left">Impressions</th>
<th class="px-6 py-3 text-left">Created</th>
<th class="px-6 py-3 text-right">Actions</th>

</tr>

</thead>


<tbody class="divide-y">

@forelse($creatives as $creative)

<tr class="hover:bg-gray-50">


{{-- PREVIEW --}}
<td class="px-6 py-4">

@if($creative->image_url)

<img src="{{ $creative->image_url }}"
class="w-16 h-16 object-cover rounded"/>

@elseif($creative->video_url)

<div class="w-16 h-16 bg-gray-200 flex items-center justify-center text-xs rounded">
Video
</div>

@else

<div class="w-16 h-16 bg-gray-100 flex items-center justify-center text-xs text-gray-400 rounded">
No Media
</div>

@endif

</td>



{{-- CREATIVE NAME --}}
<td class="px-6 py-4">

<div class="font-medium">
{{ $creative->name }}
</div>

@if($creative->meta_id)

<div class="text-xs text-gray-400">
Meta ID: {{ $creative->meta_id }}
</div>

@endif

</td>



{{-- HEADLINE --}}
<td class="px-6 py-4">

{{ $creative->headline ?? '-' }}

</td>



{{-- REVIEW STATUS --}}
<td class="px-6 py-4">

@if($creative->review_status == 'APPROVED')

<span class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded">
Approved
</span>

@elseif($creative->review_status == 'PENDING_REVIEW')

<span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs rounded">
Pending
</span>

@elseif($creative->review_status == 'DISAPPROVED')

<span class="px-2 py-1 bg-red-100 text-red-700 text-xs rounded">
Rejected
</span>

@else

<span class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded">
Draft
</span>

@endif

</td>



{{-- DELIVERY --}}
<td class="px-6 py-4">

@if($creative->effective_status == 'ACTIVE')

<span class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded">
Active
</span>

@elseif($creative->effective_status == 'PAUSED')

<span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs rounded">
Paused
</span>

@else

<span class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded">
Inactive
</span>

@endif

</td>



{{-- SPEND --}}
<td class="px-6 py-4 text-gray-600">

@if($creative->spend)

${{ number_format($creative->spend,2) }}

@else

—

@endif

</td>



{{-- IMPRESSIONS --}}
<td class="px-6 py-4 text-gray-600">

{{ $creative->impressions ?? '—' }}

</td>



{{-- DATE --}}
<td class="px-6 py-4 text-gray-500">

{{ optional($creative->created_at)->format('d M Y') }}

</td>



{{-- =========================================================
ACTIONS
========================================================= --}}
<td class="px-6 py-4 text-right whitespace-nowrap">

<div class="flex items-center justify-end gap-3 text-sm">

{{-- Preview --}}
<a href="{{ route('admin.creatives.preview',$creative->id) }}"
class="text-indigo-600 hover:text-indigo-800">
Preview
</a>


{{-- Edit --}}
<a href="{{ route('admin.creatives.edit',$creative->id) }}"
class="text-blue-600 hover:text-blue-800">
Edit
</a>


{{-- Activate --}}
@if($creative->effective_status !== 'ACTIVE')

<form method="POST"
action="{{ route('admin.creatives.activate',$creative->id) }}">

@csrf
@method('PATCH')

<button class="text-green-600 hover:text-green-800">
Activate
</button>

</form>

@endif


{{-- Pause --}}
@if($creative->effective_status === 'ACTIVE')

<form method="POST"
action="{{ route('admin.creatives.pause',$creative->id) }}">

@csrf
@method('PATCH')

<button class="text-yellow-600 hover:text-yellow-800">
Pause
</button>

</form>

@endif


{{-- Sync Meta --}}
<form method="POST"
action="{{ route('admin.creatives.sync',$creative->id) }}">

@csrf

<button class="text-purple-600 hover:text-purple-800">
Sync
</button>

</form>


{{-- Delete --}}
<form method="POST"
action="{{ route('admin.creatives.destroy',$creative->id) }}"
onsubmit="return confirm('Delete this creative?');">

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

<tr>

<td colspan="9" class="text-center py-16 text-gray-400">

<div class="flex flex-col items-center gap-4">

<p class="text-lg font-medium">
No creatives created yet
</p>

<a href="{{ route('admin.creatives.create') }}"
class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">

Create First Creative

</a>

</div>

</td>

</tr>

@endforelse

</tbody>

</table>

</div>



{{-- PAGINATION --}}
@if(method_exists($creatives,'links'))

<div>
{{ $creatives->links() }}
</div>

@endif

</div>

@endsection