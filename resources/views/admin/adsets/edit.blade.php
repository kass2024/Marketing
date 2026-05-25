@extends('layouts.admin')

@section('content')

<link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">

<div class="max-w-6xl mx-auto space-y-8 py-10">

{{-- HEADER --}}
<div class="flex justify-between items-center">

<div>
<h1 class="text-3xl font-bold text-gray-900">Edit Ad Set</h1>
<p class="text-sm text-gray-500">Modify targeting, budget and delivery settings</p>
</div>

<x-admin.page-back :href="route('admin.adsets.index')" label="Back to Ad Sets" />

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
step="0.01"
min="5"
name="daily_budget"
value="{{ old('daily_budget', $adset->daily_budget_dollars) }}"
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

<option value="ACTIVE" @selected(old('status',$adset->status)=='ACTIVE')>
Active
</option>

<option value="PAUSED" @selected(old('status',$adset->status)=='PAUSED')>
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
@selected(old('page_id',$adset->page_id ?? '')==$page['id'])>

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
@selected(in_array(1,old('genders',$adset->genders ?? [])))>
Male
</option>

<option value="2"
@selected(in_array(2,old('genders',$adset->genders ?? [])))>
Female
</option>

</select>

</div>



{{-- GEO TARGETING --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Location Targeting</label>

<select
name="geo_mode"
id="geo-mode"
class="w-full border rounded-xl px-4 py-3"
required>

<option value="countries_only"
@selected(old('geo_mode', $adset->geo_mode ?? 'countries_only') === 'countries_only')>
Entire selected countries
</option>

<option value="countries_and_cities"
@selected(old('geo_mode', $adset->geo_mode ?? 'countries_only') === 'countries_and_cities')>
Selected countries and/or specific cities
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
@selected(in_array($code,old('countries',$adset->countries ?? [])))>

{{ $country }}

</option>

@endforeach

</select>

</div>



{{-- CITIES --}}
<div class="mb-6 {{ old('geo_mode', $adset->geo_mode ?? 'countries_only') === 'countries_and_cities' ? '' : 'hidden' }}" id="city-section">

<label class="font-semibold block mb-2">Cities (optional)</label>

<select
id="city-select"
multiple
class="w-full border rounded-xl px-4 py-3"></select>

<input
type="hidden"
name="cities_json"
id="cities-json"
value="{{ old('cities_json', json_encode($adset->cities ?? [])) }}">

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
@selected(in_array($id,old('languages',$adset->languages ?? [])))>

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



{{-- PLACEMENT STRATEGY --}}
<div class="mb-6">

<label class="font-semibold block mb-2">Placement Strategy</label>

<select
name="placement_type"
id="placement-type"
class="w-full border rounded-xl px-4 py-3">

<option value="automatic"
@selected(old('placement_type',$adset->placement_type)=='automatic')>
Automatic
</option>

<option value="manual"
@selected(old('placement_type',$adset->placement_type)=='manual')>
Manual
</option>

</select>

</div>



{{-- PLATFORMS --}}
<div
class="mb-6 {{ old('placement_type',$adset->placement_type)=='manual' ? '' : 'hidden' }}"
id="platform-section">

<label class="font-semibold block mb-2">
Publisher Platforms
</label>

<select
name="publisher_platforms[]"
multiple
id="platform-select"
class="w-full border rounded-xl px-4 py-3">

@foreach(['facebook','instagram','messenger','audience_network'] as $platform)

<option value="{{ $platform }}"
@selected(in_array($platform,old('publisher_platforms',$adset->publisher_platforms ?? [])))>

{{ ucfirst(str_replace('_',' ',$platform)) }}

</option>

@endforeach

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

const countrySelect = new TomSelect("#country-select",{plugins:['remove_button']});
new TomSelect("#gender-select",{plugins:['remove_button']});
new TomSelect("#language-select",{plugins:['remove_button']});
new TomSelect("#platform-select",{plugins:['remove_button']});

const geoModeSelect = document.getElementById("geo-mode");
const citySection = document.getElementById("city-section");
const citiesJsonInput = document.getElementById("cities-json");

let selectedCities = [];

try {
    selectedCities = JSON.parse(citiesJsonInput.value || "[]");
    if (!Array.isArray(selectedCities)) selectedCities = [];
} catch (e) {
    selectedCities = [];
}

function syncCitiesJson() {
    citiesJsonInput.value = JSON.stringify(selectedCities);
}

function cityLabel(city) {
    const parts = [city.name || city.key];
    if (city.region) parts.push(city.region);
    if (city.country) parts.push(city.country);
    return parts.join(", ");
}

let citySelect = new TomSelect("#city-select", {
    plugins: ['remove_button'],
    valueField: 'key',
    labelField: 'label',
    searchField: ['label', 'name', 'region'],
    maxItems: 50,
    options: selectedCities.map(city => ({
        key: city.key,
        label: cityLabel(city),
        name: city.name || city.key,
        region: city.region || '',
        country: city.country || '',
        region_id: city.region_id || null,
    })),
    items: selectedCities.map(city => city.key),
    render: {
        option: function(item, escape) {
            return `<div>${escape(item.label || item.name || item.key)}</div>`;
        },
        item: function(item, escape) {
            return `<div>${escape(item.label || item.name || item.key)}</div>`;
        }
    },
    load: function(query, callback) {
        if (query.length < 2) return callback();

        const params = new URLSearchParams({
            q: query,
            type: 'city',
        });

        const countries = countrySelect.getValue();
        if (countries.length === 1) {
            params.set('country', countries[0]);
        }

        fetch("/admin/meta/geo?" + params.toString())
            .then(res => res.json())
            .then(data => {
                callback((data.data ?? []).map(city => ({
                    key: city.key,
                    label: [city.name, city.region, city.country_code].filter(Boolean).join(", "),
                    name: city.name,
                    region: city.region || '',
                    country: city.country_code || '',
                    region_id: city.region_id || null,
                })));
            })
            .catch(() => callback());
    },
    onItemAdd: function(value, item) {
        const exists = selectedCities.some(city => city.key === value);
        if (!exists) {
            selectedCities.push({
                key: value,
                name: item.name || item.label || value,
                region: item.region || '',
                country: item.country || '',
                region_id: item.region_id || null,
            });
            syncCitiesJson();
        }
    },
    onItemRemove: function(value) {
        selectedCities = selectedCities.filter(city => city.key !== value);
        syncCitiesJson();
    }
});

function toggleCitySection() {
    const showCities = geoModeSelect.value === "countries_and_cities";
    citySection.classList.toggle("hidden", !showCities);
}

geoModeSelect.addEventListener("change", toggleCitySection);
countrySelect.on("change", () => citySelect.clearOptions());
toggleCitySection();
syncCitiesJson();

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

document.querySelector("form").addEventListener("submit", function() {
    syncCitiesJson();
});

</script>

@endsection