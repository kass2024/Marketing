@extends('layouts.admin')

@section('title', 'Create creative')

@section('content')

<div class="mx-auto grid max-w-7xl grid-cols-1 gap-10 py-2 lg:grid-cols-2 lg:py-4">


{{-- ================= FORM ================= --}}
<div>

<div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm ring-1 ring-slate-900/5 sm:p-10">

<div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0">
        <h2 class="text-2xl font-bold tracking-tight text-slate-900">Create ad creative</h2>
        <p class="mt-1 text-sm text-slate-600">Upload or define creative assets for your ads.</p>
    </div>
    <a href="{{ route('admin.creatives.index') }}" class="inline-flex shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-xander-navy/25 hover:text-xander-navy">All creatives</a>
</div>


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
class="w-full rounded-xl border border-slate-200 px-4 py-3 shadow-sm focus:border-xander-navy focus:ring-2 focus:ring-xander-navy/20"
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
class="w-full rounded-xl border border-slate-200 px-4 py-3 shadow-sm focus:border-xander-navy focus:ring-2 focus:ring-xander-navy/20"
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
class="w-full rounded-xl border border-slate-200 px-4 py-3 shadow-sm focus:border-xander-navy focus:ring-2 focus:ring-xander-navy/20"
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
class="w-full rounded-xl border border-slate-200 px-4 py-3 shadow-sm focus:border-xander-navy focus:ring-2 focus:ring-xander-navy/20"
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
class="w-full rounded-xl border border-slate-200 px-4 py-3 shadow-sm focus:border-xander-navy focus:ring-2 focus:ring-xander-navy/20">

<option value="DRAFT">Draft</option>
<option value="ACTIVE">Active</option>

</select>

</div>



<div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
    <a href="{{ route('admin.creatives.index') }}" class="text-center text-sm font-semibold text-slate-600 transition hover:text-xander-navy sm:text-left">Cancel</a>
    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-xander-navy px-6 py-3 font-semibold text-white shadow-sm transition hover:bg-xander-secondary sm:w-auto">
        Create creative
    </button>
</div>

</form>

</div>

</div>



{{-- ================= PREVIEW ================= --}}
<div>

<div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm ring-1 ring-slate-900/5">

<h3 class="mb-6 font-bold text-slate-900">
Facebook feed preview
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
class="mt-3 rounded-lg bg-xander-navy px-4 py-2 text-sm font-semibold text-white shadow-sm">

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