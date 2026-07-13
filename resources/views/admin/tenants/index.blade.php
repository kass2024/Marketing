@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-7xl space-y-8 px-4 py-10 sm:px-6">

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Tenant monitor</h1>
            <p class="mt-2 max-w-2xl text-slate-500">
                Multi-tenant overview: each business uses its own Facebook Page and WhatsApp for ads.
                Meta API access is controlled by the main platform account (.env).
            </p>
        </div>
        <form method="POST" action="{{ route('admin.tenants.sync-platform') }}">
            @csrf
            <button type="submit" class="rounded-xl bg-xander-navy px-5 py-2.5 text-sm font-semibold text-white hover:bg-xander-accent">
                Sync main account from .env
            </button>
        </form>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
            {{ session('error') }}
        </div>
    @endif

    @if($platformConnection)
        <div class="rounded-2xl border border-xander-gold/40 bg-gradient-to-r from-xander-navy/5 to-xander-gold/10 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-xander-navy/70">Main platform account (.env)</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-900">{{ $platformConnection->page_name ?? config('app.name') }}</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        WA phone ID: <span class="font-mono">{{ $platformConnection->whatsapp_phone_number_id ?? '—' }}</span>
                        · Page: {{ $platformConnection->page_id ?? '—' }}
                    </p>
                </div>
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                    {{ $platformConnection->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-6 py-4">Tenant</th>
                        <th class="px-6 py-4">Type</th>
                        <th class="px-6 py-4">WhatsApp</th>
                        <th class="px-6 py-4">Connection</th>
                        <th class="px-6 py-4">Campaigns</th>
                        <th class="px-6 py-4">Inbox</th>
                        <th class="px-6 py-4">Webhooks (24h)</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($tenants as $row)
                        @php
                            $client = $row['client'];
                            $conn = $row['connection'];
                        @endphp
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-6 py-4">
                                <div class="font-semibold text-slate-900">{{ $client->company_name }}</div>
                                <div class="text-xs text-slate-500">{{ $client->user?->email ?? 'platform' }} · #{{ $client->id }}</div>
                            </td>
                            <td class="px-6 py-4">
                                @if($client->is_platform)
                                    <span class="rounded-full bg-xander-gold/20 px-2.5 py-0.5 text-xs font-semibold text-xander-navy">Platform</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ $client->subscription_plan }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-slate-700">
                                +{{ $client->whatsapp_phone_number ?? '—' }}
                            </td>
                            <td class="px-6 py-4">
                                @if($client->hasPublishingProfile() && $conn?->is_active)
                                    <span class="text-emerald-700">Ready</span>
                                @elseif($client->is_platform && $conn?->is_active)
                                    <span class="text-emerald-700">Platform API</span>
                                @else
                                    <span class="text-amber-700">Incomplete profile</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">{{ $client->campaigns_count }}</td>
                            <td class="px-6 py-4">
                                {{ $client->conversations_count }}
                                @if($row['unread_messages'] > 0)
                                    <span class="ml-1 rounded-full bg-red-500 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $row['unread_messages'] }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">{{ $row['recent_webhooks'] }}</td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('admin.clients.show', $client) }}" class="text-xander-navy font-medium hover:underline">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-slate-500">No tenants yet. Run <code class="rounded bg-slate-100 px-1">php artisan waba:install --fresh</code>.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
