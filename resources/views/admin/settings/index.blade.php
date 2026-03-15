@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto py-10 px-6 space-y-10">

{{-- HEADER --}}
<div class="flex items-center justify-between flex-wrap gap-4">

<div>
<h1 class="text-3xl font-bold text-gray-900">
Platform Settings
</h1>

<p class="text-gray-500 mt-1">
Manage system configuration, integrations and platform tools.
</p>
</div>

<a href="{{ route('admin.dashboard') }}"
class="inline-flex items-center gap-2 bg-gray-800 text-white px-5 py-2 rounded-lg shadow hover:bg-gray-900 transition">

<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M3 12h18M12 3l9 9-9 9"/>
</svg>

Back to Dashboard

</a>

</div>



{{-- SETTINGS GRID --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">



{{-- GENERAL SETTINGS --}}
<a href="{{ route('admin.settings.general') }}"
class="group bg-white border rounded-2xl p-6 shadow hover:shadow-xl transition">

<div class="flex items-center gap-4">

<div class="bg-blue-100 text-blue-600 p-3 rounded-xl">

<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6"
fill="none" viewBox="0 0 24 24" stroke="currentColor">

<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.969a1 1 0 00.95.69h4.173c.969 0 1.371 1.24.588 1.81l-3.377 2.455a1 1 0 00-.364 1.118l1.287 3.969c.3.921-.755 1.688-1.54 1.118l-3.377-2.455a1 1 0 00-1.176 0l-3.377 2.455c-.785.57-1.84-.197-1.54-1.118l1.287-3.969a1 1 0 00-.364-1.118L2.049 9.396c-.783-.57-.38-1.81.588-1.81h4.173a1 1 0 00.95-.69l1.286-3.969z"/>

</svg>

</div>

<div>
<h3 class="font-semibold text-gray-900 group-hover:text-blue-600">
General Settings
</h3>

<p class="text-sm text-gray-500">
Platform name, email and configuration
</p>
</div>

</div>

</a>



{{-- META INTEGRATION --}}
<a href="{{ route('admin.settings.meta') }}"
class="group bg-white border rounded-2xl p-6 shadow hover:shadow-xl transition">

<div class="flex items-center gap-4">

<div class="bg-indigo-100 text-indigo-600 p-3 rounded-xl">

<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6"
fill="none" viewBox="0 0 24 24" stroke="currentColor">

<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M16 8v8M8 8v8M4 6h16M4 18h16"/>

</svg>

</div>

<div>

<h3 class="font-semibold text-gray-900 group-hover:text-indigo-600">
Meta Integration
</h3>

<p class="text-sm text-gray-500">
Configure Meta Ads and API connection
</p>

</div>

</div>

</a>



{{-- META BILLING --}}
<a href="{{ route('admin.settings.billing') }}"
class="group bg-white border rounded-2xl p-6 shadow hover:shadow-xl transition">

<div class="flex items-center gap-4">

<div class="bg-green-100 text-green-600 p-3 rounded-xl">

<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6"
fill="none" viewBox="0 0 24 24" stroke="currentColor">

<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M12 8c-3.866 0-7 1.343-7 3s3.134 3 7 3 7-1.343 7-3-3.134-3-7-3z"/>

</svg>

</div>

<div>

<h3 class="font-semibold text-gray-900 group-hover:text-green-600">
Meta Billing
</h3>

<p class="text-sm text-gray-500">
Ad account billing and payment method
</p>

</div>

</div>

</a>



{{-- TEAM MANAGEMENT --}}
<a href="{{ route('admin.settings.team') }}"
class="group bg-white border rounded-2xl p-6 shadow hover:shadow-xl transition">

<div class="flex items-center gap-4">

<div class="bg-purple-100 text-purple-600 p-3 rounded-xl">

<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6"
fill="none" viewBox="0 0 24 24" stroke="currentColor">

<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m0-4A4 4 0 109 4a4 4 0 000 8zm8 0a4 4 0 10-8 0 4 4 0 008 0z"/>

</svg>

</div>

<div>

<h3 class="font-semibold text-gray-900 group-hover:text-purple-600">
Team Management
</h3>

<p class="text-sm text-gray-500">
Manage admins and permissions
</p>

</div>

</div>

</a>



{{-- SYSTEM --}}
<a href="{{ route('admin.system.index') }}"
class="group bg-white border rounded-2xl p-6 shadow hover:shadow-xl transition">

<div class="flex items-center gap-4">

<div class="bg-red-100 text-red-600 p-3 rounded-xl">

<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6"
fill="none" viewBox="0 0 24 24" stroke="currentColor">

<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M9.75 17L9 20l-3-1 1.75-2.25M14.25 17L15 20l3-1-1.75-2.25M12 3v12"/>

</svg>

</div>

<div>

<h3 class="font-semibold text-gray-900 group-hover:text-red-600">
System Tools
</h3>

<p class="text-sm text-gray-500">
Logs, queue, cache and system information
</p>

</div>

</div>

</a>



</div>

</div>

@endsection