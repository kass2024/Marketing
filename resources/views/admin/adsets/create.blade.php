@extends('layouts.app')

@section('content')

<link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">

<div class="max-w-5xl mx-auto space-y-8">

<div class="flex justify-between items-center">
<div>
<h1 class="text-3xl font-bold text-gray-900">Create Ad Set</h1>
<p class="text-sm text-gray-500 mt-1">
Meta validated audience configuration
</p>
</div>

<a href="{{ route('admin.campaigns.index') }}"
class="bg-gray-600 text-white px-4 py-3 rounded-xl hover:bg-gray-700">
Back
</a>
</div>


@if($errors->any())
<div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl">
<ul class="list-disc ml-6">
@foreach ($errors->all() as $error)
<li>{{ $error }}</li>
@endforeach
</ul>
</div>
@endif


<div class="bg-white shadow border rounded-2xl p-8">

<form method="POST"
action="{{ route('admin.adsets.store') }}"
id="adsetForm">

@csrf


{{-- CAMPAIGN --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Campaign</label>

<select name="campaign_id"
id="campaign-select"
class="w-full border rounded-xl px-4 py-3"
required>

<option value="">Select campaign</option>

@foreach($campaigns as $campaign)

<option
value="{{ $campaign->id }}"
data-objective="{{ $campaign->objective ?? 'REACH' }}"
{{ old('campaign_id',$selectedCampaign) == $campaign->id ? 'selected' : '' }}>

{{ $campaign->name }}

</option>

@endforeach

</select>

<p id="objective-warning" class="text-xs text-blue-600 mt-1 hidden"></p>

</div>


{{-- ADSET NAME --}}
<div class="mb-6">
<label class="font-semibold block mb-2">Ad Set Name</label>

<input
type="text"
name="name"
value="{{ old('name') }}"
class="w-full border rounded-xl px-4 py-3"
required>
</div>



{{-- BUDGET --}}
<div class="mb-6">
<label class="font-semibold block mb-2">Daily Budget ($)</label>

<input
type="number"
name="daily_budget"
value="{{ old('daily_budget') }}"
min="5"
step="1"
class="w-full border rounded-xl px-4 py-3"
required>

<p class="text-xs text-gray-500 mt-1">
Minimum recommended budget: $5/day
</p>
</div>



{{-- AGE --}}
<div class="grid grid-cols-2 gap-4 mb-6">

<div>
<label class="font-semibold block mb-2">Minimum Age</label>
<input type="number" name="age_min"
value="{{ old('age_min',18) }}"
min="18" max="65"
class="w-full border rounded-xl px-4 py-3">
</div>

<div>
<label class="font-semibold block mb-2">Maximum Age</label>
<input type="number" name="age_max"
value="{{ old('age_max',65) }}"
min="18" max="65"
class="w-full border rounded-xl px-4 py-3">
</div>

</div>



{{-- GENDER --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Gender</label>

<select name="genders[]" multiple
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

<select name="countries[]" multiple
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

<select name="languages[]" multiple
id="language-select"
class="w-full border rounded-xl px-4 py-3">

@foreach($languages as $id => $language)

<option value="{{ $id }}">
{{ $language }}
</option>

@endforeach

</select>

<p class="text-xs text-gray-500 mt-1">
Languages automatically disabled for single country targeting
</p>

</div>



{{-- INTERESTS --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Interest Targeting</label>

<select
name="interests[]"
id="interest-select"
multiple
class="w-full border rounded-xl px-4 py-3"></select>

<p id="interest-warning"
class="text-xs text-red-600 mt-1 hidden">
Interest targeting disabled for REACH campaigns
</p>

</div>



{{-- PLACEMENTS --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Placement Strategy</label>

<select name="placement_type"
id="placement-type"
class="w-full border rounded-xl px-4 py-3">

<option value="automatic">Automatic (Recommended)</option>
<option value="manual">Manual</option>

</select>

</div>



{{-- PLATFORMS --}}
<div class="mb-6 hidden" id="platform-section">

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

document.addEventListener("DOMContentLoaded", function(){

function initSelect(id){
if(document.querySelector(id)){
new TomSelect(id,{plugins:['remove_button']});
}
}

initSelect("#country-select");
initSelect("#gender-select");
initSelect("#language-select");
initSelect("#platform-select");



/*
INTEREST SEARCH
*/

let interestTimeout;

const interestSelect = new TomSelect("#interest-select",{

plugins:['remove_button'],
valueField:'id',
labelField:'name',
searchField:'name',
maxItems:5,

load:function(query,callback){

clearTimeout(interestTimeout);

interestTimeout=setTimeout(()=>{

if(query.length < 2) return callback();

fetch("/admin/meta/interests?q="+query)
.then(res=>res.json())
.then(data=>callback(data.data ?? []))
.catch(()=>callback());

},400);

}

});



/*
OBJECTIVE VALIDATION
*/

document.getElementById("campaign-select")
.addEventListener("change",function(){

let selected = this.options[this.selectedIndex];
let objective = selected.dataset.objective;

let warning = document.getElementById("objective-warning");
let interestWarning = document.getElementById("interest-warning");

if(objective === "REACH"){

interestSelect.disable();
interestWarning.classList.remove("hidden");

warning.innerText = "REACH campaigns use broad targeting. Detailed interests disabled.";
warning.classList.remove("hidden");

}else{

interestSelect.enable();
interestWarning.classList.add("hidden");
warning.classList.add("hidden");

}

});



/*
LANGUAGE VALIDATION
*/

document.getElementById("country-select")
.addEventListener("change",function(){

let languages=document.getElementById("language-select");

if(this.selectedOptions.length === 1){

languages.tomselect.clear();
languages.tomselect.disable();

}else{

languages.tomselect.enable();

}

});



/*
PLACEMENT CONTROL
*/

document.getElementById("placement-type")
.addEventListener("change",function(){

let section=document.getElementById("platform-section");

if(this.value==="manual"){
section.classList.remove("hidden");
}else{
section.classList.add("hidden");
}

});



/*
AGE VALIDATION
*/

document.getElementById("adsetForm")
.addEventListener("submit",function(e){

let min=parseInt(document.querySelector("[name='age_min']").value);
let max=parseInt(document.querySelector("[name='age_max']").value);

if(min > max){
e.preventDefault();
alert("Minimum age cannot be greater than maximum age");
}

});

});

</script>

@endsection