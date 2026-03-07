@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto py-6">

<h1 class="text-2xl font-bold mb-6">
Campaign: {{ $campaign->name }}
</h1>

<div class="bg-white shadow rounded-lg p-6 mb-6">

<p><strong>Objective:</strong> {{ $campaign->objective }}</p>
<p><strong>Status:</strong> {{ $campaign->status }}</p>
<p><strong>Meta ID:</strong> {{ $campaign->meta_id }}</p>

</div>

<h2 class="text-xl font-semibold mb-4">Ad Sets</h2>

@if($campaign->adSets->count())

<table class="w-full border rounded-lg overflow-hidden">

<thead class="bg-gray-100">
<tr>
<th class="p-3 text-left">Name</th>
<th class="p-3 text-left">Budget</th>
<th class="p-3 text-left">Status</th>
<th class="p-3 text-left">Meta ID</th>
</tr>
</thead>

<tbody>

@foreach($campaign->adSets as $adset)
<tr class="border-t">
<td class="p-3">{{ $adset->name }}</td>
<td class="p-3">{{ $adset->daily_budget / 100 }}</td>
<td class="p-3">{{ $adset->status }}</td>
<td class="p-3">{{ $adset->meta_id }}</td>
</tr>
@endforeach

</tbody>

</table>

@else

<p class="text-gray-500">No Ad Sets yet.</p>

@endif

</div>

@endsection