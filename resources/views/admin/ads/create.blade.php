@extends('layouts.admin')

@section('content')

<div class="max-w-5xl mx-auto space-y-8">

{{-- HEADER --}}
<div class="flex items-center justify-between">

<div>
<h1 class="text-2xl font-bold text-gray-900">
Create Ad
</h1>

<p class="text-sm text-gray-500 mt-1">
Attach a creative and publish under an AdSet.
</p>
</div>

<a href="{{ route('admin.ads.index') }}"
class="text-gray-600 hover:text-gray-900">
← Back to Ads
</a>

</div>



{{-- ERRORS --}}
@if($errors->any())
<div class="bg-red-50 border-l-4 border-red-400 p-4 rounded">
<ul class="text-sm text-red-700 space-y-1">
@foreach($errors->all() as $error)
<li>{{ $error }}</li>
@endforeach
</ul>
</div>
@endif



<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

{{-- ================= FORM ================= --}}
<div class="bg-white rounded-xl shadow border p-6">

<form method="POST" action="{{ route('admin.ads.store') }}">
@csrf


{{-- AD NAME --}}
<div class="mb-5">

<label class="block text-sm font-medium mb-2">
Ad Name
</label>

<input
type="text"
name="name"
value="{{ old('name') }}"
class="w-full border rounded-lg px-4 py-2 focus:ring focus:ring-blue-200"
required>

</div>



{{-- ADSET --}}
<div class="mb-5">

<label class="block text-sm font-medium mb-2">
Ad Set
</label>

<select
name="adset_id"
class="w-full border rounded-lg px-4 py-2"
required>

@foreach($adsets as $adset)

<option
value="{{ $adset->id }}"
{{ old('adset_id') == $adset->id ? 'selected' : '' }}
>

{{ $adset->name }}
@if($adset->campaign)
— {{ $adset->campaign->name }}
@endif

</option>

@endforeach

</select>

</div>



{{-- CREATIVE --}}
<div class="mb-5">

<label class="block text-sm font-medium mb-2">
Creative
</label>

<select
name="creative_id"
id="creativeSelect"
class="w-full border rounded-lg px-4 py-2"
required>

<option value="">Select Creative</option>

@foreach($creatives as $creative)

<option
value="{{ $creative->id }}"
data-headline="{{ $creative->headline }}"
data-body="{{ $creative->body }}"
data-image="{{ $creative->image_url }}"
{{ old('creative_id') == $creative->id ? 'selected' : '' }}
>

{{ $creative->name }}

</option>

@endforeach

</select>

</div>



{{-- DAILY BUDGET --}}
<div class="mb-5">

<label class="block text-sm font-medium mb-2">
Daily Budget ($)
</label>

<input
type="number"
name="daily_budget"
value="{{ old('daily_budget',2) }}"
step="0.01"
min="0.10"
class="w-full border rounded-lg px-4 py-2"
required>

</div>



{{-- STATUS --}}
<div class="mb-6">

<label class="block text-sm font-medium mb-2">
Status
</label>

<select name="status" class="w-full border rounded-lg px-4 py-2">

<option value="PAUSED" {{ old('status') == 'PAUSED' ? 'selected' : '' }}>
Paused
</option>

<option value="ACTIVE" {{ old('status') == 'ACTIVE' ? 'selected' : '' }}>
Active
</option>

</select>

</div>



{{-- ACTIONS --}}
<div class="flex justify-between items-center">

<a href="{{ route('admin.ads.index') }}"
class="text-gray-500 hover:text-gray-800">
Cancel
</a>

<button
type="submit"
class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">

Create Ad

</button>

</div>


</form>

</div>



{{-- ================= PREVIEW ================= --}}
<div class="bg-white rounded-xl shadow border p-6">

<h3 class="font-semibold mb-4">
Creative Preview
</h3>

<div id="creativePreview"
class="text-gray-400 text-sm">

Select a creative to preview

</div>

</div>



</div>

</div>



{{-- ================= PREVIEW SCRIPT ================= --}}
<script>

const select = document.getElementById('creativeSelect');
const preview = document.getElementById('creativePreview');

function updatePreview(){

let option = select.options[select.selectedIndex];

if(!option || !option.dataset.headline){

preview.innerHTML = 'Select a creative to preview';
return;

}

let headline = option.dataset.headline ?? '';
let body = option.dataset.body ?? '';
let image = option.dataset.image ?? '';

let img = '';

if(image){

img = `<img src="${image}" class="rounded-lg w-full mb-3">`;

}

preview.innerHTML = `

<div class="border rounded-xl overflow-hidden">

${img}

<div class="p-4">

<h4 class="font-semibold mb-2">${headline}</h4>

<p class="text-sm text-gray-600">${body}</p>

</div>

</div>

`;

}

select.addEventListener('change', updatePreview);

window.addEventListener('load', updatePreview);

</script>

@endsection