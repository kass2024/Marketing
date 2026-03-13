@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto py-10 space-y-8">

{{-- HEADER --}}
<div class="flex justify-between items-center">

<div>
<h1 class="text-2xl font-bold">Edit Creative</h1>
<p class="text-gray-500 text-sm">
Update your ad creative and preview changes.
</p>
</div>

<a href="{{ route('admin.creatives.index') }}"
class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800">
Back
</a>

</div>


{{-- ALERTS --}}
@if(session('success'))
<div class="bg-green-100 text-green-700 p-4 rounded-lg">
{{ session('success') }}
</div>
@endif

@if($errors->any())
<div class="bg-red-100 text-red-700 p-4 rounded-lg">
{{ $errors->first() }}
</div>
@endif


<div class="grid grid-cols-1 lg:grid-cols-2 gap-10">


{{-- =========================================
FORM
========================================= --}}
<div>

<div class="bg-white shadow rounded-2xl p-10">

<h2 class="text-xl font-bold mb-8">
Creative Settings
</h2>

<form method="POST"
action="{{ route('admin.creatives.update',$creative->id) }}"
enctype="multipart/form-data"
id="creativeForm">

@csrf
@method('PUT')


{{-- NAME --}}
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


{{-- BODY --}}
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


{{-- DESTINATION --}}
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

<option value="LEARN_MORE" @selected($creative->call_to_action=='LEARN_MORE')>Learn More</option>
<option value="APPLY_NOW" @selected($creative->call_to_action=='APPLY_NOW')>Apply Now</option>
<option value="SIGN_UP" @selected($creative->call_to_action=='SIGN_UP')>Sign Up</option>
<option value="CONTACT_US" @selected($creative->call_to_action=='CONTACT_US')>Contact Us</option>
<option value="DOWNLOAD" @selected($creative->call_to_action=='DOWNLOAD')>Download</option>

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
class="rounded-xl w-72 shadow">

</div>

@endif


{{-- REPLACE IMAGE --}}
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

<option value="DRAFT" @selected($creative->status=='DRAFT')>Draft</option>
<option value="ACTIVE" @selected($creative->status=='ACTIVE')>Active</option>
<option value="PAUSED" @selected($creative->status=='PAUSED')>Paused</option>

</select>

</div>


{{-- META INFO --}}
@if($creative->meta_id)

<div class="mb-6 bg-gray-50 p-4 rounded-lg">

<p class="text-sm text-gray-600">
Meta Creative ID
</p>

<p class="font-semibold">
{{ $creative->meta_id }}
</p>

</div>

@endif


<div class="flex justify-between items-center">

<a
href="{{ route('admin.creatives.index') }}"
class="text-gray-600 hover:underline">
Cancel
</a>

<button
type="submit"
class="bg-blue-600 text-white px-6 py-3 rounded-xl hover:bg-blue-700">

Update Creative

</button>

</div>

</form>

</div>

</div>



{{-- =========================================
PREVIEW
========================================= --}}
<div>

<div class="bg-white shadow rounded-2xl p-6">

<h3 class="font-bold mb-6">
Ad Preview
</h3>

<img
id="preview-image"
src="{{ $creative->image_url ?? '' }}"
class="w-full rounded-xl mb-4">

<div id="preview-text" class="text-gray-700">
{{ $creative->body }}
</div>

<div
id="preview-headline"
class="font-semibold mt-3 text-lg">

{{ $creative->headline }}

</div>

<button
id="preview-cta"
class="mt-4 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">

{{ $creative->call_to_action ?? 'Learn More' }}

</button>

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

document.getElementById('preview-cta').innerText =
document.getElementById('cta').value.replace('_',' ');

}

</script>

@endsection