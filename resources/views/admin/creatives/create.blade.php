@extends('layouts.app')

@section('content')

<div class="max-w-6xl mx-auto py-10 grid grid-cols-1 lg:grid-cols-2 gap-10">

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
      enctype="multipart/form-data">

@csrf


{{-- Creative Name --}}
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


{{-- Headline --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Headline
</label>

<input
type="text"
name="headline"
value="{{ old('headline') }}"
class="w-full border rounded-xl px-4 py-3"
required>

</div>


{{-- Primary Text --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Primary Text
</label>

<textarea
name="body"
rows="4"
class="w-full border rounded-xl px-4 py-3"
required>{{ old('body') }}</textarea>

</div>


{{-- Description --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Description (optional)
</label>

<input
type="text"
name="description"
value="{{ old('description') }}"
class="w-full border rounded-xl px-4 py-3">

</div>


{{-- Destination URL --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Destination URL
</label>

<input
type="url"
name="link_url"
value="{{ old('link_url') }}"
class="w-full border rounded-xl px-4 py-3"
placeholder="https://example.com"
required>

</div>


{{-- CTA --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Call To Action
</label>

<select
name="cta"
class="w-full border rounded-xl px-4 py-3">

<option value="LEARN_MORE">Learn More</option>
<option value="SIGN_UP">Sign Up</option>
<option value="APPLY_NOW">Apply Now</option>
<option value="CONTACT_US">Contact Us</option>
<option value="BOOK_NOW">Book Now</option>

</select>

</div>


{{-- Image Upload --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Creative Image
</label>

<input
type="file"
name="image"
accept="image/*"
class="w-full border rounded-xl px-4 py-3"
required
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

<option value="DRAFT">Draft</option>
<option value="ACTIVE">Active</option>

</select>

</div>


<div class="flex justify-between items-center">

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



{{-- ================= LIVE PREVIEW ================= --}}
<div>

<div class="bg-white shadow rounded-2xl p-6">

<h3 class="font-bold mb-4">
Creative Preview
</h3>


<div class="border rounded-xl overflow-hidden max-w-sm mx-auto">

<div class="p-4 text-sm text-gray-700">

<div class="font-semibold mb-1">
Page Name
</div>

<div id="preview-text" class="text-gray-600">
Your ad text will appear here.
</div>

</div>


<img
id="preview-image"
class="w-full hidden"
>


<div class="p-4">

<div id="preview-headline"
class="font-semibold text-sm">
Headline preview
</div>

<div id="preview-description"
class="text-xs text-gray-500 mt-1">
Description
</div>


<button
class="mt-3 bg-blue-600 text-white text-sm px-4 py-2 rounded">
Learn More
</button>

</div>

</div>

</div>

</div>

</div>



<script>

function previewImage(event)
{
let reader = new FileReader();

reader.onload = function(){
let img = document.getElementById('preview-image');
img.src = reader.result;
img.classList.remove('hidden');
};

reader.readAsDataURL(event.target.files[0]);
}

</script>


@endsection