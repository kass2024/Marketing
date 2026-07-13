@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-7xl space-y-8 px-4 py-10 sm:px-6">

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Business accounts</h1>
            <p class="mt-2 max-w-2xl text-slate-500">
                Registered businesses, their Facebook Pages, and linked ad workspaces.
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <form method="GET" class="flex flex-col gap-3 sm:flex-row">
        <input
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search business, email, or page…"
            class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm sm:max-w-md"
        >
        <button type="submit" class="rounded-xl bg-xander-navy px-5 py-2.5 text-sm font-semibold text-white">
            Search
        </button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-6 py-4">Business</th>
                        <th class="px-6 py-4">Owner</th>
                        <th class="px-6 py-4">Facebook Page</th>
                        <th class="px-6 py-4">WhatsApp (ads)</th>
                        <th class="px-6 py-4">Plan</th>
                        <th class="px-6 py-4">Campaigns</th>
                        <th class="px-6 py-4">Joined</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($clients as $client)
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-6 py-4">
                                <div class="font-semibold text-slate-900">{{ $client->company_name }}</div>
                                <div class="text-xs text-slate-500">#{{ $client->id }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-slate-800">{{ $client->user?->name ?? '—' }}</div>
                                <div class="text-xs text-slate-500">{{ $client->user?->email ?? '—' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                @if($client->meta_page_name)
                                    <div class="font-medium text-slate-800">{{ $client->meta_page_name }}</div>
                                    <div class="text-xs text-slate-500">ID {{ $client->meta_page_id }}</div>
                                @else
                                    <span class="text-slate-400">Not set</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($client->whatsapp_phone_number)
                                    <div class="font-medium text-slate-800">+{{ $client->whatsapp_phone_number }}</div>
                                    <div class="text-xs text-slate-500">Ad destination</div>
                                @else
                                    <span class="text-slate-400">Not set</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 capitalize text-slate-700">{{ $client->subscription_plan }}</td>
                            <td class="px-6 py-4 text-slate-700">{{ $client->campaigns_count ?? $client->campaigns()->count() }}</td>
                            <td class="px-6 py-4 text-slate-500">{{ $client->created_at?->format('M j, Y') }}</td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('admin.clients.impersonate', $client) }}"
                                   class="font-semibold text-xander-navy hover:underline">
                                    Open as user
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-10 text-center text-slate-500">
                                No business accounts yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $clients->withQueryString()->links() }}
</div>
@endsection
