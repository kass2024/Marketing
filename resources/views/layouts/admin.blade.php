<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        @hasSection('title')
            @yield('title') — {{ config('app.name') }}
        @else
            {{ config('app.name') }}
        @endif
    </title>

    <link rel="icon" href="{{ asset(config('branding.logo_path', 'img/logo.png')) }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet"/>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-screen bg-slate-100 font-sans antialiased text-slate-800">

@php
    $user = auth()->user();
    $isSuperAdmin = $user && $user->isSuperAdmin();
    $unreadCount = \App\Models\Message::where('direction', 'incoming')->where('is_read', 0)->count();
@endphp

<div
    class="flex min-h-screen"
    x-data="{ navOpen: false }"
    @close-mobile-nav="navOpen = false"
    @keydown.escape.window="navOpen = false"
>
    {{-- Mobile overlay --}}
    <div
        x-show="navOpen"
        x-transition.opacity.duration.200ms
        class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-[2px] lg:hidden"
        x-cloak
        @click="navOpen = false"
        aria-hidden="true"
    ></div>

    {{-- Sidebar: drawer on small screens, static on lg+ --}}
    <aside
        class="fixed inset-y-0 left-0 z-50 h-full w-[min(18rem,calc(100vw-2.5rem))] max-w-[85vw] transform transition-transform duration-200 ease-out will-change-transform lg:static lg:z-auto lg:h-auto lg:max-w-none lg:translate-x-0 lg:will-change-auto"
        :class="navOpen ? 'translate-x-0 shadow-2xl shadow-black/20' : '-translate-x-full'"
        role="navigation"
        aria-label="Main navigation"
    >
        <x-admin.sidebar
            :route-name="Route::currentRouteName()"
            :is-super-admin="$isSuperAdmin"
            :unread-count="$unreadCount"
        />
    </aside>

    <div class="flex min-h-0 min-w-0 flex-1 flex-col lg:min-h-screen">
        <header class="flex h-14 shrink-0 items-center justify-between gap-3 border-b border-slate-200/80 bg-white px-3 shadow-sm sm:px-4 lg:px-8">
            <div class="flex min-w-0 items-center gap-2 sm:gap-3">
                <button
                    type="button"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200/90 bg-white text-xander-navy shadow-sm transition hover:border-xander-navy/30 hover:bg-slate-50 lg:hidden"
                    @click="navOpen = true"
                    aria-label="Open menu"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <p class="min-w-0 truncate text-xs font-medium text-slate-500 sm:text-sm">
                    {{ now()->format('l, d M Y • H:i') }}
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-3 sm:gap-6">
                <span class="hidden max-w-[160px] truncate text-sm font-semibold text-xander-navy sm:inline">{{ $user->name }}</span>
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-red-600 transition hover:text-red-800">
                        Log out
                    </button>
                </form>
            </div>
        </header>

        <main class="min-w-0 flex-1 overflow-x-hidden overflow-y-auto p-4 sm:p-5 lg:p-8">
            @yield('content')
        </main>
    </div>
</div>

@stack('scripts')
</body>
</html>
