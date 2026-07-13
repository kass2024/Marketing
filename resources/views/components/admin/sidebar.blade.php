@props([
    'routeName' => '',
    'isSuperAdmin' => false,
    'isClient' => false,
    'canManageAds' => false,
    'unreadCount' => 0,
    'brandName' => 'Dashboard',
    'brandSubtitle' => 'Ads workspace',
])

@php
$r = $routeName ?: Route::currentRouteName();
@endphp

<div
    x-data="{
        openAds: {{ str_contains($r, 'admin.accounts')
            || str_contains($r, 'admin.campaigns')
            || str_contains($r, 'admin.adsets')
            || str_contains($r, 'admin.ads')
            || str_contains($r, 'admin.creatives')
            || str_contains($r, 'admin.marketing') ? 'true' : 'false' }},
        openBm: {{ str_contains($r, 'admin.meta') ? 'true' : 'false' }},
        openAutomation: {{ str_contains($r, 'admin.inbox')
            || str_contains($r, 'admin.faq')
            || str_contains($r, 'admin.bulk') ? 'true' : 'false' }},
        openSettings: {{ str_contains($r, 'admin.settings') || str_contains($r, 'admin.users') ? 'true' : 'false' }},
        prefetch(url) {
            if (!url || url === '#' || url.startsWith('javascript:')) return;
            this._prefetched = this._prefetched || new Set();
            if (this._prefetched.has(url)) return;
            this._prefetched.add(url);
            const link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = url;
            link.as = 'document';
            document.head.appendChild(link);
        }
    }"
    class="flex h-full w-full min-h-0 flex-col border-r border-white/10 bg-gradient-to-b from-xander-navy via-xander-navy to-xander-accent text-white shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06)]"
