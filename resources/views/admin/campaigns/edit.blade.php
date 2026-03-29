@extends('layouts.admin')

@section('content')

<div class="max-w-4xl mx-auto py-10 space-y-8">

<div>
    <h1 class="text-3xl font-bold text-gray-900">
        Edit Campaign
    </h1>

    <p class="text-gray-500 mt-2">
        Update campaign settings and budget.
    </p>
</div>


<div class="bg-white rounded-2xl shadow border overflow-hidden">

<form method="POST" action="{{ route('admin.campaigns.update', $campaign->id) }}">
@csrf
@method('PUT')

<div class="p-10 space-y-8">

@if ($errors->any())
<div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl">
<ul class="list-disc pl-5 text-sm space-y-1">
@foreach ($errors->all() as $error)
<li>{{ $error }}</li>
@endforeach
</ul>
</div>
@endif


{{-- Campaign Name --}}
<div>
<label class="block text-sm font-semibold text-gray-700 mb-2">
Campaign Name
</label>

<input
type="text"
name="name"
value="{{ old('name', $campaign->name) }}"
required
class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
</div>


{{-- Objective --}}
<div>
<label class="block text-sm font-semibold text-gray-700 mb-2">
Objective
</label>

<select
name="objective"
class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">

<option value="OUTCOME_LEADS" {{ $campaign->objective == 'OUTCOME_LEADS' ? 'selected' : '' }}>
Lead Generation
</option>

<option value="OUTCOME_TRAFFIC" {{ $campaign->objective == 'OUTCOME_TRAFFIC' ? 'selected' : '' }}>
Website Traffic
</option>

<option value="OUTCOME_ENGAGEMENT" {{ $campaign->objective == 'OUTCOME_ENGAGEMENT' ? 'selected' : '' }}>
Engagement
</option>

</select>
</div>


{{-- Daily Budget --}}
<div>
<label class="block text-sm font-semibold text-gray-700 mb-2">
Daily Budget (CAD)
</label>

<input
type="number"
name="daily_budget"
value="{{ old('daily_budget', $campaign->daily_budget / 100) }}"
min="5"
required
class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">
</div>


{{-- Status --}}
<div>
<label class="block text-sm font-semibold text-gray-700 mb-2">
Status
</label>

<select
name="status"
class="w-full border rounded-xl px-4 py-3">

<option value="draft" {{ $campaign->status == 'draft' ? 'selected' : '' }}>
Draft
</option>

<option value="active" {{ $campaign->status == 'active' ? 'selected' : '' }}>
Active
</option>

<option value="paused" {{ $campaign->status == 'paused' ? 'selected' : '' }}>
Paused
</option>

<option value="completed" {{ $campaign->status == 'completed' ? 'selected' : '' }}>
Completed
</option>

</select>
</div>

</div>


<div class="bg-gray-50 px-10 py-6 flex justify-between items-center border-t">

<a href="{{ route('admin.campaigns.index') }}"
class="text-gray-500 hover:text-gray-700 text-sm">
Cancel
</a>

<button
type="submit"
class="bg-blue-600 text-white px-6 py-3 rounded-xl shadow hover:bg-blue-700">

Save Changes

</button>

</div>

</form>

</div>

</div>

@endsection