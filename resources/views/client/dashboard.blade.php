<x-app-layout>

@php
    $user = auth()->user();
    $client = $user->client;
    $meta = $client?->metaConnection ?? null;
    $plan = $client->subscription_plan ?? 'free';
    $isFree = $plan === 'free';

    $botCount = $client?->chatbots()->count() ?? 0;
    $templateCount = $client?->templates()->count() ?? 0;
    $conversationCount = $client?->conversations()->count() ?? 0;
    $campaignCount = $kpis['total_campaigns'] ?? 0;

    $hasCampaigns = $campaignCount > 0;
@endphp

{{-- HEADER --}}
<x-slot name="header">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">
                Control Center
            </h2>
            <p class="text-sm text-gray-500 mt-1">
                Manage campaigns, automation & conversations
            </p>
        </div>

        <div class="flex items-center gap-3">
            <span class="hidden md:block text-sm text-gray-400">
                {{ now()->format('l, d M Y') }}
            </span>

            @if(!$meta)
                <a href="{{ route('client.meta.connect') }}"
                   class="px-6 py-3 bg-blue-600 text-white font-semibold rounded-xl shadow hover:bg-blue-700 transition">
                    Connect Meta Business
                </a>
            @endif
        </div>
    </div>
</x-slot>

<div class="py-8 bg-gray-50 min-h-screen">
<div class="max-w-7xl mx-auto space-y-10 px-4">

    {{-- ===================== --}}
    {{-- ACCOUNT OVERVIEW --}}
    {{-- ===================== --}}
    <div class="bg-white rounded-2xl border shadow-sm p-6">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-6 text-center md:text-left">

            <div>
                <p class="text-xs uppercase text-gray-500">Meta Status</p>
                <p class="mt-2 text-lg font-semibold {{ $meta ? 'text-green-600' : 'text-red-500' }}">
                    {{ $meta ? 'Connected' : 'Not Connected' }}
                </p>
            </div>

            <div>
                <p class="text-xs uppercase text-gray-500">Subscription</p>
                <p class="mt-2 text-lg font-semibold">
                    {{ ucfirst($plan) }}
                </p>
            </div>

            <div>
                <p class="text-xs uppercase text-gray-500">Campaigns</p>
                <p class="mt-2 text-lg font-semibold">
                    {{ $campaignCount }}
                </p>
            </div>

            <div>
                <p class="text-xs uppercase text-gray-500">Chatbots</p>
                <p class="mt-2 text-lg font-semibold">
                    {{ $botCount }}
                </p>
            </div>

            <div>
                <p class="text-xs uppercase text-gray-500">Templates</p>
                <p class="mt-2 text-lg font-semibold">
                    {{ $templateCount }}
                </p>
            </div>

        </div>
    </div>

    {{-- ===================== --}}
    {{-- ALERTS --}}
    {{-- ===================== --}}
    @if(!$meta)
        <div class="bg-red-50 border border-red-200 rounded-2xl p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-red-600">
                    Meta Business Not Connected
                </h3>
                <p class="text-sm text-red-500 mt-1">
                    Campaigns and WhatsApp automation will not function until connected.
                </p>
            </div>

            <a href="{{ route('client.meta.connect') }}"
               class="px-5 py-3 bg-red-600 text-white rounded-xl font-semibold hover:bg-red-700 transition">
                Connect Now
            </a>
        </div>
    @endif

    @if($isFree)
        <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-5 flex flex-col md:flex-row md:justify-between md:items-center gap-3">
            <p class="text-sm text-yellow-700">
                You are on Free Plan. Upgrade to unlock unlimited automation & analytics.
            </p>
            <a href="{{ route('client.billing.index') }}"
               class="font-semibold text-yellow-800 underline">
                Upgrade Plan
            </a>
        </div>
    @endif

    {{-- ===================== --}}
    {{-- KPI CARDS --}}
    {{-- ===================== --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

        @foreach([
            ['Active Campaigns', $kpis['active_campaigns'] ?? 0],
            ['Total Budget', '$'.number_format($kpis['total_budget'] ?? 0,2)],
            ['Total Spend', '$'.number_format($kpis['total_spend'] ?? 0,2)],
            ['Leads Generated', $kpis['total_leads'] ?? 0]
        ] as $card)
            <div class="bg-white rounded-2xl border p-6 shadow-sm hover:shadow-md transition">
                <p class="text-sm text-gray-500">{{ $card[0] }}</p>
                <p class="text-3xl font-bold mt-3 text-gray-900">
                    {{ $card[1] }}
                </p>
            </div>
        @endforeach

    </div>

    {{-- ===================== --}}
    {{-- PERFORMANCE METRICS --}}
    {{-- ===================== --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">

        @foreach([
            ['CTR',$kpis['ctr'] ?? 0 .'%'],
            ['Conversion Rate',$kpis['conversion_rate'] ?? 0 .'%'],
            ['CPC','$'.number_format($kpis['cpc'] ?? 0,2)],
            ['CPA','$'.number_format($kpis['cpa'] ?? 0,2)]
        ] as $metric)
            <div class="bg-white rounded-xl border p-5 text-center">
                <p class="text-sm text-gray-500">{{ $metric[0] }}</p>
                <p class="text-xl font-bold mt-2 text-gray-900">
                    {{ $metric[1] }}
                </p>
            </div>
        @endforeach

    </div>

    {{-- ===================== --}}
    {{-- CHARTS --}}
    {{-- ===================== --}}
    @if($hasCampaigns)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <div class="bg-white rounded-2xl border p-6">
                <h3 class="font-semibold mb-4 text-gray-800">
                    Monthly Spend Trend
                </h3>
                <canvas id="spendChart"></canvas>
            </div>

            <div class="bg-white rounded-2xl border p-6">
                <h3 class="font-semibold mb-4 text-gray-800">
                    Leads Trend
                </h3>
                <canvas id="leadsChart"></canvas>
            </div>

        </div>
    @endif

    {{-- ===================== --}}
    {{-- MODULES --}}
    {{-- ===================== --}}
    <div>
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            Platform Modules
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

            <a href="{{ route('client.campaigns.index') }}"
               class="bg-white border rounded-2xl p-6 hover:shadow-lg transition">
                <h4 class="font-semibold text-gray-900">
                    Campaign Management
                </h4>
                <p class="text-sm text-gray-500 mt-2">
                    Create & optimize ad campaigns.
                </p>
            </a>

            <a href="{{ route('client.chatbots.index') }}"
               class="bg-white border rounded-2xl p-6 hover:shadow-lg transition">
                <h4 class="font-semibold text-gray-900">
                    Chatbot Builder
                </h4>
                <p class="text-sm text-gray-500 mt-2">
                    Build advanced WhatsApp flows.
                </p>
            </a>

            <a href="{{ route('client.templates.index') }}"
               class="bg-white border rounded-2xl p-6 hover:shadow-lg transition">
                <h4 class="font-semibold text-gray-900">
                    Message Templates
                </h4>
                <p class="text-sm text-gray-500 mt-2">
                    Submit & manage approved templates.
                </p>
            </a>

            <a href="{{ route('client.inbox.index') }}"
               class="bg-white border rounded-2xl p-6 hover:shadow-lg transition">
                <h4 class="font-semibold text-gray-900">
                    WhatsApp Inbox
                </h4>
                <p class="text-sm text-gray-500 mt-2">
                    Real-time conversations & automation.
                </p>
            </a>

        </div>
    </div>

</div>
</div>

</x-app-layout>