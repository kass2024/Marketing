@extends('layouts.app')

@section('content')

<div class="max-w-2xl mx-auto py-10">

<h1 class="text-2xl font-bold mb-6">Creative Preview</h1>

<div class="bg-white rounded-xl shadow overflow-hidden">

@if($creative->image_url)
<img src="{{ asset('storage/'.$creative->image_url) }}"
class="w-full object-cover">
@endif

<div class="p-6">

<h2 class="text-xl font-semibold mb-2">
{{ $creative->title }}
</h2>

<p class="text-gray-600 mb-4">
{{ $creative->body }}
</p>

@if($creative->call_to_action)
<button class="bg-blue-600 text-white px-5 py-2 rounded-lg">
{{ str_replace('_',' ',$creative->call_to_action) }}
</button>
@endif

</div>

</div>

</div>

@endsection