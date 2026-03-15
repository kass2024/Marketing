@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto py-10 space-y-8">

{{-- =========================================================
HEADER
========================================================= --}}
<div class="flex items-center justify-between flex-wrap gap-4">

<div>
<h1 class="text-2xl font-bold text-gray-900">
User Management
</h1>

<p class="text-sm text-gray-500 mt-1">
Manage platform users, roles and permissions
</p>
</div>

<div class="flex gap-3">

{{-- BACK TO DASHBOARD --}}
<a href="{{ route('admin.dashboard') }}"
class="inline-flex items-center gap-2 bg-gray-700 hover:bg-gray-800 text-white px-4 py-2 rounded-lg shadow transition">

<span>←</span>
<span>Dashboard</span>

</a>

{{-- CREATE USER --}}
<a href="{{ route('admin.users.create') }}"
class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg shadow transition">

<span class="text-lg">＋</span>
<span>Create User</span>

</a>

</div>

</div>



{{-- =========================================================
USERS TABLE
========================================================= --}}
<div class="bg-white border border-gray-200 rounded-2xl shadow overflow-hidden">

<div class="overflow-x-auto">

<table class="min-w-full text-sm">

{{-- TABLE HEADER --}}
<thead class="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">

<tr>

<th class="px-6 py-4 text-left">ID</th>
<th class="px-6 py-4 text-left">Name</th>
<th class="px-6 py-4 text-left">Email</th>
<th class="px-6 py-4 text-left">WhatsApp</th>
<th class="px-6 py-4 text-left">Password</th>
<th class="px-6 py-4 text-left">Role</th>
<th class="px-6 py-4 text-left">Status</th>
<th class="px-6 py-4 text-left">Created</th>
<th class="px-6 py-4 text-right">Actions</th>

</tr>

</thead>



{{-- TABLE BODY --}}
<tbody class="divide-y divide-gray-200">

@forelse($users as $user)

<tr class="hover:bg-gray-50 transition">

{{-- ID --}}
<td class="px-6 py-4 font-semibold text-gray-700">
#{{ $user->id }}
</td>


{{-- NAME --}}
<td class="px-6 py-4 font-medium text-gray-900">
{{ $user->name }}
</td>


{{-- EMAIL --}}
<td class="px-6 py-4 text-gray-600">
{{ $user->email }}
</td>


{{-- WHATSAPP --}}
<td class="px-6 py-4 text-gray-600">
{{ $user->whatsapp_number ?? '-' }}
</td>


{{-- PASSWORD --}}
<td class="px-6 py-4 text-xs font-mono text-gray-400">
{{ Str::limit($user->password,25) }}
</td>



{{-- ROLE --}}
<td class="px-6 py-4">

@php

$roleColor = match($user->role) {

'super_admin' => 'bg-purple-100 text-purple-700',
'agent' => 'bg-blue-100 text-blue-700',
'client' => 'bg-green-100 text-green-700',

default => 'bg-gray-100 text-gray-700'

};

@endphp

<span class="px-3 py-1 rounded-full text-xs font-semibold {{ $roleColor }}">

{{ Str::headline($user->role) }}

</span>

</td>



{{-- STATUS --}}
<td class="px-6 py-4">

@if($user->status === 'active')

<span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
Active
</span>

@else

<span class="px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
Suspended
</span>

@endif

</td>



{{-- CREATED --}}
<td class="px-6 py-4 text-xs text-gray-500">
{{ $user->created_at?->format('d M Y') }}
</td>



{{-- ACTIONS --}}
<td class="px-6 py-4 text-right">

<div class="flex justify-end gap-2">

{{-- EDIT --}}
<a href="{{ route('admin.users.edit',$user->id) }}"
class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">

Edit

</a>


{{-- DELETE --}}
<form method="POST"
action="{{ route('admin.users.destroy',$user->id) }}"
onsubmit="return confirm('Delete this user?')">

@csrf
@method('DELETE')

<button
class="px-3 py-1.5 text-xs bg-red-600 text-white rounded-md hover:bg-red-700 transition">

Delete

</button>

</form>

</div>

</td>

</tr>

@empty


{{-- EMPTY STATE --}}
<tr>

<td colspan="9"
class="px-6 py-16 text-center">

<div class="flex flex-col items-center gap-3 text-gray-400">

<div class="text-4xl">👥</div>

<p class="text-sm">
No users found
</p>

<a href="{{ route('admin.users.create') }}"
class="text-blue-600 text-sm hover:underline">

Create the first user

</a>

</div>

</td>

</tr>

@endforelse

</tbody>

</table>

</div>



{{-- =========================================================
PAGINATION
========================================================= --}}
@if($users->hasPages())

<div class="px-6 py-4 border-t bg-gray-50">

{{ $users->links() }}

</div>

@endif


</div>

</div>

@endsection