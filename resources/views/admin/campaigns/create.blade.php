@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto py-10 space-y-8">

{{-- ================= HEADER ================= --}}
<div class="flex items-center justify-between">

<div>

<h1 class="text-3xl font-bold text-gray-900">
Create Campaign
</h1>

<p class="text-gray-500 mt-2">
Define the objective for your Meta advertising campaign.
</p>

</div>

<a
href="{{ route('admin.campaigns.index') }}"
class="text-sm text-gray-500 hover:text-gray-700"
>
← Back to Campaigns
</a>

</div>



{{-- ================= FORM ================= --}}
<div class="bg-white border rounded-2xl shadow-sm overflow-hidden">

<form
method="POST"
action="{{ route('admin.campaigns.store') }}"
id="campaignForm"
>

@csrf


<div class="p-10 space-y-10">


{{-- ================= ERRORS ================= --}}
@if ($errors->any())

<div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl text-sm">

<ul class="list-disc pl-5 space-y-1">

@foreach ($errors->all() as $error)

<li>{{ $error }}</li>

@endforeach

</ul>

</div>

@endif



{{-- ================= CAMPAIGN NAME ================= --}}
<div class="space-y-2">

<label class="block text-sm font-semibold text-gray-700">
Campaign Name
</label>

<input
type="text"
name="name"
value="{{ old('name') }}"
required
placeholder="Example: Global Study Abroad Campaign"
class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500
@if($errors->has('name')) border-red-500 @endif"
>

@error('name')
<p class="text-sm text-red-500">{{ $message }}</p>
@enderror

<p class="text-xs text-gray-400">
Visible only in your dashboard.
</p>

</div>



{{-- ================= OBJECTIVE ================= --}}
<div class="space-y-2">

<label class="block text-sm font-semibold text-gray-700">
Campaign Objective
</label>

<select
name="objective"
required
class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500
@if($errors->has('objective')) border-red-500 @endif"
>

<option value="">Select objective</option>

<option value="LEADS" {{ old('objective')=='LEADS'?'selected':'' }}>
Lead Generation
</option>

<option value="TRAFFIC" {{ old('objective')=='TRAFFIC'?'selected':'' }}>
Website Traffic
</option>

<option value="ENGAGEMENT" {{ old('objective')=='ENGAGEMENT'?'selected':'' }}>
Post Engagement
</option>

<option value="AWARENESS" {{ old('objective')=='AWARENESS'?'selected':'' }}>
Brand Awareness
</option>

<option value="SALES" {{ old('objective')=='SALES'?'selected':'' }}>
Sales / Conversions
</option>

</select>

@error('objective')
<p class="text-sm text-red-500">{{ $message }}</p>
@enderror

<p class="text-xs text-gray-400">
Meta will optimize delivery according to this objective.
</p>

</div>



{{-- ================= STATUS ================= --}}
<div class="space-y-2">

<label class="block text-sm font-semibold text-gray-700">
Campaign Status
</label>

<select
name="status"
class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500"
>

<option value="PAUSED" {{ old('status')=='PAUSED'?'selected':'' }}>
Paused (recommended)
</option>

<option value="ACTIVE" {{ old('status')=='ACTIVE'?'selected':'' }}>
Active immediately
</option>

</select>

<p class="text-xs text-gray-400">
Campaigns normally start paused until ads are ready.
</p>

</div>



{{-- ================= META SYNC ================= --}}
<div class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm">

<label class="flex items-center gap-3">

<input
type="checkbox"
name="sync_meta"
value="1"
checked
class="rounded border-gray-300"
>

<span>
Create campaign on Meta Ads Manager
</span>

</label>

<p class="text-xs text-gray-500 mt-2">
Disable to test locally without sending data to Meta.
</p>

</div>


</div>



{{-- ================= ACTION BAR ================= --}}
<div class="bg-gray-50 border-t px-10 py-6 flex items-center justify-between">

<a
href="{{ route('admin.campaigns.index') }}"
class="text-sm text-gray-500 hover:text-gray-700"
>
Cancel
</a>

<button
type="submit"
id="submitBtn"
class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-xl shadow hover:bg-blue-700 transition"
>

<span id="btnText">
Create Campaign
</span>

<svg
id="btnSpinner"
class="hidden animate-spin w-4 h-4"
xmlns="http://www.w3.org/2000/svg"
fill="none"
viewBox="0 0 24 24"
>

<circle
class="opacity-25"
cx="12"
cy="12"
r="10"
stroke="currentColor"
stroke-width="4"
/>

<path
class="opacity-75"
fill="currentColor"
d="M4 12a8 8 0 018-8v8H4z"
/>

</svg>

</button>

</div>

</form>

</div>

</div>



{{-- ================= SUBMIT LOADER ================= --}}
<script>

document.getElementById('campaignForm').addEventListener('submit',function(){

const btn = document.getElementById('submitBtn');
const spinner = document.getElementById('btnSpinner');
const text = document.getElementById('btnText');

btn.disabled = true;

spinner.classList.remove('hidden');

text.innerText = "Creating...";

});

</script>

@endsection