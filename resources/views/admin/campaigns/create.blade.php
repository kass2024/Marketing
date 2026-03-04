@extends('layouts.app')

@section('content')

<div class="max-w-4xl mx-auto py-10 space-y-8">

{{-- ================= PAGE HEADER ================= --}}
<div>

    <h1 class="text-3xl font-bold text-gray-900">
        Create Campaign
    </h1>

    <p class="text-gray-500 mt-2">
        Define the objective and budget for your advertising campaign.
    </p>

</div>


{{-- ================= FORM CARD ================= --}}
<div class="bg-white rounded-2xl shadow border overflow-hidden">

<form method="POST" action="{{ route('admin.campaigns.store') }}">
@csrf

<div class="p-10 space-y-8">

{{-- ================= ERROR DISPLAY ================= --}}
@if ($errors->any())
<div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl">
    <ul class="list-disc pl-5 text-sm space-y-1">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif


{{-- ================= CAMPAIGN NAME ================= --}}
<div>

<label class="block text-sm font-semibold text-gray-700 mb-2">
Campaign Name
</label>

<input
type="text"
name="name"
value="{{ old('name') }}"
placeholder="Example: Canada Study Leads"
required
class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">

<p class="text-xs text-gray-400 mt-2">
This name will only be visible inside your dashboard.
</p>

</div>



{{-- ================= OBJECTIVE ================= --}}
<div>

<label class="block text-sm font-semibold text-gray-700 mb-2">
Campaign Objective
</label>

<select
name="objective"
required
class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">

<option value="MESSAGES">WhatsApp Messages</option>
<option value="TRAFFIC">Website Traffic</option>
<option value="LEADS">Lead Generation</option>
<option value="ENGAGEMENT">Post Engagement</option>

</select>

<p class="text-xs text-gray-400 mt-2">
Choose the main goal for this campaign. This determines how Meta optimizes delivery.
</p>

</div>



{{-- ================= DAILY BUDGET ================= --}}
<div>

<label class="block text-sm font-semibold text-gray-700 mb-2">
Daily Budget (CAD)
</label>

<input
type="number"
name="daily_budget"
value="{{ old('daily_budget') }}"
min="1"
required
placeholder="Example: 20"
class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500">

<p class="text-xs text-gray-400 mt-2">
The maximum amount you are willing to spend per day.
</p>

</div>


</div>


{{-- ================= ACTION BAR ================= --}}
<div class="bg-gray-50 px-10 py-6 flex justify-between items-center border-t">

<a href="{{ route('admin.campaigns.index') }}"
class="text-gray-500 hover:text-gray-700 text-sm">
Cancel
</a>

<button
type="submit"
class="inline-flex items-center gap-3 bg-blue-600 text-white px-6 py-3 rounded-xl shadow hover:bg-blue-700 transition">

Continue

<svg xmlns="http://www.w3.org/2000/svg"
class="w-4 h-4"
fill="none"
viewBox="0 0 24 24"
stroke="currentColor">
<path stroke-linecap="round"
stroke-linejoin="round"
stroke-width="2"
d="M9 5l7 7-7 7" />
</svg>

</button>

</div>

</form>

</div>

</div>

@endsection