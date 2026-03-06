@extends('layouts.app')

@section('content')

<link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">

<div class="max-w-5xl mx-auto space-y-8">

<div class="flex justify-between items-center">

<div>
<h1 class="text-3xl font-bold text-gray-900">Create Ad Set</h1>
<p class="text-sm text-gray-500 mt-1">
Configure audience targeting and delivery
</p>
</div>

<a href="{{ route('admin.adsets.index') }}"
class="bg-gray-600 text-white px-4 py-3 rounded-xl hover:bg-gray-700">
Back
</a>

</div>


<div class="bg-white shadow border rounded-2xl p-8">

<form method="POST" action="{{ route('admin.adsets.store') }}">
@csrf


{{-- CAMPAIGN --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Campaign</label>

<select name="campaign_id"
class="w-full border rounded-xl px-4 py-3" required>

<option value="">Select campaign</option>

@foreach($campaigns as $campaign)

<option value="{{ $campaign->id }}">
{{ $campaign->name }}
</option>

@endforeach

</select>

</div>



{{-- NAME --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Ad Set Name</label>

<input type="text"
name="name"
class="w-full border rounded-xl px-4 py-3"
placeholder="Example: US Tech Audience"
required>

</div>



{{-- DAILY BUDGET --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Daily Budget</label>

<input type="number"
name="daily_budget"
min="5"
step="1"
class="w-full border rounded-xl px-4 py-3"
required>

<p class="text-xs text-gray-500 mt-1">
Minimum recommended budget: $5/day
</p>

</div>



{{-- STATUS --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Status</label>

<select name="status"
class="w-full border rounded-xl px-4 py-3">

<option value="PAUSED">Paused (recommended)</option>
<option value="ACTIVE">Active immediately</option>

</select>

</div>



{{-- AGE TARGETING --}}
<div class="grid grid-cols-2 gap-4 mb-6">

<div>
<label class="font-semibold block mb-2">Minimum Age</label>

<input type="number"
name="age_min"
value="18"
min="18"
max="65"
class="w-full border rounded-xl px-4 py-3">
</div>

<div>
<label class="font-semibold block mb-2">Maximum Age</label>

<input type="number"
name="age_max"
value="65"
min="18"
max="65"
class="w-full border rounded-xl px-4 py-3">
</div>

</div>



{{-- GENDER --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Gender</label>

<select name="genders[]"
multiple
id="gender-select"
class="w-full border rounded-xl px-4 py-3">

<option value="1">Male</option>
<option value="2">Female</option>

</select>

<p class="text-xs text-gray-500 mt-1">
Leave empty to target all genders
</p>

</div>



{{-- COUNTRIES --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Countries</label>

<select name="countries[]"
multiple
id="country-select"
class="w-full border rounded-xl px-4 py-3"
required>

@foreach($countries as $code => $country)

<option value="{{ $code }}">
{{ $country }}
</option>

@endforeach

</select>

</div>



{{-- LANGUAGES --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Languages</label>

<select name="languages[]"
multiple
id="language-select"
class="w-full border rounded-xl px-4 py-3">

@foreach($languages as $code => $language)

<option value="{{ $code }}">
{{ $language }}
</option>

@endforeach

</select>

</div>



{{-- INTEREST TARGETING --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Interest Targeting</label>

<select name="interests[]"
id="interest-select"
multiple
class="w-full border rounded-xl px-4 py-3"></select>

<p class="text-xs text-gray-500 mt-1">
Start typing to search Meta interests (minimum 2 characters)
</p>

</div>



{{-- PLACEMENTS --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Placement Strategy</label>

<select name="placement_type"
id="placement-type"
class="w-full border rounded-xl px-4 py-3">

<option value="automatic">Automatic (recommended)</option>
<option value="manual">Manual</option>

</select>

</div>



{{-- PLATFORMS --}}
<div class="mb-6" id="platform-section">

<label class="font-semibold block mb-2">Platforms</label>

<select name="publisher_platforms[]"
multiple
id="platform-select"
class="w-full border rounded-xl px-4 py-3">

<option value="facebook">Facebook</option>
<option value="instagram">Instagram</option>
<option value="messenger">Messenger</option>
<option value="audience_network">Audience Network</option>

</select>

</div>



<div class="flex justify-end">

<button
type="submit"
class="bg-blue-600 text-white px-8 py-3 rounded-xl hover:bg-blue-700">

Create Ad Set

</button>

</div>

</form>

</div>

</div>



<script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>

<script>

new TomSelect("#country-select",{plugins:['remove_button']});
new TomSelect("#gender-select",{plugins:['remove_button']});
new TomSelect("#language-select",{plugins:['remove_button']});
new TomSelect("#platform-select",{plugins:['remove_button']});


let interestTimeout;

new TomSelect("#interest-select",{

plugins:['remove_button'],
valueField:'id',
labelField:'name',
searchField:'name',

load:function(query,callback){

clearTimeout(interestTimeout);

interestTimeout=setTimeout(()=>{

if(query.length<2) return callback();

fetch("/admin/meta/interests?q="+query)
.then(res=>res.json())
.then(data=>callback(data.data))
.catch(()=>callback());

},400);

}

});


document.getElementById("placement-type").addEventListener("change",function(){

let section=document.getElementById("platform-section");

if(this.value==="automatic"){
section.style.display="none";
}else{
section.style.display="block";
}

});

</script>

@endsection