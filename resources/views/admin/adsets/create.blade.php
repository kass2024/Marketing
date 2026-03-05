@extends('layouts.app')

@section('content')

<link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">

<div class="max-w-5xl mx-auto space-y-8">

{{-- HEADER --}}
<div class="flex justify-between items-center">

<div>
<h1 class="text-3xl font-bold text-gray-900">
Create Ad Set
</h1>

<p class="text-sm text-gray-500">
Configure targeting and delivery for your campaign.
</p>
</div>

<a href="{{ route('admin.adsets.index') }}"
class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
Back
</a>

</div>



{{-- FORM CARD --}}
<div class="bg-white shadow rounded-2xl p-10">

@if($errors->any())
<div class="mb-6 bg-red-50 border border-red-200 text-red-600 p-4 rounded-lg">
<ul class="list-disc pl-5">
@foreach ($errors->all() as $error)
<li>{{ $error }}</li>
@endforeach
</ul>
</div>
@endif


<form method="POST" action="{{ route('admin.adsets.store') }}">
@csrf


{{-- CAMPAIGN --}}
<div class="mb-6">

<label class="font-semibold block mb-2">
Campaign
</label>

<select
name="campaign_id"
id="campaign-select"
class="w-full border rounded-xl px-4 py-3"
required
>

<option value="">Select Campaign</option>

@foreach($campaigns as $campaign)

<option value="{{ $campaign->id }}">
{{ $campaign->name }}
</option>

@endforeach

</select>

</div>



{{-- NAME --}}
<div class="mb-6">

<label class="font-semibold block mb-2">
Ad Set Name
</label>

<input
type="text"
name="name"
class="w-full border rounded-xl px-4 py-3"
required
>

</div>



{{-- BUDGET --}}
<div class="mb-6">

<label class="font-semibold block mb-2">
Daily Budget (CAD)
</label>

<input
type="number"
name="daily_budget"
class="w-full border rounded-xl px-4 py-3"
required
>

</div>



{{-- AGE --}}
<div class="grid grid-cols-2 gap-6 mb-6">

<div>

<label class="font-semibold block mb-2">
Age Min
</label>

<input
type="number"
name="age_min"
class="w-full border rounded-xl px-4 py-3"
value="18"
>

</div>


<div>

<label class="font-semibold block mb-2">
Age Max
</label>

<input
type="number"
name="age_max"
class="w-full border rounded-xl px-4 py-3"
value="65"
>

</div>

</div>



{{-- GENDER --}}
<div class="mb-6">

<label class="font-semibold block mb-2">
Gender
</label>

<select
name="genders[]"
multiple
id="gender-select"
class="w-full border rounded-xl px-4 py-3"
>

<option value="1">Male</option>
<option value="2">Female</option>

</select>

</div>



{{-- COUNTRIES --}}
<div class="mb-6">

<label class="font-semibold block mb-2">
Target Countries
</label>

<select
name="countries[]"
multiple
id="country-select"
class="w-full border rounded-xl px-4 py-3"
required
>

@foreach($countries as $code => $country)

<option value="{{ $code }}">
{{ $country }}
</option>

@endforeach

</select>

<p class="text-sm text-gray-500 mt-2">
Select one or more countries.
</p>

</div>



{{-- INTERESTS --}}
<div class="mb-6">

<label class="font-semibold block mb-2">
Audience Interests
</label>

<input
type="text"
name="interests"
placeholder="study abroad, scholarships, university"
class="w-full border rounded-xl px-4 py-3"
>

</div>



{{-- PLACEMENTS --}}
<div class="mb-6">

<label class="font-semibold block mb-2">
Placements
</label>

<select
name="placements[]"
multiple
id="placement-select"
class="w-full border rounded-xl px-4 py-3"
>

<option value="facebook">Facebook Feed</option>
<option value="instagram">Instagram Feed</option>
<option value="messenger">Messenger</option>
<option value="audience_network">Audience Network</option>

</select>

</div>



{{-- DEVICES --}}
<div class="mb-6">

<label class="font-semibold block mb-2">
Devices
</label>

<select
name="devices[]"
multiple
id="device-select"
class="w-full border rounded-xl px-4 py-3"
>

<option value="mobile">Mobile</option>
<option value="desktop">Desktop</option>

</select>

</div>



<div class="flex justify-end">

<button
type="submit"
class="bg-blue-600 text-white px-6 py-3 rounded-xl shadow hover:bg-blue-700"
>
Create Ad Set
</button>

</div>

</form>

</div>

</div>



{{-- TOMSELECT --}}
<script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>

<script>

new TomSelect("#campaign-select");

new TomSelect("#country-select",{
plugins:['remove_button'],
maxItems:null,
placeholder:'Select countries'
});

new TomSelect("#gender-select",{
plugins:['remove_button']
});

new TomSelect("#placement-select",{
plugins:['remove_button']
});

new TomSelect("#device-select",{
plugins:['remove_button']
});

</script>

@endsection