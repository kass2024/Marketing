@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto py-10 grid grid-cols-1 lg:grid-cols-2 gap-10">


{{-- ================= FORM ================= --}}
<div>

<div class="bg-white shadow rounded-2xl p-10">

<h2 class="text-2xl font-bold mb-8">
Create Ad Creative
</h2>


@if($errors->any())
<div class="mb-6 bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl">
<ul class="list-disc ml-6">
@foreach ($errors->all() as $error)
<li>{{ $error }}</li>
@endforeach
</ul>
</div>
@endif


<form method="POST"
action="{{ route('admin.creatives.store') }}"
enctype="multipart/form-data"
id="creativeForm">

@csrf


{{-- CAMPAIGN --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Campaign
</label>

<select
name="campaign_id"
id="campaign-select"
class="w-full border rounded-xl px-4 py-3"
required>

<option value="">Select Campaign</option>

@foreach($campaigns as $campaign)

<option value="{{ $campaign->id }}">
{{ $campaign->name }}
</option>

@endforeach

</select>

</div>



{{-- ADSET --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Ad Set
</label>

<select
name="adset_id"
id="adset-select"
class="w-full border rounded-xl px-4 py-3"
required>

<option value="">Select AdSet</option>

@foreach($adsets as $adset)

<option value="{{ $adset->id }}">
{{ $adset->name }}
</option>

@endforeach

</select>

</div>



{{-- PAGE --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Facebook Page
</label>

<select
name="page_id"
class="w-full border rounded-xl px-4 py-3"
required>

<option value="">Select Page</option>

@foreach($pages as $page)

<option value="{{ $page['id'] }}">
{{ $page['name'] }}
</option>

@endforeach

</select>

</div>



{{-- CREATIVE NAME --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Creative Name
</label>

<input
type="text"
name="name"
value="{{ old('name') }}"
class="w-full border rounded-xl px-4 py-3"
required>

</div>



{{-- HEADLINE --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Headline
</label>

<input
type="text"
name="headline"
id="headline"
class="w-full border rounded-xl px-4 py-3"
oninput="updatePreview()">

</div>



{{-- PRIMARY TEXT --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Primary Text
</label>

<textarea
name="body"
id="body"
rows="4"
class="w-full border rounded-xl px-4 py-3"
oninput="updatePreview()"></textarea>

</div>



{{-- DESTINATION URL --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Destination URL
</label>

<input
type="url"
name="destination_url"
class="w-full border rounded-xl px-4 py-3"
placeholder="https://yourwebsite.com">

</div>



{{-- CTA --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Call To Action
</label>

<select
name="call_to_action"
id="cta"
class="w-full border rounded-xl px-4 py-3"
onchange="updatePreview()">

<option value="">None</option>
<option value="LEARN_MORE">Learn More</option>
<option value="APPLY_NOW">Apply Now</option>
<option value="SIGN_UP">Sign Up</option>
<option value="CONTACT_US">Contact Us</option>
<option value="DOWNLOAD">Download</option>

</select>

</div>



{{-- IMAGE --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Creative Image
</label>

<input
type="file"
name="image"
accept="image/*"
class="w-full border rounded-xl px-4 py-3"
onchange="previewImage(event)"
required>

</div>



{{-- META SYNC --}}
<div class="mb-6">

<label class="flex items-center gap-3">

<input
type="checkbox"
name="sync_meta"
value="1"
checked
class="w-5 h-5">

<span class="font-medium">
Sync with Meta Ads
</span>

</label>

<p class="text-sm text-gray-500 mt-1">
Creative will be uploaded to Facebook Ads Manager.
</p>

</div>



{{-- STATUS --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Status
</label>

<select
name="status"
class="w-full border rounded-xl px-4 py-3">

<option value="DRAFT">Draft</option>
<option value="ACTIVE">Active</option>

</select>

</div>



<div class="flex justify-between">

<a
href="{{ route('admin.creatives.index') }}"
class="text-gray-600 hover:text-gray-900">

Cancel

</a>


<button
type="submit"
class="bg-blue-600 text-white px-6 py-3 rounded-xl shadow hover:bg-blue-700">

Create Creative

</button>

</div>

</form>

</div>

</div>



{{-- ================= PREVIEW ================= --}}
<div>

<div class="bg-white shadow rounded-2xl p-6">

<h3 class="font-bold mb-6">
Facebook Feed Preview
</h3>


<div class="border rounded-xl overflow-hidden max-w-md mx-auto bg-white">

<div class="p-4 text-sm">

<div class="font-semibold">
Facebook Page
</div>

<div id="preview-text"
class="text-gray-700 mt-2">

Ad text preview

</div>

</div>


<img
id="preview-image"
class="w-full hidden">


<div class="p-4">

<div
id="preview-headline"
class="font-semibold text-sm">

Headline preview

</div>

<button
id="preview-cta"
class="mt-3 bg-blue-600 text-white text-sm px-4 py-2 rounded">

Call To Action

</button>

</div>

</div>

</div>

</div>


</div>



<script>

function previewImage(event){

let reader = new FileReader();

reader.onload = function(){

let img = document.getElementById('preview-image');
img.src = reader.result;
img.classList.remove('hidden');

};

reader.readAsDataURL(event.target.files[0]);

}


function updatePreview(){

document.getElementById('preview-text').innerText =
document.getElementById('body').value;

document.getElementById('preview-headline').innerText =
document.getElementById('headline').value;

let cta = document.getElementById('cta').value;

if(cta){
document.getElementById('preview-cta').innerText =
cta.replace('_',' ');
}

}

</script>


@endsection