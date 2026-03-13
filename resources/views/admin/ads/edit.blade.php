@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto space-y-8">

{{-- HEADER --}}
<div class="flex items-center justify-between">

    <div>
        <h1 class="text-2xl font-bold text-gray-900">
            Edit Ad
        </h1>

        <p class="text-sm text-gray-500">
            Update ad configuration, budget and delivery status.
        </p>
    </div>

    <a href="{{ route('admin.ads.index') }}"
       class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800">
        Back
    </a>

</div>


{{-- ERROR DISPLAY --}}
@if ($errors->any())
<div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg">
    <ul class="list-disc pl-5 text-sm">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif


{{-- FORM --}}
<div class="bg-white p-8 rounded-2xl shadow border">

<form method="POST" action="{{ route('admin.ads.update',$ad->id) }}">
@csrf
@method('PUT')


{{-- AD NAME --}}
<div class="mb-6">

<label class="block text-sm font-medium text-gray-700 mb-2">
Ad Name
</label>

<input type="text"
       name="name"
       value="{{ old('name',$ad->name) }}"
       class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-blue-200"
       required>

</div>



{{-- ADSET --}}
<div class="mb-6">

<label class="block text-sm font-medium text-gray-700 mb-2">
AdSet
</label>

<select name="adset_id"
        class="w-full border rounded-lg px-3 py-2">

@foreach($adsets as $adset)

<option value="{{ $adset->id }}"
{{ $ad->adset_id == $adset->id ? 'selected' : '' }}>

{{ $adset->name }}

</option>

@endforeach

</select>

</div>



{{-- CREATIVE --}}
<div class="mb-6">

<label class="block text-sm font-medium text-gray-700 mb-2">
Creative
</label>

<select name="creative_id"
        class="w-full border rounded-lg px-3 py-2">

@foreach($creatives as $creative)

<option value="{{ $creative->id }}"
{{ $ad->creative_id == $creative->id ? 'selected' : '' }}>

{{ $creative->name }}

</option>

@endforeach

</select>

</div>



{{-- DAILY BUDGET --}}
<div class="mb-6">

<label class="block text-sm font-medium text-gray-700 mb-2">
Daily Budget ($)
</label>

<input type="number"
       step="0.01"
       min="1"
       name="daily_budget"
       value="{{ old('daily_budget',$ad->daily_budget ?? 2) }}"
       class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-blue-200">

<p class="text-xs text-gray-500 mt-1">
Ad will automatically pause when daily spend reaches this limit.
</p>

</div>



{{-- STATUS --}}
<div class="mb-6">

<label class="block text-sm font-medium text-gray-700 mb-2">
Status
</label>

<select name="status"
        class="w-full border rounded-lg px-3 py-2">

<option value="ACTIVE"
{{ $ad->status == 'ACTIVE' ? 'selected' : '' }}>
ACTIVE
</option>

<option value="PAUSED"
{{ $ad->status == 'PAUSED' ? 'selected' : '' }}>
PAUSED
</option>

<option value="ARCHIVED"
{{ $ad->status == 'ARCHIVED' ? 'selected' : '' }}>
ARCHIVED
</option>

</select>

</div>



{{-- METRICS --}}
<div class="grid grid-cols-3 gap-6 mb-8 text-center">

<div class="bg-gray-50 p-4 rounded-xl">
<p class="text-xs text-gray-500">Impressions</p>
<p class="font-bold text-lg">{{ number_format($ad->impressions) }}</p>
</div>

<div class="bg-gray-50 p-4 rounded-xl">
<p class="text-xs text-gray-500">Clicks</p>
<p class="font-bold text-lg">{{ number_format($ad->clicks) }}</p>
</div>

<div class="bg-gray-50 p-4 rounded-xl">
<p class="text-xs text-gray-500">Spend</p>
<p class="font-bold text-lg">${{ number_format($ad->spend,2) }}</p>
</div>

</div>



{{-- ACTIONS --}}
<div class="flex justify-end gap-3">

<a href="{{ route('admin.ads.index') }}"
   class="px-5 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">
Cancel
</a>

<button
class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow">

Update Ad

</button>

</div>


</form>

</div>

</div>

@endsection