@extends('layouts.app')

@section('content')
<div class="bg-white p-10 rounded-2xl shadow max-w-4xl mx-auto">

    <h2 class="text-2xl font-bold mb-6">
        Create Ad Set for: {{ $campaign->name }}
    </h2>

<form method="POST" action="{{ route('admin.adsets.store') }}">
@csrf

<input type="hidden" name="campaign_id" value="{{ $campaign->id }}">

{{-- Name --}}
<div class="mb-5">
    <label class="block font-medium mb-2">Ad Set Name</label>
    <input type="text" name="name" class="w-full border rounded-xl px-4 py-3" required>
</div>

{{-- Budget --}}
<div class="mb-5">
    <label class="block font-medium mb-2">Daily Budget (CAD)</label>
    <input type="number" name="daily_budget" class="w-full border rounded-xl px-4 py-3" required>
</div>

{{-- Age --}}
<div class="grid grid-cols-2 gap-4 mb-5">
    <div>
        <label>Age Min</label>
        <input type="number" name="age_min" class="w-full border rounded-xl px-4 py-3" value="22">
    </div>
    <div>
        <label>Age Max</label>
        <input type="number" name="age_max" class="w-full border rounded-xl px-4 py-3" value="45">
    </div>
</div>

{{-- Gender --}}
<div class="mb-5">
    <label class="block mb-2">Gender</label>
    <select name="genders[]" multiple class="w-full border rounded-xl px-4 py-3">
        <option value="1">Male</option>
        <option value="2">Female</option>
    </select>
</div>

{{-- Countries --}}
<div class="mb-5">
    <label class="block mb-2">Countries</label>
    <select name="countries[]" multiple class="w-full border rounded-xl px-4 py-3">
        <option value="CA">Canada</option>
        <option value="IN">India</option>
        <option value="NG">Nigeria</option>
        <option value="US">United States</option>
    </select>
</div>

<div class="flex justify-end mt-6">
    <button type="submit"
        class="bg-blue-600 text-white px-6 py-3 rounded-xl shadow hover:bg-blue-700">
        Create Ad Set
    </button>
</div>

</form>

</div>
@endsection