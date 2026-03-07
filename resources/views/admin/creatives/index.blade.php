@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto space-y-8 py-10">

{{-- ================= HEADER ================= --}}
<div class="flex items-center justify-between flex-wrap gap-4">

<div>
<h1 class="text-2xl font-bold text-gray-900">
Creative Library
</h1>

<p class="text-sm text-gray-500 mt-1">
Manage reusable ad creatives for your campaigns.
</p>
</div>


<a
href="{{ route('admin.creatives.create') }}"
class="inline-flex items-center gap-2 bg-blue-600 text-white px-5 py-2 rounded-lg shadow hover:bg-blue-700 transition">

<span class="text-lg">＋</span>
<span>Create Creative</span>

</a>

</div>



{{-- ================= METRICS ================= --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">

<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-sm text-gray-500">Total Creatives</p>
<p class="text-xl font-bold">{{ $creatives->total() ?? $creatives->count() }}</p>
</div>


<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-sm text-gray-500">Active</p>
<p class="text-xl font-bold text-green-600">
{{ $creatives->where('status','ACTIVE')->count() }}
</p>
</div>


<div class="bg-white p-5 rounded-xl shadow border">
<p class="text-sm text-gray-500">Draft</p>
<p class="text-xl font-bold text-gray-600">
{{ $creatives->where('status','DRAFT')->count() }}
</p>
</div>

</div>



{{-- ================= CREATIVE TABLE ================= --}}
<div class="bg-white rounded-xl shadow overflow-hidden">

<table class="min-w-full text-sm">

<thead class="bg-gray-50 text-gray-600">

<tr>

<th class="px-6 py-3 text-left">Preview</th>
<th class="px-6 py-3 text-left">Creative</th>
<th class="px-6 py-3 text-left">Headline</th>
<th class="px-6 py-3 text-left">Status</th>
<th class="px-6 py-3 text-left">Created</th>
<th class="px-6 py-3 text-right">Actions</th>

</tr>

</thead>



<tbody class="divide-y">

@forelse($creatives as $creative)

<tr class="hover:bg-gray-50 transition">


{{-- PREVIEW --}}
<td class="px-6 py-4">

@if(!empty($creative->image_url))

<img
src="{{ $creative->image_url }}"
class="w-16 h-16 object-cover rounded"
/>

@elseif(!empty($creative->video_url))

<div class="w-16 h-16 bg-gray-200 flex items-center justify-center rounded text-xs">
Video
</div>

@else

<div class="w-16 h-16 bg-gray-100 flex items-center justify-center rounded text-gray-400 text-xs">
No Media
</div>

@endif

</td>


{{-- NAME --}}
<td class="px-6 py-4">

<div class="font-medium">
{{ $creative->name }}
</div>

@if(!empty($creative->meta_id))
<div class="text-xs text-gray-400 mt-1">
Meta ID: {{ $creative->meta_id }}
</div>
@endif

</td>


{{-- HEADLINE --}}
<td class="px-6 py-4">
{{ $creative->headline ?? '-' }}
</td>


{{-- STATUS --}}
<td class="px-6 py-4">

@if($creative->status === 'ACTIVE')

<span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs">
Active
</span>

@elseif($creative->status === 'PAUSED')

<span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs">
Paused
</span>

@else

<span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">
Draft
</span>

@endif

</td>


{{-- CREATED --}}
<td class="px-6 py-4 text-gray-500">
{{ optional($creative->created_at)->format('d M Y') }}
</td>


{{-- ACTIONS --}}
<td class="px-6 py-4 text-right space-x-4 whitespace-nowrap">

<a
href="{{ route('admin.creatives.preview',$creative->id) }}"
class="text-indigo-600 hover:text-indigo-800">
Preview
</a>

<a
href="{{ route('admin.creatives.edit',$creative->id) }}"
class="text-blue-600 hover:text-blue-800">
Edit
</a>
<form
action="{{ route('admin.creatives.destroy',$creative->id) }}"
method="POST"
class="inline"
onsubmit="return confirm('Delete this creative?');"
>

@csrf
@method('DELETE')

<button
type="submit"
class="text-red-600 hover:text-red-800">
Delete
</button>

</form>

</td>

</tr>

@empty


<tr>

<td colspan="6" class="text-center py-16">

<div class="text-gray-400 text-lg">
No creatives yet
</div>

<a
href="{{ route('admin.creatives.create') }}"
class="mt-4 inline-block bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700">

Create First Creative

</a>

</td>

</tr>

@endforelse

</tbody>

</table>



{{-- PAGINATION --}}
@if(method_exists($creatives,'links'))

<div class="p-4 border-t">
{{ $creatives->links() }}
</div>

@endif

</div>

</div>

@endsection