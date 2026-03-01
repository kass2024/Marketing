<x-app-layout>

@php
$route = \Illuminate\Support\Facades\Route::currentRouteName();

if (!function_exists('safeRoute')) {
    function safeRoute($name, $fallback = '#') {
        return \Illuminate\Support\Facades\Route::has($name)
            ? route($name)
            : $fallback;
    }
}
@endphp

<div 
x-data="{
    openAds: {{ str_contains($route,'admin.accounts') || str_contains($route,'admin.campaigns') || str_contains($route,'admin.ads') || str_contains($route,'admin.analytics') ? 'true':'false' }},
    openSocial: {{ str_contains($route,'admin.instagram') || str_contains($route,'admin.messenger') || str_contains($route,'admin.whatsapp') ? 'true':'false' }},
    openAutomation: {{ str_contains($route,'admin.chatbots') || str_contains($route,'admin.templates') || str_contains($route,'admin.leads') || str_contains($route,'admin.faq') ? 'true':'false' }}
}"
class="min-h-screen bg-gray-100 font-sans antialiased">

<div class="flex min-h-screen">

{{-- ================= SIDEBAR ================= --}}
<aside class="w-80 bg-white border-r border-gray-200 flex flex-col shadow-sm">

    {{-- LOGO --}}
    <div class="h-24 flex items-center px-8 border-b bg-gradient-to-r from-blue-600 to-indigo-600">
        <div>
            <h2 class="text-2xl font-bold text-white tracking-tight">
                MetaPanel
            </h2>
            <p class="text-sm text-blue-100 mt-1">
                Enterprise SaaS
            </p>
        </div>
    </div>

    {{-- MENU --}}
    <nav class="flex-1 overflow-y-auto px-6 py-6 space-y-3 text-base">

        {{-- Dashboard --}}
        <a href="{{ safeRoute('admin.dashboard') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl transition font-medium
           {{ $route == 'admin.dashboard' ? 'bg-blue-600 text-white shadow-lg' : 'hover:bg-gray-100 text-gray-700' }}">
            <span>📊</span> Dashboard
        </a>

        {{-- Business --}}
        <a href="{{ safeRoute('admin.meta.index') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl transition font-medium
           {{ str_contains($route,'admin.meta') ? 'bg-blue-600 text-white shadow-lg' : 'hover:bg-gray-100 text-gray-700' }}">
            <span>🏢</span> Business Manager
        </a>

        {{-- ================= ADS ================= --}}
        <div>
            <button @click="openAds = !openAds"
                    class="w-full flex justify-between items-center px-4 py-3 rounded-xl font-semibold text-gray-800 hover:bg-gray-100 transition">
                <span>🎯 Ads Management</span>
                <svg :class="openAds ? 'rotate-90' : ''"
                     class="w-4 h-4 transition-transform"
                     fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <div x-show="openAds"
                 x-transition
                 class="pl-6 mt-2 space-y-2">

                <a href="{{ safeRoute('admin.accounts.index') }}"
                   class="block py-2 rounded-lg px-3 hover:bg-gray-100 {{ str_contains($route,'admin.accounts')?'text-blue-600 font-semibold':'' }}">
                    Ad Accounts
                </a>

                <a href="{{ safeRoute('admin.campaigns.index') }}"
                   class="block py-2 rounded-lg px-3 hover:bg-gray-100 {{ str_contains($route,'admin.campaigns')?'text-blue-600 font-semibold':'' }}">
                    Campaigns
                </a>

                <a href="{{ safeRoute('admin.ads.index') }}"
                   class="block py-2 rounded-lg px-3 hover:bg-gray-100 {{ str_contains($route,'admin.ads')?'text-blue-600 font-semibold':'' }}">
                    Ads & Creatives
                </a>

                <a href="{{ safeRoute('admin.analytics.index') }}"
                   class="block py-2 rounded-lg px-3 hover:bg-gray-100 {{ str_contains($route,'admin.analytics')?'text-blue-600 font-semibold':'' }}">
                    Insights & Reports
                </a>

            </div>
        </div>

        {{-- ================= SOCIAL ================= --}}
        <div>
            <button @click="openSocial = !openSocial"
                    class="w-full flex justify-between items-center px-4 py-3 rounded-xl font-semibold text-gray-800 hover:bg-gray-100 transition">
                <span>🌍 Social Channels</span>
                <svg :class="openSocial ? 'rotate-90' : ''"
                     class="w-4 h-4 transition-transform"
                     fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <div x-show="openSocial" x-transition class="pl-6 mt-2 space-y-2">

                <a href="{{ safeRoute('admin.instagram.index') }}"
                   class="block py-2 rounded-lg px-3 hover:bg-gray-100 {{ str_contains($route,'admin.instagram')?'text-blue-600 font-semibold':'' }}">
                    Instagram
                </a>

                <a href="{{ safeRoute('admin.messenger.index') }}"
                   class="block py-2 rounded-lg px-3 hover:bg-gray-100 {{ str_contains($route,'admin.messenger')?'text-blue-600 font-semibold':'' }}">
                    Messenger
                </a>

                <a href="{{ safeRoute('admin.whatsapp.index') }}"
                   class="block py-2 rounded-lg px-3 hover:bg-gray-100 {{ str_contains($route,'admin.whatsapp')?'text-blue-600 font-semibold':'' }}">
                    WhatsApp
                </a>

            </div>
        </div>

        {{-- ================= AUTOMATION ================= --}}
        <div>
            <button @click="openAutomation = !openAutomation"
                    class="w-full flex justify-between items-center px-4 py-3 rounded-xl font-semibold text-gray-800 hover:bg-gray-100 transition">
                <span>🤖 Automation & CRM</span>
                <svg :class="openAutomation ? 'rotate-90' : ''"
                     class="w-4 h-4 transition-transform"
                     fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <div x-show="openAutomation" x-transition class="pl-6 mt-2 space-y-2">

                <a href="{{ safeRoute('admin.chatbots.index') }}"
                   class="block py-2 rounded-lg px-3 hover:bg-gray-100 {{ str_contains($route,'admin.chatbots')?'text-blue-600 font-semibold':'' }}">
                    Chatbots
                </a>

                <a href="{{ safeRoute('admin.faq.index') }}"
                   class="block py-2 rounded-lg px-3 hover:bg-gray-100 {{ str_contains($route,'admin.faq')?'text-blue-600 font-semibold':'' }}">
                    FAQ Knowledge Base
                </a>

                <a href="{{ safeRoute('admin.faq.create') }}"
                   class="block py-2 rounded-lg px-3 hover:bg-green-50 text-green-600 font-semibold">
                    + Add FAQ
                </a>

                <a href="{{ safeRoute('admin.templates.index') }}"
                   class="block py-2 rounded-lg px-3 hover:bg-gray-100 {{ str_contains($route,'admin.templates')?'text-blue-600 font-semibold':'' }}">
                    Templates
                </a>

                <a href="{{ safeRoute('admin.leads.index') }}"
                   class="block py-2 rounded-lg px-3 hover:bg-gray-100 {{ str_contains($route,'admin.leads')?'text-blue-600 font-semibold':'' }}">
                    Leads CRM
                </a>

            </div>
        </div>

        <a href="{{ safeRoute('admin.system.index') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl transition font-medium hover:bg-gray-100 {{ str_contains($route,'admin.system')?'text-blue-600 font-semibold':'' }}">
            🛠 System Monitor
        </a>

        <a href="{{ safeRoute('admin.settings.index') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl transition font-medium hover:bg-gray-100 {{ str_contains($route,'admin.settings')?'text-blue-600 font-semibold':'' }}">
            ⚙️ Settings
        </a>

    </nav>

</aside>