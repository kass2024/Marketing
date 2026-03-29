@extends('layouts.admin')

@section('content')

<div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 space-y-10">

{{-- HEADER --}}
<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-6">

<div>
<h1 class="text-3xl font-bold tracking-tight text-slate-900">
Platform Settings
</h1>
<p class="text-slate-500 mt-2 max-w-xl">
Configure your Parrot Canada Visa Consultant workspace, Meta integration, billing, and team access.
</p>
</div>

</div>



{{-- SETTINGS GRID --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">

@php
$card = 'group flex flex-col h-full rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm ring-1 ring-slate-900/5 transition hover:border-indigo-200/80 hover:shadow-md hover:ring-indigo-500/10';
@endphp

{{-- GENERAL SETTINGS --}}
<a href="{{ route('admin.settings.general') }}" class="{{ $card }}">

<div class="flex items-start gap-4">
<div class="shrink-0 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 p-3 text-white shadow-md">
<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
<path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.969a1 1 0 00.95.69h4.173c.969 0 1.371 1.24.588 1.81l-3.377 2.455a1 1 0 00-.364 1.118l1.287 3.969c.3.921-.755 1.688-1.54 1.118l-3.377-2.455a1 1 0 00-1.176 0l-3.377 2.455c-.785.57-1.84-.197-1.54-1.118l1.287-3.969a1 1 0 00-.364-1.118L2.049 9.396c-.783-.57-.38-1.81.588-1.81h4.173a1 1 0 00.95-.69l1.286-3.969z"/>
</svg>
</div>
<div class="min-w-0">
<h3 class="font-semibold text-slate-900 group-hover:text-indigo-700">
General Settings
</h3>
<p class="text-sm text-slate-500 mt-1 leading-relaxed">
Platform name, Xander contact details, and mail defaults
</p>
</div>
</div>
<span class="mt-auto pt-4 text-sm font-medium text-indigo-600 group-hover:text-indigo-700">
Open →
</span>
</a>



{{-- USER MANAGEMENT --}}
<a href="{{ route('admin.users.index') }}" class="{{ $card }}">

<div class="flex items-start gap-4">
<div class="shrink-0 rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 p-3 text-white shadow-md">
<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
</svg>
</div>
<div class="min-w-0">
<h3 class="font-semibold text-slate-900 group-hover:text-violet-700">
User Management
</h3>
<p class="text-sm text-slate-500 mt-1 leading-relaxed">
Admins, roles, and platform login accounts
</p>
</div>
</div>
<span class="mt-auto pt-4 text-sm font-medium text-violet-600 group-hover:text-violet-700">
Open →
</span>
</a>



{{-- META INTEGRATION --}}
<a href="{{ route('admin.settings.meta') }}" class="{{ $card }}">

<div class="flex items-start gap-4">
<div class="shrink-0 rounded-xl bg-gradient-to-br from-indigo-500 to-blue-600 p-3 text-white shadow-md">
<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
<path stroke-linecap="round" stroke-linejoin="round" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
</svg>
</div>
<div class="min-w-0">
<h3 class="font-semibold text-slate-900 group-hover:text-indigo-700">
Meta Integration
</h3>
<p class="text-sm text-slate-500 mt-1 leading-relaxed">
API keys, tokens, and Ads configuration
</p>
</div>
</div>
<span class="mt-auto pt-4 text-sm font-medium text-indigo-600 group-hover:text-indigo-700">
Open →
</span>
</a>



{{-- META BILLING --}}
<a href="{{ route('admin.settings.billing') }}" class="{{ $card }}">

<div class="flex items-start gap-4">
<div class="shrink-0 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 p-3 text-white shadow-md">
<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
<path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
</svg>
</div>
<div class="min-w-0">
<h3 class="font-semibold text-slate-900 group-hover:text-emerald-700">
Meta Billing
</h3>
<p class="text-sm text-slate-500 mt-1 leading-relaxed">
Ad account billing and payment methods
</p>
</div>
</div>
<span class="mt-auto pt-4 text-sm font-medium text-emerald-600 group-hover:text-emerald-700">
Open →
</span>
</a>

</div>

</div>

@endsection