>

    {{-- Brand + mobile close --}}
    <div class="flex items-center gap-3 border-b border-white/10 px-3 py-3 sm:px-4 sm:py-4">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/10 p-1.5 ring-1 ring-white/15 backdrop-blur-sm sm:h-[3.25rem] sm:w-[3.25rem]">
            @if($isSuperAdmin)
                <img
                    src="{{ asset(config('branding.logo_path', 'img/logo.png')) }}"
                    alt="{{ config('app.name') }}"
                    class="h-full w-full object-contain"
                    width="48"
                    height="48"
                />
            @else
                <img
                    src="{{ asset(config('branding.default_logo_path', 'img/default-logo.svg')) }}"
                    alt="{{ $brandName }}"
                    class="h-full w-full object-contain"
                    width="48"
                    height="48"
                />
            @endif
        </div>
        <div class="min-w-0 flex-1 leading-tight">
            @if($isSuperAdmin)
                <p class="truncate text-sm font-bold tracking-tight text-white">{{ config('app.name') }}</p>
                <p class="truncate text-[11px] font-medium text-white/55">Ads &amp; chatbot</p>
            @else
                <p class="truncate text-sm font-bold tracking-tight text-white">{{ $brandName }}</p>
                <p class="truncate text-[11px] font-medium text-white/55">{{ $brandSubtitle }}</p>
            @endif
        </div>
        <button
            type="button"
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-white/80 ring-1 ring-white/15 transition hover:bg-white/10 hover:text-white lg:hidden"
            @click="$dispatch('close-mobile-nav')"
            aria-label="Close menu"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <nav
        class="flex-1 space-y-0.5 overflow-y-auto overflow-x-hidden px-2.5 py-3 text-sm [scrollbar-width:thin] [scrollbar-color:rgba(255,255,255,0.2)_transparent]"
        @click.capture="if ($event.target.closest('a[href]')) { $dispatch('close-mobile-nav') }"
        @mouseover="const a = $event.target.closest('a[href]'); if (a) prefetch(a.href)"
        @focusin="const a = $event.target.closest('a[href]'); if (a) prefetch(a.href)"
    >

        @if($isSuperAdmin)

            <a href="{{ route('admin.dashboard') }}"
               class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition
               {{ $r === 'admin.dashboard' ? 'bg-white/15 text-white shadow-inner ring-1 ring-xander-gold/35' : 'text-white/88 hover:bg-white/10' }}">
                <span class="text-lg opacity-90" aria-hidden="true">📊</span>
                Overview
            </a>

            <a href="{{ route('admin.tenants.index') }}"
               class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition
               {{ str_contains($r, 'admin.tenants') ? 'bg-white/15 text-white shadow-inner ring-1 ring-xander-gold/35' : 'text-white/88 hover:bg-white/10' }}">
                <span class="text-lg opacity-90" aria-hidden="true">🛰️</span>
                Tenant monitor
            </a>

            <a href="{{ route('admin.clients.index') }}"
               class="flex items-center gap-3 rounded-xl px-3 py-2.5 font-medium transition
               {{ str_contains($r, 'admin.clients') ? 'bg-white/15 text-white shadow-inner ring-1 ring-xander-gold/35' : 'text-white/88 hover:bg-white/10' }}">
                <span class="text-lg opacity-90" aria-hidden="true">👥</span>
                Businesses
            </a>

            <div class="pt-1.5">
                <button type="button" @click="openBm = !openBm"
                    class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 font-semibold text-white/90 transition hover:bg-white/10">
                    <span class="flex items-center gap-3">
                        <span class="text-lg opacity-90" aria-hidden="true">🏢</span>
                        Business Manager
                    </span>
                    <svg class="h-4 w-4 transition" :class="openBm ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <div x-show="openBm" x-transition class="mt-1 space-y-0.5 border-l border-white/20 pl-3 ml-3">
                    <a href="{{ route('admin.meta.index') }}" class="block rounded-lg py-2 pl-2 pr-2 text-sm text-white/78 transition hover:bg-white/8 hover:text-white {{ $r === 'admin.meta.index' ? 'bg-white/10 text-xander-gold' : '' }}">Connection</a>
                    <a href="{{ route('admin.meta.whatsapp.index') }}" class="block rounded-lg py-2 pl-2 pr-2 text-sm text-white/78 transition hover:bg-white/8 hover:text-white {{ str_contains($r, 'admin.meta.whatsapp') ? 'bg-white/10 text-xander-gold' : '' }}">WhatsApp accounts</a>
                </div>
            </div>

        @endif

        @if($canManageAds)
            <div class="pt-1.5">
                <button type="button" @click="openAds = !openAds"
                    class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 font-semibold text-white/90 transition hover:bg-white/10">
                    <span class="flex items-center gap-3">
                        <span aria-hidden="true">🎯</span>
                        {{ $isClient ? 'My ads' : 'Ads' }}
                    </span>
                    <svg class="h-4 w-4 transition" :class="openAds ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <div x-show="openAds" x-transition class="mt-1 space-y-0.5 border-l border-white/20 pl-3 ml-3">
                    @if($isSuperAdmin)
                        <a href="{{ route('admin.accounts.index') }}" class="block rounded-lg py-2 pl-2 pr-2 text-sm text-white/78 transition hover:bg-white/8 hover:text-white {{ str_contains($r, 'admin.accounts') ? 'bg-white/10 text-xander-gold' : '' }}">Ad accounts</a>
                    @endif
                    <a href="{{ route('admin.marketing.create') }}" class="block rounded-lg py-2 pl-2 pr-2 text-sm font-semibold text-white/90 transition hover:bg-white/8 hover:text-white {{ str_contains($r, 'admin.marketing.create') || str_contains($r, 'admin.campaigns') || str_contains($r, 'admin.adsets') || str_contains($r, 'admin.creatives') || (str_contains($r, 'admin.ads') && !str_contains($r, 'admin.ads-manager')) ? 'bg-white/10 text-xander-gold' : '' }}">Ad Studio</a>
                </div>
            </div>
        @endif

        @if($isSuperAdmin)
            <div class="pt-1.5">
            <button type="button" @click="openAutomation = !openAutomation"
                class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 font-semibold text-white/90 transition hover:bg-white/10">
                <span class="flex items-center gap-3">
                    <span aria-hidden="true">🤖</span>
                    Chatbot monitor
                </span>
                <svg class="h-4 w-4 transition" :class="openAutomation ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
            <div x-show="openAutomation" x-transition class="mt-1 space-y-0.5 border-l border-white/20 pl-3 ml-3">
                <a href="{{ route('admin.inbox.index') }}" class="flex items-center justify-between rounded-lg py-2 pl-2 pr-2 text-sm text-white/78 transition hover:bg-white/8 hover:text-white {{ str_contains($r, 'admin.inbox') ? 'bg-white/10 text-xander-gold' : '' }}">
                    <span>Conversations</span>
                    @if($unreadCount > 0)
                        <span class="rounded-full bg-red-500 px-2 py-0.5 text-[10px] font-bold text-white shadow-sm">{{ $unreadCount }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.faq.index') }}" class="block rounded-lg py-2 pl-2 pr-2 text-sm text-white/78 transition hover:bg-white/8 hover:text-white {{ str_contains($r, 'admin.faq') ? 'bg-white/10 text-xander-gold' : '' }}">FAQ knowledge</a>
                <a href="{{ url('/admin/bulk') }}" class="block rounded-lg py-2 pl-2 pr-2 text-sm text-white/78 transition hover:bg-white/8 hover:text-white">Bulk send</a>
            </div>
            </div>
        @endif

        @if($isSuperAdmin)
            <div class="pt-1.5">
                <button type="button" @click="openSettings = !openSettings"
                    class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 font-semibold text-white/90 transition hover:bg-white/10">
                    <span class="flex items-center gap-3">
                        <span aria-hidden="true">⚙️</span>
                        Settings
                    </span>
                    <svg class="h-4 w-4 transition" :class="openSettings ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <div x-show="openSettings" x-transition class="mt-1 space-y-0.5 border-l border-white/20 pl-3 ml-3">
                    <a href="{{ route('admin.settings.index') }}" class="block rounded-lg py-2 pl-2 pr-2 text-sm text-white/78 transition hover:bg-white/8 hover:text-white {{ str_contains($r, 'admin.settings') && !str_contains($r, 'admin.settings.team') ? 'bg-white/10 text-xander-gold' : '' }}">Platform</a>
                    <a href="{{ route('admin.users.index') }}" class="block rounded-lg py-2 pl-2 pr-2 text-sm text-white/78 transition hover:bg-white/8 hover:text-white {{ str_contains($r, 'admin.users') ? 'bg-white/10 text-xander-gold' : '' }}">Users</a>
                </div>
            </div>
        @endif

    </nav>

    <div class="border-t border-white/10 p-3 text-[11px] text-white/40">
        © {{ now()->year }} {{ $isSuperAdmin ? config('app.name') : $brandName }}
    </div>
</div>
