@extends('layouts.app')

@section('content')

<div class="max-w-5xl mx-auto py-10 px-6 space-y-8">

{{-- HEADER --}}

<div class="flex items-center justify-between">

<div>
<h1 class="text-2xl font-bold text-gray-900">
Create Ad Set
</h1>

<p class="text-gray-500 text-sm mt-1">
Configure targeting and budget for your campaign.
</p>
</div>

<a href="{{ route('admin.campaigns.index') }}"
class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">
← Back to Dashboard
</a>

</div>


{{-- ERROR --}}

@if ($errors->any())
<div class="bg-red-100 border border-red-200 text-red-700 p-4 rounded-lg">
<ul class="list-disc ml-6 text-sm">
@foreach ($errors->all() as $error)
<li>{{ $error }}</li>
@endforeach
</ul>
</div>
@endif


{{-- FORM --}}

<form method="POST" action="{{ route('admin.adsets.store') }}"
class="bg-white shadow rounded-xl p-8 space-y-8">

@csrf


{{-- CAMPAIGN --}}

<div>

<label class="block text-sm font-semibold text-gray-700 mb-2">
Campaign
</label>

<select name="campaign_id"
class="w-full border rounded-lg px-3 py-2">

<option value="">Select Campaign</option>

@foreach($campaigns as $campaign)

<option value="{{ $campaign->id }}"
@if(old('campaign_id',$selectedCampaign)==$campaign->id) selected @endif>

{{ $campaign->name }}

</option>

@endforeach

</select>

</div>


{{-- NAME --}}

<div>

<label class="block text-sm font-semibold text-gray-700 mb-2">
Ad Set Name
</label>

<input type="text"
name="name"
value="{{ old('name') }}"
class="w-full border rounded-lg px-3 py-2"
placeholder="Example: UK Study – Rwanda Students">

</div>


{{-- DAILY BUDGET --}}

<div>

<label class="block text-sm font-semibold text-gray-700 mb-2">
Daily Budget ($)
</label>

<input type="number"
name="daily_budget"
value="{{ old('daily_budget',20) }}"
class="w-full border rounded-lg px-3 py-2">

</div>


{{-- PAGE --}}

<div>

<label class="block text-sm font-semibold text-gray-700 mb-2">
Facebook Page
</label>

<select name="page_id"
class="w-full border rounded-lg px-3 py-2">

@foreach($pages as $page)

<option value="{{ $page['id'] }}">
{{ $page['name'] }}
</option>

@endforeach

</select>

</div>


{{-- AGE --}}

<div class="grid grid-cols-2 gap-6">

<div>

<label class="block text-sm font-semibold text-gray-700 mb-2">
Minimum Age
</label>

<input type="number"
name="age_min"
value="{{ old('age_min',18) }}"
class="w-full border rounded-lg px-3 py-2">

</div>

<div>

<label class="block text-sm font-semibold text-gray-700 mb-2">
Maximum Age
</label>

<input type="number"
name="age_max"
value="{{ old('age_max',35) }}"
class="w-full border rounded-lg px-3 py-2">

</div>

</div>


{{-- COUNTRIES --}}

<div>

<label class="block text-sm font-semibold text-gray-700 mb-2">
Target Countries
</label>

<select name="countries[]" multiple
class="w-full border rounded-lg px-3 py-2 h-36">

@foreach($countries as $code=>$name)

<option value="{{ $code }}"
@if(in_array($code,old('countries',[]))) selected @endif>

{{ $name }}

</option>

@endforeach

</select>

<p class="text-xs text-gray-500 mt-1">
Hold CTRL or CMD to select multiple countries
</p>

</div>


{{-- GENDERS --}}

<div>

<label class="block text-sm font-semibold text-gray-700 mb-2">
Gender
</label>

<div class="flex gap-6">

<label class="flex items-center gap-2">

<input type="checkbox" name="genders[]" value="1">
Male

</label>

<label class="flex items-center gap-2">

<input type="checkbox" name="genders[]" value="2">
Female

</label>

</div>

</div>


{{-- INTERESTS --}}

<div>

<label class="block text-sm font-semibold text-gray-700 mb-2">
Interest IDs
</label>

<textarea name="interests[]"
class="w-full border rounded-lg px-3 py-2"
placeholder="Example: 6003246559157"></textarea>

<p class="text-xs text-gray-500 mt-1">
Enter interest IDs separated by comma
</p>

</div>


{{-- PLACEMENTS --}}

<div>

<label class="block text-sm font-semibold text-gray-700 mb-2">
Placements
</label>

<select name="placement_type"
class="w-full border rounded-lg px-3 py-2">

<option value="automatic">Automatic Placements</option>

<option value="manual">Manual Placements</option>

</select>

</div>


{{-- MANUAL PLACEMENTS --}}

<div>

<label class="block text-sm font-semibold text-gray-700 mb-2">
Publisher Platforms
</label>

<div class="grid grid-cols-3 gap-4 text-sm">

<label><input type="checkbox" name="publisher_platforms[]" value="facebook"> Facebook</label>

<label><input type="checkbox" name="publisher_platforms[]" value="instagram"> Instagram</label>

<label><input type="checkbox" name="publisher_platforms[]" value="audience_network"> Audience Network</label>

<label><input type="checkbox" name="publisher_platforms[]" value="messenger"> Messenger</label>

</div>

</div>


{{-- BID STRATEGY --}}

<div>

<label class="block text-sm font-semibold text-gray-700 mb-2">
Bid Strategy
</label>

<select name="bid_strategy"
class="w-full border rounded-lg px-3 py-2">

<option value="LOWEST_COST_WITHOUT_CAP">
Lowest Cost
</option>

<option value="COST_CAP">
Cost Cap
</option>

</select>

</div>


{{-- SUBMIT --}}

<div class="flex justify-end gap-4 pt-6 border-t">

<a href="{{ route('admin.campaigns.index') }}"
class="px-5 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-700">

Cancel

</a>

<button type="submit"
class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold">

Create Ad Set

</button>

</div>


</form>

</div>

@endsection