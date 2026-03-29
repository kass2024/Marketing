@extends('layouts.admin')

@section('title', 'Create campaign')

@section('content')

<div class="mx-auto max-w-3xl space-y-8">

<div class="min-w-0">
    <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Create campaign</h1>
    <p class="mt-1 text-sm text-slate-600">
        Define the objective for your Meta advertising campaign.
    </p>
</div>

<div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/5">

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
class="w-full rounded-xl border border-slate-200 px-4 py-3 shadow-sm focus:border-xander-navy focus:ring-2 focus:ring-xander-navy/20
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
class="w-full rounded-xl border border-slate-200 px-4 py-3 shadow-sm focus:border-xander-navy focus:ring-2 focus:ring-xander-navy/20
@if($errors->has('objective')) border-red-500 @endif"
>

<option value="">Select objective</option>

<option value="OUTCOME_TRAFFIC" {{ old('objective')=='OUTCOME_TRAFFIC'?'selected':'' }}>
Website Traffic
</option>

<option value="OUTCOME_LEADS" {{ old('objective')=='OUTCOME_LEADS'?'selected':'' }}>
Lead Generation
</option>

<option value="OUTCOME_ENGAGEMENT" {{ old('objective')=='OUTCOME_ENGAGEMENT'?'selected':'' }}>
Post Engagement
</option>

<option value="OUTCOME_AWARENESS" {{ old('objective')=='OUTCOME_AWARENESS'?'selected':'' }}>
Brand Awareness
</option>

<option value="OUTCOME_SALES" {{ old('objective')=='OUTCOME_SALES'?'selected':'' }}>
Sales / Conversions
</option>

</select>

@error('objective')
<p class="text-sm text-red-500">{{ $message }}</p>
@enderror

<p class="text-xs text-gray-400">
These objectives match Meta Ads Manager outcome-driven objectives.
</p>

</div>
{{-- ================= STATUS ================= --}}
<div class="space-y-2">

<label class="block text-sm font-semibold text-gray-700">
Campaign Status
</label>

<select
name="status"
class="w-full rounded-xl border border-slate-200 px-4 py-3 shadow-sm focus:border-xander-navy focus:ring-2 focus:ring-xander-navy/20"
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
<div class="rounded-xl border border-xander-navy/15 bg-xander-navy/5 p-4 text-sm text-slate-700">

<label class="flex items-center gap-3">

<input
type="checkbox"
name="sync_meta"
value="1"
checked
class="rounded border-slate-300 text-xander-navy focus:ring-xander-navy"
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
<div class="flex items-center justify-between border-t border-slate-200 bg-slate-50/80 px-6 py-5 sm:px-10">

<a
href="{{ route('admin.campaigns.index') }}"
class="text-sm font-semibold text-slate-600 transition hover:text-xander-navy"
>
Cancel
</a>

<button
type="submit"
id="submitBtn"
class="inline-flex items-center gap-2 rounded-xl bg-xander-navy px-6 py-3 font-semibold text-white shadow-sm transition hover:bg-xander-secondary"
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