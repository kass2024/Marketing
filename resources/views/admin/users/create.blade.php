@extends('layouts.admin')

@section('content')

<div class="max-w-4xl mx-auto px-4 sm:px-6 py-10 space-y-8">

<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
<div>
<h1 class="text-3xl font-bold tracking-tight text-slate-900">
Create user
</h1>
<p class="text-slate-500 mt-2">
Add a new platform account with role and secure password.
</p>
</div>
<x-admin.page-back :href="route('admin.users.index')" label="Back to users" />
</div>



{{-- ERRORS --}}
@if ($errors->any())

<div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl">

<ul class="list-disc ml-6 space-y-1">

@foreach ($errors->all() as $error)
<li>{{ $error }}</li>
@endforeach

</ul>

</div>

@endif



<div class="rounded-2xl border border-slate-200/80 bg-white p-8 shadow-sm ring-1 ring-slate-900/5">

<form method="POST"
action="{{ route('admin.users.store') }}"
class="space-y-6">

@csrf



{{-- NAME --}}
<div>
<label class="block text-sm font-semibold text-gray-700 mb-2">
Full Name
</label>

<input type="text"
name="name"
value="{{ old('name') }}"
class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
required>
</div>



{{-- EMAIL --}}
<div>
<label class="block text-sm font-semibold text-gray-700 mb-2">
Email Address
</label>

<input type="email"
name="email"
value="{{ old('email') }}"
class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
required>
</div>



{{-- WHATSAPP --}}
<div>
<label class="block text-sm font-semibold text-gray-700 mb-2">
WhatsApp Number
</label>

<input type="text"
name="whatsapp_number"
value="{{ old('whatsapp_number') }}"
class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
</div>



{{-- PASSWORD WITH EYE --}}
<div x-data="{ show:false }">

<label class="block text-sm font-semibold text-gray-700 mb-2">
Password
</label>

<div class="relative">

<input
x-bind:type="show ? 'text' : 'password'"
name="password"
class="w-full border rounded-xl px-4 py-3 pr-12 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
required
>

<button
type="button"
@click="show = !show"
class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">

{{-- Eye icon --}}
<svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
fill="none" viewBox="0 0 24 24" stroke="currentColor">

<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />

<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />

</svg>


{{-- Eye off --}}
<svg x-show="show" xmlns="http://www.w3.org/2000/svg"
class="h-5 w-5" fill="none"
viewBox="0 0 24 24" stroke="currentColor">

<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95M6.1 6.1A9.956 9.956 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.96 9.96 0 01-4.043 5.058M15 12a3 3 0 00-4.243-2.828M3 3l18 18" />

</svg>

</button>

</div>

<p class="text-xs text-gray-500 mt-2">
Password will be securely hashed before storing in the database.
</p>

</div>



{{-- ROLE --}}
<div>
<label class="block text-sm font-semibold text-gray-700 mb-2">
User Role
</label>

<select name="role"
class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">

<option value="client">Client</option>
<option value="agent">Agent</option>
<option value="super_admin">Super Admin</option>

</select>

</div>



{{-- SUBMIT --}}
<div class="pt-4">

<button
type="submit"
class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-3 text-sm font-semibold text-white shadow-md shadow-blue-500/25 transition hover:from-blue-700 hover:to-indigo-700">

Create user

</button>

</div>

</form>

</div>

</div>

@endsection