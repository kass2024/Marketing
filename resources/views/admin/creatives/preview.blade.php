@extends('layouts.admin')

@section('content')

<div class="max-w-4xl mx-auto py-10">

<h1 class="text-2xl font-bold mb-8 text-center">
Creative Preview
</h1>


<div class="bg-white rounded-2xl shadow-lg max-w-xl mx-auto overflow-hidden border">


{{-- HEADER --}}
<div class="p-4 flex items-center gap-3 border-b">

<div class="w-10 h-10 rounded-full bg-gray-200"></div>

<div>

<div class="font-semibold">
{{ config('app.name') }}
</div>

<div class="text-xs text-gray-500">
Sponsored
</div>

</div>

</div>



{{-- TEXT --}}
@if($creative->body)

<div class="p-4 text-gray-700 text-sm leading-relaxed">
{!! nl2br(e($creative->body)) !!}
</div>

@endif



{{-- IMAGE --}}
@if($creative->image_url)

<img
src="{{ $creative->image_url }}"
class="w-full object-cover">

@endif



{{-- LINK SECTION --}}
<div class="p-4 border-t">

@if($creative->headline)

<div class="font-semibold text-gray-900 text-sm">
{{ $creative->headline }}
</div>

@endif


@if($creative->destination_url)

<div class="text-xs text-gray-500 mt-1 truncate">
{{ parse_url($creative->destination_url, PHP_URL_HOST) }}
</div>

@endif



{{-- CTA --}}
@if($creative->call_to_action)

<div class="mt-4">

<a
href="{{ $creative->destination_url ?? '#' }}"
target="_blank"
class="inline-block bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg">

{{ str_replace('_',' ',$creative->call_to_action) }}

</a>

</div>

@endif

</div>

</div>



{{-- ACTIONS --}}
<div class="text-center mt-8">

<a
href="{{ route('admin.creatives.index') }}"
class="text-blue-600 hover:underline">

← Back to Creatives

</a>

</div>

</div>

@endsection