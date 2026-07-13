@extends('layouts.admin')

@section('title', 'Instagram accounts — Business Manager')

@section('content')
@php
    $defaultIgId = $connection?->instagram_business_account_id;
    $openPanel = null;
    if (session('show_link_ig') || old('instagram_id')) {
        $openPanel = 'link';
    }
@endphp

<div class="w-full min-w-0" x-data="{
    panel: @json($openPanel),
    addOpen: false,
    openPanel(name) { this.panel = name; this.addOpen = false; },
    closePanel() { this.panel = null; }
}">

    <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-medium text-slate-500">Business Manager / Accounts</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Instagram accounts</h1>
            <p class="mt-0.5 text-sm text-slate-600">
                Opens instantly from cache — refreshes from Meta in the background. Use <strong>Sync now</strong> to force a full refresh.
                @isset($lastSyncedAt)
                    <span class="text-slate-400">Last sync: {{ $lastSyncedAt }}</span>
                @endisset
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="post" action="{{ route('admin.meta.instagram.sync') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Sync now
                </button>
            </form>
            <a href="{{ route('admin.meta.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Connection</a>
            <div class="relative" @click.outside="addOpen = false">
                <button type="button" @click="addOpen = !addOpen"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-[#0866FF] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#0759DB]">
                    <span aria-hidden="true">+</span>
                    Add
                    <svg class="h-3.5 w-3.5 opacity-90" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <div x-show="addOpen" x-cloak x-transition
                     class="absolute right-0 z-40 mt-2 w-[22rem] overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl ring-1 ring-black/5">
                    <a href="{{ $metaBusinessSuiteUrl }}" target="_blank" rel="noopener"
                        class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-slate-50">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-[#f58529] via-[#dd2a7b] to-[#8134af] text-sm font-bold text-white">IG</span>
                        <span>
                            <span class="block text-sm font-semibold text-slate-900">Add Instagram account in Meta</span>
                            <span class="mt-0.5 block text-xs leading-snug text-slate-500">Opens Meta Business Suite — log in to Instagram and add it to this portfolio, then Sync here.</span>
                        </span>
                    </a>
                    <button type="button" @click="openPanel('link')"
                        class="flex w-full items-start gap-3 border-t border-slate-100 px-4 py-3 text-left hover:bg-slate-50">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-2.61a4.5 4.5 0 00-1.242-7.244l-4.5-4.5a4.5 4.5 0 00-6.364 6.364L4.34 8.45"/></svg>
                        </span>
                        <span>
                            <span class="block text-sm font-semibold text-slate-900">Link Instagram account ID</span>
                            <span class="mt-0.5 block text-xs leading-snug text-slate-500">Paste the Instagram account ID from Meta → Accounts → Instagram accounts.</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div x-show="panel === 'link'" x-cloak class="mb-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-slate-900">Link Instagram account</h3>
                <p class="mt-1 text-xs text-slate-600">Use the numeric Instagram account ID from Meta Business Suite (e.g. 17841468010858538 for @moveabroadwithparrot).</p>
            </div>
            <button type="button" @click="closePanel()" class="text-xs font-semibold text-slate-500">Close</button>
        </div>
        <form method="post" action="{{ route('admin.meta.instagram.link') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            <div class="min-w-0 flex-1">
                <label class="mb-1 block text-xs font-semibold text-slate-600">Instagram account ID</label>
                <input type="text" name="instagram_id" value="{{ old('instagram_id') }}" required
                       placeholder="17841468010858538"
                       class="w-full rounded-lg border-slate-300 text-sm focus:border-xander-navy focus:ring-xander-navy">
                @error('instagram_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="rounded-lg bg-[#0866FF] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0759DB]">Link account</button>
        </form>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error') || $error)
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') ?: $error }}</div>
    @endif

    <div class="mb-4">
        <form method="get" class="flex gap-2">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search username or ID"
                   class="w-full max-w-sm rounded-lg border-slate-300 text-sm focus:border-xander-navy focus:ring-xander-navy">
            <button class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700">Search</button>
        </form>
    </div>

    <div class="grid gap-4 lg:grid-cols-12">
        <div class="lg:col-span-4 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-100 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                {{ count($accounts) }} account(s)
            </div>
            <ul class="max-h-[32rem] overflow-y-auto divide-y divide-slate-100">
                @forelse($accounts as $account)
                    @php
                        $isActive = (string) $account['id'] === (string) $selectedId;
                        $isDefault = (string) $account['id'] === (string) $defaultIgId;
                        $label = $account['username'] ? '@'.$account['username'] : ($account['name'] ?? $account['id']);
                    @endphp
                    <li>
                        <a href="{{ route('admin.meta.instagram.index', ['ig' => $account['id'], 'q' => $search ?: null]) }}"
                           class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50 {{ $isActive ? 'bg-sky-50' : '' }}">
                            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#f58529] via-[#dd2a7b] to-[#8134af] text-xs font-bold text-white">
                                {{ strtoupper(substr(ltrim((string) ($account['username'] ?? 'IG'), '@'), 0, 2) ?: 'IG') }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-slate-900">{{ $label }}</span>
                                <span class="mt-0.5 block truncate font-mono text-[11px] text-slate-500">{{ $account['id'] }}</span>
                                <span class="mt-1 inline-flex flex-wrap gap-1">
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600">{{ $account['source'] ?? 'meta' }}</span>
                                    @if($isDefault)
                                        <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700">Default</span>
                                    @endif
                                </span>
                            </span>
                        </a>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-sm text-slate-500">
                        No Instagram accounts found yet.
                        <span class="mt-2 block text-xs">Use <strong>Sync now</strong>, or <strong>+ Add → Link Instagram account ID</strong> with the ID from Meta Business Suite.</span>
                    </li>
                @endforelse
            </ul>
        </div>

        <div class="lg:col-span-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            @if($selected)
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Selected account</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-900">
                            {{ $selected['username'] ? '@'.$selected['username'] : ($selected['name'] ?? 'Instagram account') }}
                        </h2>
                        <p class="mt-1 font-mono text-sm text-slate-600">Instagram Account ID: {{ $selected['id'] }}</p>
                        <p class="mt-2 text-xs text-slate-500">Source: {{ $selected['source'] ?? 'meta' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if((string) $selected['id'] !== (string) $defaultIgId)
                            <form method="post" action="{{ route('admin.meta.instagram.default') }}">
                                @csrf
                                <input type="hidden" name="instagram_id" value="{{ $selected['id'] }}">
                                <button type="submit" class="rounded-lg bg-xander-navy px-4 py-2 text-sm font-semibold text-white hover:opacity-90">
                                    Set as Ad Studio default
                                </button>
                            </form>
                        @else
                            <span class="rounded-lg bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700">Ad Studio default</span>
                        @endif
                        @if(!empty($selected['username']))
                            <a href="https://instagram.com/{{ ltrim($selected['username'], '@') }}" target="_blank" rel="noopener"
                               class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                View on Instagram
                            </a>
                        @endif
                    </div>
                </div>
                <div class="mt-6 rounded-xl border border-slate-100 bg-slate-50 p-4 text-sm text-slate-600">
                    <p class="font-semibold text-slate-800">How sync works</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-xs">
                        <li><code class="text-[11px]">GET /{business-id}/owned_instagram_accounts</code> &amp; <code class="text-[11px]">owned_instagram_assets</code></li>
                        <li><code class="text-[11px]">GET /{business-id}/client_instagram_assets</code></li>
                        <li><code class="text-[11px]">GET /{business-id}/instagram_accounts</code></li>
                        <li><code class="text-[11px]">GET /act_{ad-account}/instagram_accounts</code></li>
                        <li>Facebook Pages with connected Instagram business accounts</li>
                    </ul>
                    <p class="mt-3 text-xs">Linked accounts appear in Ad Studio → Destinations → Instagram profile.</p>
                </div>
            @else
                <div class="py-16 text-center text-sm text-slate-500">
                    Select an Instagram account from the list, or link one with its Meta ID.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
