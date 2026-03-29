@extends('layouts.admin')

@section('content')

<div class="max-w-6xl mx-auto py-10 space-y-8">

<h1 class="text-2xl font-bold text-gray-900">
System Tools
</h1>


<div class="grid grid-cols-3 gap-6">

<div class="bg-white border rounded-xl p-6 shadow">

<h3 class="font-semibold">Queue</h3>

<a href="{{ route('admin.system.queue') }}"
class="text-blue-600 text-sm">

View Queue

</a>

</div>


<div class="bg-white border rounded-xl p-6 shadow">

<h3 class="font-semibold">Cache</h3>

<form method="POST" action="{{ route('admin.system.cache.clear') }}">

@csrf

<button
class="mt-2 bg-red-500 text-white px-4 py-2 rounded-lg">

Clear Cache

</button>

</form>

</div>


<div class="bg-white border rounded-xl p-6 shadow">

<h3 class="font-semibold">System Info</h3>

<a href="{{ route('admin.system.info') }}"
class="text-blue-600 text-sm">

View Info

</a>

</div>

</div>

</div>

@endsection