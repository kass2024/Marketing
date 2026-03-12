@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto py-10 space-y-8">

{{-- HEADER --}}
<div class="flex items-center justify-between">

<div>
<h1 class="text-2xl font-bold text-gray-900">
Edit Ad Set
</h1>

<p class="text-sm text-gray-500">
Modify targeting, budget and delivery settings.
</p>
</div>

<a
href="{{ route('admin.adsets.index') }}"
class="text-gray-600 hover:text-gray-800">

← Back to Ad Sets

</a>

</div>



{{-- FORM --}}
<div class="bg-white p-8 rounded-xl shadow border">

<form
method="POST"
action="{{ route('admin.adsets.update',$adset) }}">

@csrf
@method('PUT')



{{-- NAME --}}
<div class="mb-6">

<label class="block text-sm font-medium text-gray-600">
Ad Set Name
</label>

<input
type="text"
name="name"
value="{{ old('name',$adset->name) }}"
class="w-full border rounded-lg px-4 py-2 mt-1 focus:ring-2 focus:ring-blue-500"
required>

</div>



{{-- CAMPAIGN --}}
<div class="mb-6">

<label class="block text-sm font-medium text-gray-600">
Campaign
</label>

<input
type="text"
value="{{ $adset->campaign->name ?? '-' }}"
class="w-full border rounded-lg px-4 py-2 mt-1 bg-gray-100"
disabled>

</div>



{{-- DAILY BUDGET --}}
<div class="mb-6">

<label class="block text-sm font-medium text-gray-600">
Daily Budget ($)
</label>

<input
type="number"
step="0.01"
name="daily_budget"
value="{{ $adset->daily_budget ? $adset->daily_budget / 100 : '' }}"
class="w-full border rounded-lg px-4 py-2 mt-1">

<p class="text-xs text-gray-400 mt-1">
Budget will be converted to cents for Meta API.
</p>

</div>



{{-- STATUS --}}
<div class="mb-6">

<label class="block text-sm font-medium text-gray-600">
Status
</label>

<select
name="status"
class="w-full border rounded-lg px-4 py-2 mt-1">

<option value="DRAFT" @selected($adset->status=='DRAFT')>
Draft
</option>

<option value="ACTIVE" @selected($adset->status=='ACTIVE')>
Active
</option>

<option value="PAUSED" @selected($adset->status=='PAUSED')>
Paused
</option>

</select>

</div>



{{-- META INFO --}}
@if($adset->meta_id)

<div class="mb-6 bg-gray-50 border rounded-lg p-4">

<div class="text-sm text-gray-600">

<div class="font-semibold">
Meta Ad Set ID
</div>

<div class="text-xs font-mono text-gray-500 mt-1">
{{ $adset->meta_id }}
</div>

</div>

</div>

@endif



{{-- BUTTONS --}}
<div class="flex items-center justify-between mt-8">

<button
type="submit"
class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">

Update Ad Set

</button>

</form>



{{-- DELETE FORM --}}
<form
method="POST"
action="{{ route('admin.adsets.destroy',$adset) }}">

@csrf
@method('DELETE')

<button
onclick="return confirm('Delete this Ad Set? This action cannot be undone.')"
class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700">

Delete Ad Set

</button>

</form>

</div>

</div>

</div>

@endsection