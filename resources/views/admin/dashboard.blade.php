<x-app-layout>

@php
$route = Route::currentRouteName();

$unreadCount = \App\Models\Message::where('direction','incoming')
    ->where('is_read',0)
    ->count();
@endphp

<div 
x-data="{
    openAds: {{ str_contains($route,'admin.accounts') 
    || str_contains($route,'admin.campaigns')
    || str_contains($route,'admin.adsets')
    || str_contains($route,'admin.ads')
    || str_contains($route,'admin.creatives')
    || str_contains($route,'admin.analytics')
    ? 'true':'false' }},
}"
class="min-h-screen bg-gray-100 font-sans">

<div class="flex min-h-screen">

{{-- ================= SIDEBAR ================= --}}
<aside class="w-80 bg-white border-r border-gray-200 flex flex-col shadow-sm">

    {{-- LOGO --}}
    <div class="h-24 flex items-center px-8 border-b bg-gradient-to-r from-blue-600 to-indigo-600">
        <div>
            <h2 class="text-2xl font-bold text-white tracking-tight">MetaPanel</h2>
            <p class="text-sm text-blue-100 mt-1">Enterprise SaaS</p>
        </div>
    </div>

    {{-- MENU --}}
    <nav class="flex-1 overflow-y-auto px-6 py-6 space-y-3 text-base">

        {{-- Dashboard --}}
        <a href="{{ Route::has('admin.dashboard') ? route('admin.dashboard') : '#' }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl transition font-medium
           {{ $route == 'admin.dashboard' ? 'bg-blue-600 text-white shadow' : 'hover:bg-gray-100 text-gray-700' }}">
            📊 Dashboard
        </a>

        {{-- Business --}}
        <a href="{{ Route::has('admin.meta.index') ? route('admin.meta.index') : '#' }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl transition font-medium
           {{ str_contains($route,'admin.meta') ? 'bg-blue-600 text-white shadow' : 'hover:bg-gray-100 text-gray-700' }}">
            🏢 Business Manager
        </a>

        {{-- ================= ADS ================= --}}
<div>
    <button @click="openAds = !openAds"
        class="w-full flex justify-between items-center px-4 py-3 rounded-xl font-semibold text-gray-800 hover:bg-gray-100 transition">
        🎯 Ads Management
        <svg :class="openAds ? 'rotate-90' : ''"
             class="w-4 h-4 transition-transform"
             fill="none" stroke="currentColor" stroke-width="2"
             viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
    </button>

    <div x-show="openAds" x-transition class="pl-6 mt-2 space-y-2">

        {{-- Ad Accounts --}}
        <a href="{{ route('admin.accounts.index') }}"
           class="block py-2 px-3 rounded-lg hover:bg-gray-100
           {{ str_contains($route,'admin.accounts') ? 'text-blue-600 font-semibold' : '' }}">
            Ad Accounts
        </a>

        {{-- Campaigns --}}
        <a href="{{ route('admin.campaigns.index') }}"
           class="block py-2 px-3 rounded-lg hover:bg-gray-100
           {{ str_contains($route,'admin.campaigns') ? 'text-blue-600 font-semibold' : '' }}">
            Campaigns
        </a>

        {{-- Ad Sets --}}
        <a href="{{ route('admin.adsets.index') }}"
           class="block py-2 px-3 rounded-lg hover:bg-gray-100
           {{ str_contains($route,'admin.adsets') ? 'text-blue-600 font-semibold' : '' }}">
            Ad Sets
        </a>

        {{-- Ads --}}
        <a href="{{ route('admin.ads.index') }}"
           class="block py-2 px-3 rounded-lg hover:bg-gray-100
           {{ str_contains($route,'admin.ads') ? 'text-blue-600 font-semibold' : '' }}">
            Ads
        </a>

        {{-- Creatives --}}
        <a href="{{ route('admin.creatives.index') }}"
           class="block py-2 px-3 rounded-lg hover:bg-gray-100
           {{ str_contains($route,'admin.creatives') ? 'text-blue-600 font-semibold' : '' }}">
            Creatives
        </a>

        {{-- Analytics --}}
        <a href="{{ route('admin.analytics.index') }}"
           class="block py-2 px-3 rounded-lg hover:bg-gray-100
           {{ str_contains($route,'admin.analytics') ? 'text-blue-600 font-semibold' : '' }}">
            Insights & Reports
        </a>
<a href="{{ route('admin.ads.manager') }}"
class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100">
📢 Ads Manager
</a>
    </div>
