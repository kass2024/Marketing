<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Parrot Canada Visa Consultant — Meta Ads management, AI WhatsApp automation, and analytics in one dashboard.">

    <title>{{ config('app.name') }}</title>

    <link rel="icon" href="{{ asset('img/logo.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet"/>

    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F0F2F5] font-sans text-slate-800 antialiased">

{{-- Top bar — Meta-style minimal chrome --}}
<header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-md">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
        <a href="{{ url('/') }}" class="group flex min-w-0 items-center gap-3 sm:gap-4">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-xander-navy to-xander-accent p-1.5 shadow-md shadow-xander-navy/15 ring-1 ring-white/20 sm:h-12 sm:w-12">
                <img src="{{ asset('img/logo.png') }}" alt="" class="h-full w-full object-contain" width="48" height="48">
            </span>
            <span class="min-w-0 text-left">
                <span class="block truncate text-base font-bold tracking-tight text-xander-navy sm:text-lg">{{ config('app.name') }}</span>
                <span class="hidden text-xs font-medium text-slate-500 sm:block">Ads &amp; automation suite</span>
            </span>
        </a>
        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
            <a
                href="{{ route('login') }}"
                class="inline-flex items-center justify-center rounded-lg bg-xander-navy px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-xander-secondary sm:px-5"
            >
                Log in
            </a>
        </div>
    </div>
</header>

