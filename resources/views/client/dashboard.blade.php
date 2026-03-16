<x-app-layout>

@php
$user = auth()->user();
$client = $user->client ?? null;

$meta = $client?->metaConnection;

$plan = $client->subscription_plan ?? 'free';
$isFree = $plan === 'free';

$botCount = $client?->chatbots()->count() ?? 0;
$templateCount = $client?->templates()->count() ?? 0;

$campaignCount = $kpis['total_campaigns'] ?? 0;

$activeCampaigns = $kpis['active_campaigns'] ?? 0;
$totalBudget = $kpis['total_budget'] ?? 0;
$totalSpend = $kpis['total_spend'] ?? 0;
$totalLeads = $kpis['total_leads'] ?? 0;

$ctr = $kpis['ctr'] ?? 0;
$conversionRate = $kpis['conversion_rate'] ?? 0;
$cpc = $kpis['cpc'] ?? 0;
$cpa = $kpis['cpa'] ?? 0;
@endphp


{{-- PAGE HEADER --}}
<x-slot name="header">

<div class="flex items-center justify-between">

<div>
<h2 class="text-2xl font-bold text-gray-900">
Client Dashboard
</h2>

<p class="text-sm text-gray-500">
Manage campaigns, automation and conversations
</p>
</div>

</div>

</x-slot>



<div class="py-8 bg-gray-50 min-h-screen">

<div class="max-w-7xl mx-auto px-4 space-y-8">


{{-- TOP TOOLBAR --}}
<div class="flex items-center justify-between">

<div class="text-sm text-gray-500">
{{ now()->format('l d M Y') }}
</div>

<div class="flex items-center gap-3">

@if(!$meta)
<a
href="{{ route('client.meta.connect') }}"
class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
Connect Meta
</a>
@endif

<form method="POST" action="{{ route('logout') }}">
@csrf

<button
type="submit"
class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
Logout
</button>

</form>

</div>

</div>



{{-- ACCOUNT OVERVIEW --}}
<div class="bg-white rounded-2xl border shadow-sm p-6">

<div class="grid grid-cols-2 md:grid-cols-5 gap-6 text-center">

<div>
<p class="text-xs text-gray-500 uppercase">Meta Status</p>
<p class="mt-2 font-semibold {{ $meta ? 'text-green-600' : 'text-red-500' }}">
{{ $meta ? 'Connected' : 'Not Connected' }}
</p>
</div>

<div>
<p class="text-xs text-gray-500 uppercase">Subscription</p>
<p class="mt-2 font-semibold">
{{ ucfirst($plan) }}
</p>
</div>

<div>
<p class="text-xs text-gray-500 uppercase">Campaigns</p>
<p class="mt-2 font-semibold">
{{ $campaignCount }}
</p>
</div>

<div>
<p class="text-xs text-gray-500 uppercase">Chatbots</p>
<p class="mt-2 font-semibold">
{{ $botCount }}
</p>
</div>

<div>
<p class="text-xs text-gray-500 uppercase">Templates</p>
<p class="mt-2 font-semibold">
{{ $templateCount }}
</p>
</div>

</div>

</div>



{{-- META WARNING --}}
@if(!$meta)

<div class="bg-red-50 border border-red-200 rounded-2xl p-6 flex items-center justify-between">

<div>
<h3 class="font-semibold text-red-600">
Meta Business Not Connected
</h3>

<p class="text-sm text-red-500">
Campaigns and WhatsApp automation will not work until connected.
</p>
</div>

<a
href="{{ route('client.meta.connect') }}"
class="px-5 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
Connect Now
</a>

</div>

@endif



{{-- PLAN WARNING --}}
@if($isFree)

<div class="bg-yellow-50 border border-yellow-200 rounded-xl p-5 flex justify-between">

<p class="text-sm text-yellow-700">
You are on Free Plan. Upgrade to unlock unlimited automation.
</p>

<a
href="{{ route('client.billing.index') }}"
class="text-yellow-800 font-semibold underline">
Upgrade Plan
</a>

</div>

@endif



{{-- KPI CARDS --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

<div class="bg-white rounded-xl border p-6">
<p class="text-sm text-gray-500">Active Campaigns</p>
<p class="text-3xl font-bold mt-2">{{ $activeCampaigns }}</p>
</div>

<div class="bg-white rounded-xl border p-6">
<p class="text-sm text-gray-500">Total Budget</p>
<p class="text-3xl font-bold mt-2">
${{ number_format($totalBudget,2) }}
</p>
</div>

<div class="bg-white rounded-xl border p-6">
<p class="text-sm text-gray-500">Total Spend</p>
<p class="text-3xl font-bold mt-2">
${{ number_format($totalSpend,2) }}
</p>
</div>

<div class="bg-white rounded-xl border p-6">
<p class="text-sm text-gray-500">Leads</p>
<p class="text-3xl font-bold mt-2">{{ $totalLeads }}</p>
</div>

</div>



{{-- PERFORMANCE --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-6">

<div class="bg-white border rounded-xl p-5 text-center">
<p class="text-sm text-gray-500">CTR</p>
<p class="text-xl font-bold mt-2">{{ $ctr }}%</p>
</div>

<div class="bg-white border rounded-xl p-5 text-center">
<p class="text-sm text-gray-500">Conversion</p>
<p class="text-xl font-bold mt-2">{{ $conversionRate }}%</p>
</div>

<div class="bg-white border rounded-xl p-5 text-center">
<p class="text-sm text-gray-500">CPC</p>
<p class="text-xl font-bold mt-2">
${{ number_format($cpc,2) }}
</p>
</div>

<div class="bg-white border rounded-xl p-5 text-center">
<p class="text-sm text-gray-500">CPA</p>
<p class="text-xl font-bold mt-2">
${{ number_format($cpa,2) }}
</p>
</div>

</div>



{{-- PLATFORM MODULES --}}
<div>

<h3 class="text-lg font-semibold text-gray-800 mb-4">
Platform Modules
</h3>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">


<a href="{{ route('client.campaigns.index') }}"
class="bg-white border rounded-2xl p-6 hover:shadow-lg transition">

<h4 class="font-semibold">Campaign Management</h4>

<p class="text-sm text-gray-500 mt-2">
Create and manage campaigns
</p>

</a>



<a href="{{ route('client.chatbots.index') }}"
class="bg-white border rounded-2xl p-6 hover:shadow-lg transition">

<h4 class="font-semibold">Chatbot Builder</h4>

<p class="text-sm text-gray-500 mt-2">
Build WhatsApp flows
</p>

</a>



<a href="{{ route('client.templates.index') }}"
class="bg-white border rounded-2xl p-6 hover:shadow-lg transition">

<h4 class="font-semibold">Templates</h4>

<p class="text-sm text-gray-500 mt-2">
Manage approved templates
</p>

</a>



<a href="{{ route('client.inbox.index') }}"
class="bg-white border rounded-2xl p-6 hover:shadow-lg transition">

<h4 class="font-semibold">WhatsApp Inbox</h4>

<p class="text-sm text-gray-500 mt-2">
Real-time conversations
</p>

</a>


</div>

</div>


</div>
</div>

</x-app-layout>