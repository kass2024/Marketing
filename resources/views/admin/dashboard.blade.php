@extends('layouts.admin')

@section('title', 'Overview')

@section('content')

<div class="mx-auto max-w-7xl space-y-8">

    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-xander-navy sm:text-3xl">Overview</h1>
            <p class="mt-1 text-sm text-slate-600">Account health, activity, and quick shortcuts — similar to Meta Ads Manager.</p>
        </div>
        @if(!empty($platformMeta))
            <span class="inline-flex w-fit items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                Meta connected
            </span>
        @endif
    </div>

    {{-- KPI grid (Meta-style metric tiles) --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ad accounts</p>
            <p class="mt-2 text-3xl font-bold text-xander-navy">{{ $adsStats['ad_accounts'] ?? 0 }}</p>
            <a href="{{ route('admin.accounts.index') }}" class="mt-3 inline-block text-xs font-semibold text-xander-secondary hover:text-xander-navy">View accounts →</a>
        </div>
        <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Campaigns</p>
            <p class="mt-2 text-3xl font-bold text-xander-navy">{{ $stats['total_campaigns'] ?? 0 }}</p>
            <p class="text-xs text-slate-500 mt-1">{{ $stats['active_campaigns'] ?? 0 }} active</p>
            <a href="{{ route('admin.campaigns.index') }}" class="mt-3 inline-block text-xs font-semibold text-xander-secondary hover:text-xander-navy">Manage →</a>
        </div>
        <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ad sets</p>
            <p class="mt-2 text-3xl font-bold text-xander-navy">{{ $adsStats['ad_sets'] ?? 0 }}</p>
            <a href="{{ route('admin.adsets.index') }}" class="mt-3 inline-block text-xs font-semibold text-xander-secondary hover:text-xander-navy">View ad sets →</a>
        </div>
        <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ads</p>
            <p class="mt-2 text-3xl font-bold text-xander-navy">{{ $adsStats['ads'] ?? 0 }}</p>
            <a href="{{ route('admin.ads.index') }}" class="mt-3 inline-block text-xs font-semibold text-xander-secondary hover:text-xander-navy">View ads →</a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Business connection --}}
        <div class="lg:col-span-2 rounded-xl border border-slate-200/80 bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-xander-navy">Business Manager</h2>
                    <p class="mt-1 text-sm text-slate-600">Platform token and business linkage</p>
                </div>
                @if(!empty($platformMeta))
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">Connected</span>
                @else
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Not connected</span>
                @endif
            </div>

            @if(!empty($platformMeta))
                <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-lg bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase text-slate-500">Business ID</dt>
                        <dd class="mt-1 font-mono text-sm text-slate-900">{{ $platformMeta->business_id }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-4">
                        <dt class="text-xs font-semibold uppercase text-slate-500">Name</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-900">{{ $platformMeta->business_name ?? '—' }}</dd>
                    </div>
                </dl>
                <form method="POST" action="{{ route('admin.meta.disconnect') }}" class="mt-6" onsubmit="return confirm('Disconnect Meta for this platform?');">
                    @csrf
                    <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                        Disconnect
                    </button>
                </form>
            @else
                <p class="mt-4 text-sm text-slate-600">Connect your Meta Business to sync campaigns and ad accounts.</p>
                <a href="{{ route('admin.meta.connect') }}" class="mt-4 inline-flex rounded-lg bg-xander-navy px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:bg-xander-secondary">
                    Connect Meta Business
                </a>
            @endif
        </div>

        {{-- Chatbot / comms snapshot --}}
        <div class="rounded-xl border border-slate-200/80 bg-gradient-to-br from-xander-navy to-xander-secondary p-6 text-white shadow-md ring-1 ring-xander-accent/20">
            <h2 class="text-lg font-semibold text-white">Chatbot monitor</h2>
            <p class="mt-1 text-sm text-white/75">Conversations and messaging</p>
            <dl class="mt-6 space-y-4">
                <div>
                    <dt class="text-xs font-medium text-white/60">Total conversations</dt>
                    <dd class="text-2xl font-bold text-xander-gold">{{ $stats['total_conversations'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-white/60">Messages today</dt>
                    <dd class="text-xl font-semibold">{{ $stats['messages_today'] ?? 0 }}</dd>
                </div>
            </dl>
            <a href="{{ route('admin.inbox.index') }}" class="mt-6 inline-flex w-full justify-center rounded-lg bg-xander-gold px-4 py-2.5 text-sm font-bold text-white transition hover:brightness-110">
                Open inbox
            </a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/5">
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="font-semibold text-xander-navy">Recent campaigns</h3>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse($recentCampaigns ?? [] as $c)
                    <li class="flex items-center justify-between px-5 py-3 text-sm">
                        <span class="font-medium text-slate-800">{{ $c->name }}</span>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $c->status ?? '—' }}</span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-500">No campaigns yet.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/5">
            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="font-semibold text-xander-navy">Platform snapshot</h3>
            </div>
            <ul class="divide-y divide-slate-100 px-5 py-2 text-sm">
                <li class="flex justify-between py-2"><span class="text-slate-600">Users</span><span class="font-semibold text-slate-900">{{ $stats['total_users'] ?? 0 }}</span></li>
                <li class="flex justify-between py-2"><span class="text-slate-600">Clients</span><span class="font-semibold text-slate-900">{{ $stats['total_clients'] ?? 0 }}</span></li>
                <li class="flex justify-between py-2"><span class="text-slate-600">Jobs pending</span><span class="font-semibold text-slate-900">{{ $queueStats['pending_jobs'] ?? 0 }}</span></li>
                <li class="flex justify-between py-2"><span class="text-slate-600">Failed jobs</span><span class="font-semibold text-red-600">{{ $queueStats['failed_jobs'] ?? 0 }}</span></li>
            </ul>
        </div>
    </div>

</div>

@endsection
