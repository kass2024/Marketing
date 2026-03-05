@extends('layouts.app')

@section('content')

<div class="max-w-6xl mx-auto py-10">

<h1 class="text-2xl font-bold mb-6">Creatives</h1>

<a href="{{ route('admin.creatives.create') }}"
class="bg-blue-600 text-white px-4 py-2 rounded-lg">
Create Creative
</a>

<table class="w-full mt-6 border">

<thead class="bg-gray-100">
<tr>
<th class="p-3 text-left">Name</th>
<th>Headline</th>
<th>Actions</th>
</tr>
</thead>

<tbody>

@foreach($creatives as $creative)

<tr class="border-t">
<td class="p-3">{{ $creative->name }}</td>
<td>{{ $creative->headline }}</td>

<td>

<a href="{{ route('admin.creatives.edit',$creative->id) }}"
class="text-blue-600">Edit</a>

</td>
</tr>

@endforeach

</tbody>

</table>

</div>

@endsection