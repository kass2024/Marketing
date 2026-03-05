@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto">

<div class="bg-white shadow rounded-2xl p-10">

<h2 class="text-2xl font-bold mb-8">
Create Ad Creative
</h2>


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
class="w-full border rounded-xl px-4 py-3"
required
>

</div>


{{-- Headline --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Headline
</label>

<input
type="text"
name="headline"
class="w-full border rounded-xl px-4 py-3"
required
>

</div>


{{-- Ad Text --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Primary Text
</label>

<textarea
name="body"
rows="4"
class="w-full border rounded-xl px-4 py-3"
required
></textarea>

</div>


{{-- Destination URL --}}
<div class="mb-6">

<label class="block font-semibold mb-2">
Destination URL
</label>

<input
type="url"
name="link_url"
class="w-full border rounded-xl px-4 py-3"
placeholder="https://example.com"
required
>

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
>

</div>


<div class="flex justify-end">

<button
type="submit"
class="bg-blue-600 text-white px-6 py-3 rounded-xl shadow hover:bg-blue-700">

Create Creative

</button>

</div>

</form>

</div>

</div>

@endsection