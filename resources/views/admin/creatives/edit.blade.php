@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto py-10 space-y-8">

<div class="flex items-center justify-between">

<div>
<h1 class="text-2xl font-bold text-gray-900">
Edit Creative
</h1>

<p class="text-sm text-gray-500">
Update creative details before publishing ads.
</p>
</div>

<a href="{{ route('admin.creatives.index') }}"
class="text-gray-600 hover:text-gray-800">
← Back
</a>

</div>


<div class="bg-white p-8 rounded-xl shadow border">

<form method="POST"
action="{{ route('admin.creatives.update',$creative->id) }}">

@csrf
@method('PUT')


{{-- NAME --}}
<div class="mb-6">

<label class="block text-sm font-medium text-gray-600">
Creative Name
</label>

<input
type="text"
name="name"
value="{{ old('name',$creative->name) }}"
class="w-full border rounded-lg px-4 py-2 mt-1 focus:ring-2 focus:ring-blue-500"
required>

</div>


{{-- HEADLINE --}}
<div class="mb-6">

<label class="block text-sm font-medium text-gray-600">
Headline
</label>

<input
type="text"
name="headline"
value="{{ old('headline',$creative->headline) }}"
class="w-full border rounded-lg px-4 py-2 mt-1">

</div>


{{-- BODY --}}
<div class="mb-6">

<label class="block text-sm font-medium text-gray-600">
Ad Text
</label>

<textarea
name="body"
rows="4"
class="w-full border rounded-lg px-4 py-2 mt-1">{{ old('body',$creative->body) }}</textarea>

</div>


{{-- CTA --}}
<div class="mb-6">

<label class="block text-sm font-medium text-gray-600">
Call To Action
</label>

<select
name="call_to_action"
class="w-full border rounded-lg px-4 py-2 mt-1">

<option value="">Select CTA</option>

<option value="LEARN_MORE" @selected($creative->call_to_action=='LEARN_MORE')>
Learn More
</option>

<option value="SIGN_UP" @selected($creative->call_to_action=='SIGN_UP')>
Sign Up
</option>

<option value="BOOK_NOW" @selected($creative->call_to_action=='BOOK_NOW')>
Book Now
</option>

</select>

</div>


{{-- DESTINATION URL --}}
<div class="mb-6">

<label class="block text-sm font-medium text-gray-600">
Destination URL
</label>

<input
type="url"
name="destination_url"
value="{{ old('destination_url',$creative->destination_url) }}"
class="w-full border rounded-lg px-4 py-2 mt-1">

</div>


{{-- STATUS --}}
<div class="mb-6">

<label class="block text-sm font-medium text-gray-600">
Status
</label>

<select
name="status"
class="w-full border rounded-lg px-4 py-2 mt-1">

<option value="DRAFT" @selected($creative->status=='DRAFT')>Draft</option>
<option value="ACTIVE" @selected($creative->status=='ACTIVE')>Active</option>
<option value="PAUSED" @selected($creative->status=='PAUSED')>Paused</option>

</select>

</div>


{{-- SUBMIT --}}
<div class="flex justify-end">

<button
type="submit"
class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">

Update Creative

</button>

</div>

</form>

</div>

</div>

@endsection