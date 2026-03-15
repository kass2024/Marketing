@extends('layouts.app')

@section('content')

<div class="max-w-6xl mx-auto py-10 space-y-8">

<h1 class="text-2xl font-bold text-gray-900">
Team Management
</h1>


<div class="bg-white shadow rounded-xl border">

<table class="w-full">

<thead class="bg-gray-50">

<tr>

<th class="p-4 text-left text-sm">Name</th>
<th class="p-4 text-left text-sm">Email</th>
<th class="p-4 text-left text-sm">Role</th>
<th class="p-4 text-right text-sm">Actions</th>

</tr>

</thead>

<tbody>

@foreach($users as $user)

<tr class="border-t">

<td class="p-4">{{ $user->name }}</td>
<td class="p-4">{{ $user->email }}</td>
<td class="p-4">{{ $user->role }}</td>

<td class="p-4 text-right">

<form method="POST" action="{{ route('admin.settings.team.destroy',$user) }}">

@csrf
@method('DELETE')

<button class="text-red-600 hover:underline">
Remove
</button>

</form>

</td>

</tr>

@endforeach

</tbody>

</table>

</div>

</div>

@endsection