</div>
        {{-- ================= SOCIAL ================= --}}
        <div>
            <button @click="openSocial = !openSocial"
                class="w-full flex justify-between items-center px-4 py-3 rounded-xl font-semibold text-gray-800 hover:bg-gray-100 transition">
                🌍 Social Channels
                <svg :class="openSocial ? 'rotate-90' : ''"
                     class="w-4 h-4 transition-transform"
                     fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <div x-show="openSocial" x-transition class="pl-6 mt-2 space-y-2">

                <a href="{{ Route::has('admin.instagram.index') ? route('admin.instagram.index') : '#' }}"
                   class="block py-2 px-3 rounded-lg hover:bg-gray-100 {{ str_contains($route,'admin.instagram')?'text-blue-600 font-semibold':'' }}">
                    Instagram
                </a>

                <a href="{{ Route::has('admin.messenger.index') ? route('admin.messenger.index') : '#' }}"
                   class="block py-2 px-3 rounded-lg hover:bg-gray-100 {{ str_contains($route,'admin.messenger')?'text-blue-600 font-semibold':'' }}">
                    Messenger
                </a>

                <a href="{{ Route::has('admin.whatsapp.index') ? route('admin.whatsapp.index') : '#' }}"
                   class="block py-2 px-3 rounded-lg hover:bg-gray-100 {{ str_contains($route,'admin.whatsapp')?'text-blue-600 font-semibold':'' }}">
                    WhatsApp
                </a>

            </div>
        </div>

        {{-- ================= AUTOMATION ================= --}}
        <div>
            <button @click="openAutomation = !openAutomation"
                class="w-full flex justify-between items-center px-4 py-3 rounded-xl font-semibold text-gray-800 hover:bg-gray-100 transition">
                🤖 Automation & CRM
                <svg :class="openAutomation ? 'rotate-90' : ''"
                     class="w-4 h-4 transition-transform"
                     fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <div x-show="openAutomation" x-transition class="pl-6 mt-2 space-y-2">

                {{-- Conversations Added --}}
                <a href="{{ Route::has('admin.inbox.index') ? route('admin.inbox.index') : '#' }}"
                   class="flex justify-between items-center py-2 px-3 rounded-lg hover:bg-gray-100 {{ str_contains($route,'admin.inbox')?'text-blue-600 font-semibold':'' }}">
                    <span>💬 Conversations</span>

                    @if($unreadCount > 0)
                        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                            {{ $unreadCount }}
                        </span>
                    @endif
                </a>

                <a href="{{ Route::has('admin.chatbots.index') ? route('admin.chatbots.index') : '#' }}"
                   class="block py-2 px-3 rounded-lg hover:bg-gray-100 {{ str_contains($route,'admin.chatbots')?'text-blue-600 font-semibold':'' }}">
                    Chatbots
                </a>

                <a href="{{ Route::has('admin.faq.index') ? route('admin.faq.index') : '#' }}"
                   class="block py-2 px-3 rounded-lg hover:bg-gray-100 {{ str_contains($route,'admin.faq')?'text-blue-600 font-semibold':'' }}">
                    FAQ Knowledge Base
                </a>

                <a href="{{ Route::has('admin.templates.index') ? route('admin.templates.index') : '#' }}"
                   class="block py-2 px-3 rounded-lg hover:bg-gray-100 {{ str_contains($route,'admin.templates')?'text-blue-600 font-semibold':'' }}">
                    Templates
                </a>

                <a href="{{ Route::has('admin.leads.index') ? route('admin.leads.index') : '#' }}"
                   class="block py-2 px-3 rounded-lg hover:bg-gray-100 {{ str_contains($route,'admin.leads')?'text-blue-600 font-semibold':'' }}">
                    Leads CRM
                </a>

            </div>
        </div>

        <a href="{{ Route::has('admin.system.index') ? route('admin.system.index') : '#' }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 {{ str_contains($route,'admin.system')?'text-blue-600 font-semibold':'' }}">
            🛠 System Monitor
        </a>

        <a href="{{ Route::has('admin.settings.index') ? route('admin.settings.index') : '#' }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 {{ str_contains($route,'admin.settings')?'text-blue-600 font-semibold':'' }}">
            ⚙️ Settings
        </a>

    </nav>
</aside>

{{-- ================= RIGHT SIDE ================= --}}
<div class="flex-1 flex flex-col">

<header class="bg-white border-b px-12 py-8 shadow-sm">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Meta Enterprise Dashboard</h1>
            <p class="text-sm text-gray-500 mt-2">{{ now()->format('l, d M Y H:i') }}</p>
        </div>

        <div class="flex items-center gap-8">
            <span class="text-gray-700">{{ auth()->user()->name ?? 'Admin' }}</span>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-red-500 hover:underline">Logout</button>
            </form>
        </div>
    </div>
</header>

<main class="flex-1 py-12">
<div class="max-w-7xl mx-auto px-12 space-y-10">

{{-- Business Card Preserved --}}
<div class="bg-white p-10 rounded-2xl border shadow-sm flex justify-between items-center">
    <div>
        <h3 class="text-xl font-semibold text-gray-900">Business Manager</h3>

        @if(!empty($platformMeta))
            <p class="text-base text-gray-600 mt-3">
                Business ID: <strong>{{ $platformMeta->business_id }}</strong>
            </p>
            <p class="text-green-600 text-base font-medium mt-2">
                Verified & Connected
            </p>
        @else
            <p class="text-red-500 text-base mt-3">No business connected</p>
        @endif
    </div>

    <div>
        @if(empty($platformMeta))
            <a href="{{ route('admin.meta.connect') }}"
               class="bg-blue-600 text-white px-6 py-3 rounded-xl shadow hover:bg-blue-700 transition">
                Connect Business
            </a>
        @else
            <form method="POST" action="{{ route('admin.meta.disconnect') }}">
                @csrf
                <button type="submit"
                    class="bg-red-500 text-white px-6 py-3 rounded-xl shadow hover:bg-red-600 transition">
                    Disconnect
                </button>
            </form>
        @endif
    </div>
</div>

</div>
</main>

</div>
</div>
</div>

</x-app-layout>