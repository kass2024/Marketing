@extends('layouts.app')

@section('content')

<link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">

<div class="max-w-6xl mx-auto space-y-8 py-10">

{{-- HEADER --}}
<div class="flex justify-between items-center">

<div>
<h1 class="text-3xl font-bold text-gray-900">Edit Ad Set</h1>
<p class="text-sm text-gray-500">Modify targeting, budget and delivery settings</p>
</div>

<a href="{{ route('admin.adsets.index') }}"
class="bg-gray-600 text-white px-4 py-3 rounded-xl hover:bg-gray-700">
Back
</a>

</div>


{{-- ERRORS --}}
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
action="{{ route('admin.adsets.update',$adset) }}"
id="adsetForm">

@csrf
@method('PUT')


{{-- CAMPAIGN --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Campaign</label>

<input
type="text"
value="{{ $adset->campaign->name ?? '-' }}"
class="w-full border rounded-xl px-4 py-3 bg-gray-100"
disabled>

</div>



{{-- NAME --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Ad Set Name</label>

<input
type="text"
name="name"
value="{{ old('name',$adset->name) }}"
class="w-full border rounded-xl px-4 py-3"
required>

</div>



{{-- BUDGET --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Daily Budget ($)</label>

<input
type="number"
name="daily_budget"
step="0.01"
min="5"
value="{{ old('daily_budget',$adset->daily_budget ?? 10) }}"
class="w-full border rounded-xl px-4 py-3"
required>

<p class="text-xs text-gray-500 mt-1">
Minimum recommended: $5/day
</p>

</div>



{{-- STATUS --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Status</label>

<select name="status"
class="w-full border rounded-xl px-4 py-3">

<option value="ACTIVE" @selected($adset->status=='ACTIVE')>
Active
</option>

<option value="PAUSED" @selected($adset->status=='PAUSED')>
Paused
</option>

</select>

</div>



{{-- FACEBOOK PAGE --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Facebook Page</label>

<select
name="page_id"
class="w-full border rounded-xl px-4 py-3"
required>

<option value="">Select Page</option>

@foreach($pages as $page)

<option
value="{{ $page['id'] }}"
@selected(old('page_id',$adset->page_id)==$page['id'])>

{{ $page['name'] }}

</option>

@endforeach

</select>

</div>



{{-- AGE --}}
<div class="grid grid-cols-2 gap-4 mb-6">

<div>
<label class="font-semibold block mb-2">Min Age</label>

<input
type="number"
name="age_min"
min="18"
max="65"
value="{{ old('age_min',$adset->age_min ?? 18) }}"
class="w-full border rounded-xl px-4 py-3">
</div>


<div>
<label class="font-semibold block mb-2">Max Age</label>

<input
type="number"
name="age_max"
min="18"
max="65"
value="{{ old('age_max',$adset->age_max ?? 65) }}"
class="w-full border rounded-xl px-4 py-3">
</div>

</div>



{{-- GENDER --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Gender</label>

<select
name="genders[]"
multiple
id="gender-select"
class="w-full border rounded-xl px-4 py-3">

<option value="1"
@selected(in_array(1,$adset->genders ?? []))>
Male
</option>

<option value="2"
@selected(in_array(2,$adset->genders ?? []))>
Female
</option>

</select>

</div>



{{-- COUNTRIES --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Countries</label>

<select
name="countries[]"
multiple
id="country-select"
class="w-full border rounded-xl px-4 py-3"
required>

@foreach($countries as $code => $country)

<option
value="{{ $code }}"
@selected(in_array($code,$adset->countries ?? []))>

{{ $country }}

</option>

@endforeach

</select>

</div>



{{-- LANGUAGES --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Languages</label>

<select
name="languages[]"
multiple
id="language-select"
class="w-full border rounded-xl px-4 py-3">

@foreach($languages as $id => $language)

<option
value="{{ $id }}"
@selected(in_array($id,$adset->languages ?? []))>

{{ $language }}

</option>

@endforeach

</select>

</div>



{{-- INTERESTS --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Interest Targeting</label>

<select
name="interests[]"
id="interest-select"
multiple
class="w-full border rounded-xl px-4 py-3"></select>

</div>



{{-- PLACEMENT --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Placement Strategy</label>

<select
name="placement_type"
id="placement-type"
class="w-full border rounded-xl px-4 py-3">

<option value="automatic"
@selected($adset->placement_type=='automatic')>
Automatic
</option>

<option value="manual"
@selected($adset->placement_type=='manual')>
Manual
</option>

</select>

</div>



{{-- PLATFORMS --}}
<div
class="mb-6 {{ $adset->placement_type=='manual' ? '' : 'hidden' }}"
id="platform-section">

<label class="font-semibold block mb-2">
Publisher Platforms
</label>

<select
name="publisher_platforms[]"
multiple
id="platform-select"
class="w-full border rounded-xl px-4 py-3">

<option value="facebook"
@selected(in_array('facebook',$adset->publisher_platforms ?? []))>
Facebook
</option>

<option value="instagram"
@selected(in_array('instagram',$adset->publisher_platforms ?? []))>
Instagram
</option>

<option value="messenger"
@selected(in_array('messenger',$adset->publisher_platforms ?? []))>
Messenger
</option>

<option value="audience_network"
@selected(in_array('audience_network',$adset->publisher_platforms ?? []))>
Audience Network
</option>

</select>

</div>



{{-- META ID --}}
@if($adset->meta_id)

<div class="bg-gray-50 border rounded-xl p-4 mb-6">

<div class="text-sm text-gray-600">

<div class="font-semibold">
Meta AdSet ID
</div>

<div class="text-xs font-mono text-gray-500 mt-1">
{{ $adset->meta_id }}
</div>

</div>

</div>

@endif



{{-- BUTTONS --}}
<div class="flex justify-between mt-8">

<button
type="submit"
class="bg-blue-600 text-white px-8 py-3 rounded-xl hover:bg-blue-700">
Update Ad Set
</button>

</form>



<form method="POST"
action="{{ route('admin.adsets.destroy',$adset) }}">

@csrf
@method('DELETE')

<button
onclick="return confirm('Delete this Ad Set?')"
class="bg-red-600 text-white px-8 py-3 rounded-xl hover:bg-red-700">
Delete
</button>

</form>

</div>

</div>

</div>



<script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>

<script>

new TomSelect("#country-select",{plugins:['remove_button']});
new TomSelect("#gender-select",{plugins:['remove_button']});
new TomSelect("#language-select",{plugins:['remove_button']});
new TomSelect("#platform-select",{plugins:['remove_button']});


let interestSelect = new TomSelect("#interest-select",{

plugins:['remove_button'],
valueField:'id',
labelField:'name',
searchField:'name',

load:function(query,callback){

if(query.length < 2) return callback();

fetch("/admin/meta/interests?q="+query)
.then(res=>res.json())
.then(data=>callback(data.data ?? []))
.catch(()=>callback());

}

});


// Prefill interests
let existingInterests = @json($adset->interests ?? []);

existingInterests.forEach(function(id){

interestSelect.addOption({id:id,name:id});
interestSelect.addItem(id);

});



document.getElementById("placement-type")
.addEventListener("change",function(){

let section=document.getElementById("platform-section");

if(this.value==="manual"){
section.classList.remove("hidden");
}else{
section.classList.add("hidden");
}

});



document.getElementById("adsetForm")
.addEventListener("submit",function(e){

let min=parseInt(document.querySelector("[name='age_min']").value);
let max=parseInt(document.querySelector("[name='age_max']").value);

if(min > max){

e.preventDefault();
alert("Minimum age cannot be greater than maximum age");

}

});

</script>

@endsection