<main>
    {{-- Hero — split layout like Meta Business Suite landing --}}
    <section class="relative overflow-hidden border-b border-slate-200/60 bg-white">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_80%_60%_at_70%_-10%,rgba(66,116,49,0.12),transparent)]"></div>
        <div class="relative mx-auto grid max-w-6xl gap-12 px-4 py-14 sm:px-6 lg:grid-cols-2 lg:items-center lg:gap-16 lg:px-8 lg:py-20">
            <div>
                <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-xander-navy/15 bg-xander-navy/[0.06] px-3 py-1 text-xs font-semibold uppercase tracking-wide text-xander-navy">
                    Built for Meta advertisers
                </p>
                <h1 class="text-3xl font-bold leading-tight tracking-tight text-slate-900 sm:text-4xl lg:text-[2.65rem] lg:leading-[1.12]">
                    Run campaigns and conversations
                    <span class="text-xander-navy"> from one control center</span>
                </h1>
                <p class="mt-5 max-w-xl text-base leading-relaxed text-slate-600 sm:text-lg">
                    Automate WhatsApp replies for leads from your ads, manage campaigns and ad sets, and see performance in a single workspace—aligned with how you already work in Meta.
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a
                        href="{{ route('login') }}"
                        class="inline-flex items-center justify-center rounded-xl bg-xander-navy px-6 py-3.5 text-base font-semibold text-white shadow-lg shadow-xander-navy/25 transition hover:bg-xander-secondary"
                    >
                        Open dashboard
                    </a>
                    <a
                        href="{{ route('login') }}"
                        class="inline-flex items-center justify-center rounded-xl border-2 border-slate-200 bg-white px-6 py-3.5 text-base font-semibold text-xander-navy transition hover:border-xander-navy/30 hover:bg-slate-50"
                    >
                        Sign in
                    </a>
                </div>
                <ul class="mt-10 flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-500">
                    <li class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-xander-gold"></span>
                        Campaigns &amp; ad sets
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-xander-gold"></span>
                        WhatsApp automation
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-xander-gold"></span>
                        Live metrics
                    </li>
                </ul>
            </div>

            {{-- Abstract “product” panel — Meta-style UI chrome --}}
            <div class="relative lg:justify-self-end">
                <div class="rounded-2xl border border-slate-200/90 bg-[#F0F2F5] p-2 shadow-xl shadow-slate-900/10 ring-1 ring-slate-900/5">
                    <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                        <div class="flex items-center gap-2 border-b border-slate-100 bg-slate-50/80 px-4 py-3">
                            <div class="flex gap-1.5">
                                <span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>
                            </div>
                            <span class="ml-2 text-xs font-medium text-slate-500">Overview</span>
                        </div>
                        <div class="grid gap-3 p-4 sm:p-5">
                            <div class="grid grid-cols-3 gap-2 sm:gap-3">
                                <div class="rounded-lg border border-slate-100 bg-slate-50/80 p-3 sm:p-4">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Active</p>
                                    <p class="mt-1 text-lg font-bold tabular-nums text-xander-navy sm:text-xl">—</p>
                                </div>
                                <div class="rounded-lg border border-slate-100 bg-slate-50/80 p-3 sm:p-4">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Spend</p>
                                    <p class="mt-1 text-lg font-bold tabular-nums text-slate-700 sm:text-xl">—</p>
                                </div>
                                <div class="rounded-lg border border-slate-100 bg-slate-50/80 p-3 sm:p-4">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Leads</p>
                                    <p class="mt-1 text-lg font-bold tabular-nums text-slate-700 sm:text-xl">—</p>
                                </div>
                            </div>
                            <div class="rounded-lg border border-slate-100 bg-gradient-to-br from-xander-navy/5 to-transparent p-4">
                                <div class="mb-3 flex items-center justify-between">
                                    <span class="text-xs font-semibold text-xander-navy">Campaign health</span>
                                    <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700">Live</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-200">
                                    <div class="h-full w-[72%] rounded-full bg-gradient-to-r from-xander-navy to-xander-secondary"></div>
                                </div>
                            </div>
                            <div class="flex gap-2 rounded-lg border border-dashed border-xander-navy/20 bg-xander-navy/[0.03] p-3 text-xs text-xander-secondary">
                                <svg class="h-5 w-5 shrink-0 text-xander-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                                <span class="leading-snug">Chatbot monitor and inbox sync with your ad funnel.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Feature grid — bento / Meta Ads Manager tone --}}
    <section class="border-b border-slate-200/60 py-16 sm:py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="mb-10 max-w-2xl">
                <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Everything in one place</h2>
                <p class="mt-3 text-slate-600">The same flows you expect from Meta tools—navigation, tables, and status—plus automation for the messages your ads generate.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-5">
                <article class="group rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm ring-1 ring-slate-900/[0.04] transition hover:border-xander-navy/20 hover:shadow-md lg:p-7">
                    <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-xander-navy/10 text-xander-navy">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-xander-navy">Meta Ads management</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        Campaigns, ad sets, creatives, and ads—with clear delivery status and actions that stay visible on every screen size.
                    </p>
                </article>

                <article class="group rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm ring-1 ring-slate-900/[0.04] transition hover:border-xander-navy/20 hover:shadow-md lg:p-7">
                    <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-xander-gold/20 text-xander-navy">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-xander-navy">AI chatbot automation</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        Reply to WhatsApp conversations triggered by your campaigns, with routing and monitoring built for operations teams.
                    </p>
                </article>

                <article class="group rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm ring-1 ring-slate-900/[0.04] transition hover:border-xander-navy/20 hover:shadow-md sm:col-span-2 lg:col-span-1 lg:p-7">
                    <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-xander-secondary/15 text-xander-navy">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-xander-navy">Real-time analytics</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        Spend, clicks, delivery, and conversation signals in context—so you can adjust budgets and creative without switching tools.
                    </p>
                </article>
            </div>
        </div>
    </section>

    {{-- CTA band — brand gradient + gold button (PDF palette) --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-xander-navy via-xander-secondary to-xander-accent py-16 sm:py-20">
        <div class="pointer-events-none absolute inset-0 opacity-30 bg-[radial-gradient(circle_at_30%_20%,rgba(226,29,30,0.2),transparent_50%)]"></div>
        <div class="relative mx-auto max-w-3xl px-4 text-center sm:px-6">
            <h2 class="text-2xl font-bold text-white sm:text-3xl">Ready to work the way your team already does?</h2>
            <p class="mx-auto mt-4 max-w-xl text-base text-white/85">
                Sign in to connect your Meta assets, manage billing context in-platform, and keep WhatsApp leads moving.
            </p>
            <a
                href="{{ route('login') }}"
                class="mt-8 inline-flex items-center justify-center rounded-xl bg-xander-gold px-10 py-4 text-base font-bold text-white shadow-lg transition hover:brightness-105"
            >
                Log in to dashboard
            </a>
        </div>
    </section>
</main>

<footer class="border-t border-slate-800 bg-[#1c1e21] py-10 text-slate-400">
    <div class="mx-auto flex max-w-6xl flex-col items-center gap-6 px-4 sm:px-6 lg:flex-row lg:justify-between lg:px-8">
        <div class="flex items-center gap-3">
            <img src="{{ asset('img/logo.png') }}" alt="" class="h-9 w-9 object-contain opacity-90" width="36" height="36">
            <div class="text-left text-sm">
                <p class="font-semibold text-white">{{ config('app.name') }}</p>
                <p>&copy; {{ date('Y') }} All rights reserved.</p>
            </div>
        </div>
        <nav class="flex flex-wrap justify-center gap-x-8 gap-y-2 text-sm">
            <a href="/privacy-policy" class="transition hover:text-white">Privacy</a>
            <a href="/terms-of-service" class="transition hover:text-white">Terms</a>
            <a href="/data-deletion" class="transition hover:text-white">Data deletion</a>
        </nav>
    </div>
</footer>

</body>
</html>
