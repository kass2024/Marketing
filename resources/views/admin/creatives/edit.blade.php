@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto py-10 grid grid-cols-1 lg:grid-cols-2 gap-10">

{{-- FORM --}}
<div>

<div class="bg-white shadow rounded-2xl p-10">

<h2 class="text-2xl font-bold mb-8">
Edit Ad Creative
</h2>

<form method="POST"
action="{{ route('admin.creatives.update',$creative->id) }}"
enctype="multipart/form-data"
id="creativeForm">

@csrf
@method('PUT')


{{-- CREATIVE NAME --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Creative Name
</label>

<input
type="text"
name="name"
value="{{ old('name',$creative->name) }}"
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
value="{{ old('headline',$creative->headline) }}"
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
oninput="updatePreview()">{{ old('body',$creative->body) }}</textarea>

</div>


{{-- DESTINATION URL --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Destination URL
</label>

<input
type="url"
name="destination_url"
value="{{ old('destination_url',$creative->destination_url) }}"
class="w-full border rounded-xl px-4 py-3">

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

<option value="LEARN_MORE" @selected($creative->call_to_action=='LEARN_MORE')>
Learn More
</option>

<option value="APPLY_NOW" @selected($creative->call_to_action=='APPLY_NOW')>
Apply Now
</option>

<option value="SIGN_UP" @selected($creative->call_to_action=='SIGN_UP')>
Sign Up
</option>

<option value="CONTACT_US" @selected($creative->call_to_action=='CONTACT_US')>
Contact Us
</option>

<option value="DOWNLOAD" @selected($creative->call_to_action=='DOWNLOAD')>
Download
</option>

</select>

</div>


{{-- CURRENT IMAGE --}}
@if($creative->image_url)

<div class="mb-6">

<label class="block font-semibold mb-2">
Current Image
</label>

<img
src="{{ $creative->image_url }}"
class="rounded-xl w-60">

</div>

@endif


{{-- NEW IMAGE --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Replace Image
</label>

<input
type="file"
name="image"
accept="image/*"
class="w-full border rounded-xl px-4 py-3"
onchange="previewImage(event)">

</div>


{{-- STATUS --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Status
</label>

<select
name="status"
class="w-full border rounded-xl px-4 py-3">

<option value="DRAFT" @selected($creative->status=='DRAFT')>
Draft
</option>

<option value="ACTIVE" @selected($creative->status=='ACTIVE')>
Active
</option>

<option value="PAUSED" @selected($creative->status=='PAUSED')>
Paused
</option>

</select>

</div>


<div class="flex justify-between">

<a
href="{{ route('admin.creatives.index') }}"
class="text-gray-600">

Cancel

</a>

<button
type="submit"
class="bg-blue-600 text-white px-6 py-3 rounded-xl">

Update Creative

</button>

</div>

</form>

</div>

</div>


{{-- PREVIEW --}}
<div>

<div class="bg-white shadow rounded-2xl p-6">

<h3 class="font-bold mb-6">
Preview
</h3>

<img
id="preview-image"
src="{{ $creative->image_url }}"
class="w-full rounded-xl mb-4">

<div id="preview-text">
{{ $creative->body }}
</div>

<div id="preview-headline"
class="font-semibold mt-3">

{{ $creative->headline }}

</div>

</div>

</div>

</div>

<script>

function previewImage(event){

let reader = new FileReader();

reader.onload = function(){

document.getElementById('preview-image').src = reader.result;

};

reader.readAsDataURL(event.target.files[0]);

}

function updatePreview(){

document.getElementById('preview-text').innerText =
document.getElementById('body').value;

document.getElementById('preview-headline').innerText =
document.getElementById('headline').value;

}

</script>

@endsection