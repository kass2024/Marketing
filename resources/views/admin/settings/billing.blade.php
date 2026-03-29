@extends('layouts.admin')

@section('content')

<div class="max-w-7xl mx-auto py-10 space-y-8">

{{-- HEADER --}}
<div class="flex items-center justify-between">

<div>
<h1 class="text-2xl font-bold text-gray-900">
Meta Ads Billing
</h1>

<p class="text-sm text-gray-500">
Monitor ad account status, currency, and payment method
</p>
</div>

<a href="{{ route('admin.settings.index') }}"
class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800">
Back to Settings
</a>

</div>


@if(isset($error))

<div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl">
{{ $error }}
</div>

@endif


@if($billing)

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">

{{-- ACCOUNT --}}
<div class="bg-white border rounded-xl shadow p-6">

<p class="text-xs text-gray-500">
Ad Account
</p>

<p class="text-lg font-semibold text-gray-900">
{{ $billing['name'] ?? 'Unknown' }}
</p>

<p class="text-sm text-gray-500">
ID: {{ $billing['id'] ?? '-' }}
</p>

</div>


{{-- STATUS --}}
<div class="bg-white border rounded-xl shadow p-6">

<p class="text-xs text-gray-500">
Account Status
</p>

@php
$status = $billing['account_status'] ?? 0;
@endphp

@if($status == 1)

<p class="text-green-600 font-semibold">
Active
</p>

@else

<p class="text-red-600 font-semibold">
Inactive
</p>

@endif

</div>


{{-- CURRENCY --}}
<div class="bg-white border rounded-xl shadow p-6">

<p class="text-xs text-gray-500">
Currency
</p>

<p class="text-lg font-semibold text-gray-900">
{{ $billing['currency'] ?? 'N/A' }}
</p>

</div>


{{-- PAYMENT METHOD --}}
<div class="bg-white border rounded-xl shadow p-6">

<p class="text-xs text-gray-500">
Payment Method
</p>

<p class="text-lg font-semibold text-gray-900">

{{ $billing['funding_source_details']['display_string'] ?? 'Not Available' }}

</p>

</div>


{{-- SPEND CAP --}}
<div class="bg-white border rounded-xl shadow p-6">

<p class="text-xs text-gray-500">
Spend Cap
</p>

<p class="text-lg font-semibold text-gray-900">

@if(isset($billing['spend_cap']) && $billing['spend_cap'] > 0)

${{ number_format($billing['spend_cap']/100,2) }}

@else

Unlimited

@endif

</p>

</div>

</div>

@endif


{{-- META BILLING LINK --}}
<div class="bg-gray-50 border rounded-xl p-6 flex items-center justify-between">

<div>

<p class="text-sm text-gray-600">
Need to manage payment methods or pay invoices?
</p>

<p class="text-xs text-gray-500">
Billing changes must be done directly in Meta Ads Manager.
</p>

</div>

<a
href="https://business.facebook.com/settings/payment-methods"
target="_blank"
class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">

Open Meta Billing

</a>

</div>


</div>

@endsection