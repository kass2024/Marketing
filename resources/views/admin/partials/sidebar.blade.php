@php
    $route = Route::currentRouteName();

    $unreadCount = \App\Models\Message::where('direction','incoming')
        ->where('is_read',0)
        ->count();
@endphp

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
        <a href="{{ route('admin.dashboard') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl transition font-medium
           {{ $route == 'admin.dashboard' ? 'bg-blue-600 text-white shadow' : 'hover:bg-gray-100 text-gray-700' }}">
            📊 Dashboard
        </a>

        {{-- Business Manager --}}
        <a href="{{ route('admin.meta.index') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl transition font-medium
           {{ str_contains($route,'admin.meta') ? 'bg-blue-600 text-white shadow' : 'hover:bg-gray-100 text-gray-700' }}">
            🏢 Business Manager
        </a>

        {{-- ================= ADS ================= --}}
        <div x-data="{ open: {{ str_contains($route,'admin.accounts') || str_contains($route,'admin.campaigns') || str_contains($route,'admin.ads') || str_contains($route,'admin.adsets') || str_contains($route,'admin.analytics') ? 'true':'false' }} }">

            <button @click="open = !open"
                class="w-full flex justify-between items-center px-4 py-3 rounded-xl font-semibold text-gray-800 hover:bg-gray-100 transition">
                🎯 Ads Management
                <svg :class="open ? 'rotate-90' : ''"
                     class="w-4 h-4 transition-transform"
                     fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <div x-show="open" x-transition class="pl-6 mt-2 space-y-2">

                <a href="{{ route('admin.accounts.index') }}"
                   class="block py-2 px-3 rounded-lg hover:bg-gray-100 {{ str_contains($route,'admin.accounts')?'text-blue-600 font-semibold':'' }}">
                    Ad Accounts
                </a>

                <a href="{{ route('admin.campaigns.index') }}"
                   class="block py-2 px-3 rounded-lg hover:bg-gray-100 {{ str_contains($route,'admin.campaigns')?'text-blue-600 font-semibold':'' }}">
                    Campaigns
                </a>

                <a href="{{ route('admin.adsets.index') }}"
                   class="block py-2 px-3 rounded-lg hover:bg-gray-100 {{ str_contains($route,'admin.adsets')?'text-blue-600 font-semibold':'' }}">
                    Ad Sets
                </a>

                <a href="{{ route('admin.ads.index') }}"
                   class="block py-2 px-3 rounded-lg hover:bg-gray-100 {{ str_contains($route,'admin.ads')?'text-blue-600 font-semibold':'' }}">
                    Ads & Creatives
                </a>

                <a href="{{ route('admin.analytics.index') }}"
                   class="block py-2 px-3 rounded-lg hover:bg-gray-100 {{ str_contains($route,'admin.analytics')?'text-blue-600 font-semibold':'' }}">
                    Insights & Reports
                </a>

            </div>
        </div>

        {{-- ================= AUTOMATION ================= --}}
        <div x-data="{ open: {{ str_contains($route,'admin.chatbots') || str_contains($route,'admin.templates') || str_contains($route,'admin.faq') || str_contains($route,'admin.inbox') ? 'true':'false' }} }">

            <button @click="open = !open"
                class="w-full flex justify-between items-center px-4 py-3 rounded-xl font-semibold text-gray-800 hover:bg-gray-100 transition">
                🤖 Automation & CRM
                <svg :class="open ? 'rotate-90' : ''"
                     class="w-4 h-4 transition-transform"
                     fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <div x-show="open" x-transition class="pl-6 mt-2 space-y-2">

                <a href="{{ route('admin.inbox.index') }}"
                   class="flex justify-between items-center py-2 px-3 rounded-lg hover:bg-gray-100 {{ str_contains($route,'admin.inbox')?'text-blue-600 font-semibold':'' }}">
                    <span>💬 Conversations</span>

                    @if($unreadCount > 0)
                        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                            {{ $unreadCount }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('admin.chatbots.index') }}"
                   class="block py-2 px-3 rounded-lg hover:bg-gray-100 {{ str_contains($route,'admin.chatbots')?'text-blue-600 font-semibold':'' }}">
                    Chatbots
                </a>

                <a href="{{ route('admin.faq.index') }}"
                   class="block py-2 px-3 rounded-lg hover:bg-gray-100 {{ str_contains($route,'admin.faq')?'text-blue-600 font-semibold':'' }}">
                    FAQ Knowledge Base
                </a>

                <a href="{{ route('admin.settings.index') }}"
                   class="block py-2 px-3 rounded-lg hover:bg-gray-100 {{ str_contains($route,'admin.settings')?'text-blue-600 font-semibold':'' }}">
                    Templates / Settings
                </a>

            </div>
        </div>

        {{-- SYSTEM --}}
        <a href="{{ route('admin.system.index') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 {{ str_contains($route,'admin.system')?'text-blue-600 font-semibold':'' }}">
            🛠 System Monitor
        </a>

        <a href="{{ route('admin.settings.index') }}"
           class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-100 {{ str_contains($route,'admin.settings')?'text-blue-600 font-semibold':'' }}">
            ⚙️ Settings
        </a>

    </nav>
</aside>