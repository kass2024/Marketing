@extends('layouts.app')

@section('content')

<div class="space-y-8">

{{-- ================= PAGE HEADER ================= --}}
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">

    <div>
        <h1 class="text-3xl font-bold text-gray-900">
            Campaigns
        </h1>

        <p class="text-gray-500 mt-2">
            Manage your advertising campaigns across Meta platforms.
        </p>
    </div>

    <a href="{{ route('admin.campaigns.create') }}"
       class="inline-flex items-center gap-3 bg-blue-600 text-white px-6 py-3 rounded-xl shadow hover:bg-blue-700 transition">

        <span class="text-lg">＋</span>
        New Campaign
    </a>

</div>


{{-- ================= METRICS CARDS ================= --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">

<div class="bg-white p-6 rounded-xl shadow border">
<p class="text-sm text-gray-500">Total Campaigns</p>
<p class="text-2xl font-bold mt-1">{{ $campaigns->total() }}</p>
</div>

<div class="bg-white p-6 rounded-xl shadow border">
<p class="text-sm text-gray-500">Active Campaigns</p>
<p class="text-2xl font-bold text-green-600 mt-1">
{{ $campaigns->where('status','ACTIVE')->count() }}
</p>
</div>

<div class="bg-white p-6 rounded-xl shadow border">
<p class="text-sm text-gray-500">Paused Campaigns</p>
<p class="text-2xl font-bold text-yellow-600 mt-1">
{{ $campaigns->where('status','PAUSED')->count() }}
</p>
</div>

</div>


{{-- ================= CAMPAIGN TABLE ================= --}}
<div class="bg-white rounded-2xl shadow overflow-hidden border">

<div class="overflow-x-auto">

<table class="min-w-full divide-y divide-gray-200">

<thead class="bg-gray-50">
<tr>

<th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">
Campaign
</th>

<th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">
Objective
</th>

<th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">
Daily Budget
</th>

<th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">
Status
</th>

<th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">
Created
</th>

<th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase">
Actions
</th>

</tr>
</thead>


<tbody class="bg-white divide-y divide-gray-100">

@forelse($campaigns as $campaign)

<tr class="hover:bg-gray-50 transition">

{{-- Campaign --}}
<td class="px-6 py-4">
<div class="font-semibold text-gray-900">
{{ $campaign->name }}
</div>

@if($campaign->meta_id)
<div class="text-xs text-gray-400 mt-1">
Meta ID: {{ $campaign->meta_id }}
</div>
@endif
</td>


{{-- Objective --}}
<td class="px-6 py-4 text-sm text-gray-700">
{{ $campaign->objective }}
</td>


{{-- Budget --}}
<td class="px-6 py-4 text-sm text-gray-700">
${{ number_format($campaign->daily_budget / 100, 2) }}
</td>


{{-- Status --}}
<td class="px-6 py-4">

@switch($campaign->status)

@case('ACTIVE')
<span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">
Active
</span>
@break

@case('PAUSED')
<span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700">
Paused
</span>
@break

@case('DRAFT')
<span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">
Draft
</span>
@break

@default
<span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">
{{ $campaign->status }}
</span>

@endswitch

</td>


{{-- Created --}}
<td class="px-6 py-4 text-sm text-gray-500">
{{ $campaign->created_at->format('d M Y') }}
</td>


{{-- Actions --}}
<td class="px-6 py-4 text-right space-x-4">

<a href="{{ route('admin.campaigns.edit',$campaign) }}"
   class="text-blue-600 hover:text-blue-800 text-sm font-medium">
Edit
</a>

<form method="POST"
      action="{{ route('admin.campaigns.destroy',$campaign) }}"
      class="inline"
      onsubmit="return confirm('Delete this campaign?')">

@csrf
@method('DELETE')

<button class="text-red-500 hover:text-red-700 text-sm font-medium">
Delete
</button>

</form>

</td>

</tr>

@empty

<tr>
<td colspan="6" class="text-center py-16">

<div class="text-5xl mb-4 text-gray-300">
📊
</div>

<p class="text-gray-600 font-medium">
No campaigns created yet
</p>

<p class="text-sm text-gray-400 mt-2">
Create your first marketing campaign to start advertising.
</p>

<a href="{{ route('admin.campaigns.create') }}"
class="inline-block mt-6 bg-blue-600 text-white px-6 py-3 rounded-xl hover:bg-blue-700">

Create Campaign

</a>

</td>
</tr>

@endforelse

</tbody>

</table>

</div>


{{-- Pagination --}}
@if(method_exists($campaigns,'links'))

<div class="px-6 py-4 border-t bg-gray-50">
{{ $campaigns->links() }}
</div>

@endif

</div>

</div>

@endsection