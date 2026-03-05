@extends('layouts.app')

@section('content')

<link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">

<div class="max-w-4xl mx-auto">

<div class="bg-white shadow rounded-2xl p-10">

<h2 class="text-2xl font-bold mb-6">
Create Ad Set for: {{ $campaign->name }}
</h2>

<form method="POST" action="{{ route('admin.adsets.store') }}">
@csrf

<input type="hidden" name="campaign_id" value="{{ $campaign->id }}">


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
<label class="font-semibold block mb-2">Age Min</label>

<input
type="number"
name="age_min"
class="w-full border rounded-xl px-4 py-3"
value="22"
>
</div>

<div>
<label class="font-semibold block mb-2">Age Max</label>

<input
type="number"
name="age_max"
class="w-full border rounded-xl px-4 py-3"
value="45"
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
>

@foreach($countries as $code => $country)

<option value="{{ $code }}">
{{ $country }}
</option>

@endforeach

</select>

<p class="text-sm text-gray-500 mt-2">
Select one or more countries for your audience.
</p>

</div>


{{-- INTERESTS --}}
<div class="mb-6">

<label class="font-semibold block mb-2">
Interests
</label>

<input
type="text"
name="interests"
placeholder="study abroad, university, scholarships"
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


<script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>

<script>

new TomSelect("#country-select",{
plugins:['remove_button'],
maxItems:null,
placeholder:'Select countries...'
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