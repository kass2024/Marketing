@extends('layouts.admin')

@section('title', 'Ad sets')

@section('content')

<div class="mx-auto max-w-[1600px] space-y-6 sm:space-y-8">

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Ad sets</h1>
            <p class="mt-1 text-sm text-slate-600">
                Manage targeting, budgets and delivery settings.
            </p>
        </div>
        <div class="flex flex-shrink-0 flex-wrap items-center gap-2 sm:gap-3">
            <a
                href="{{ route('admin.campaigns.index') }}"
                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-xander-navy/25 hover:bg-slate-50 hover:text-xander-navy"
            >
                Campaigns
            </a>
            <a
                href="{{ route('admin.adsets.create') }}"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-xander-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-xander-secondary"
            >
                <span class="text-lg leading-none">+</span>
                Create ad set
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total ad sets</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ $adsetStats['total'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Active</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-600">{{ $adsetStats['active'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Paused</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-amber-600">{{ $adsetStats['paused'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Draft</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-600">{{ $adsetStats['draft'] }}</p>
        </div>
    </div>

    <div class="flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap gap-2">
            <select class="rounded-xl border-slate-200 text-sm shadow-sm focus:border-xander-navy focus:ring-xander-navy" aria-label="Filter by status">
                <option>All status</option>
                <option value="ACTIVE">Active</option>
                <option value="PAUSED">Paused</option>
                <option value="DRAFT">Draft</option>
            </select>
            <select class="rounded-xl border-slate-200 text-sm shadow-sm focus:border-xander-navy focus:ring-xander-navy" aria-label="Date range">
                <option>Last 30 days</option>
                <option>Last 7 days</option>
                <option>Today</option>
            </select>
        </div>
        <p class="text-sm font-medium text-slate-500">{{ $adsets->total() }} ad sets</p>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/5">
        <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
            <table class="w-full min-w-[900px] border-collapse text-left text-sm text-slate-700">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/95 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th class="w-10 px-3 py-3 lg:px-4">
                            <input type="checkbox" class="rounded border-slate-300 text-xander-navy focus:ring-xander-navy" aria-label="Select all">
                        </th>
                        <th class="px-4 py-3 lg:px-5">Ad set</th>
                        <th class="min-w-[8rem] px-4 py-3 lg:px-5">Campaign</th>
                        <th class="whitespace-nowrap px-4 py-3 lg:px-5">Budget</th>
                        <th class="whitespace-nowrap px-4 py-3 lg:px-5">Status</th>
                        <th class="min-w-[6rem] px-4 py-3 font-mono text-xs lg:px-5">Meta ID</th>
                        <th class="sticky right-0 z-20 min-w-[11rem] border-l border-slate-200 bg-slate-50/95 px-4 py-3 text-right shadow-[-12px_0_24px_-12px_rgba(15,23,42,0.12)] backdrop-blur-sm lg:min-w-[12rem] lg:px-5">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($adsets as $adset)
                        <tr class="group transition-colors hover:bg-slate-50/80">
                            <td class="px-3 py-3 align-top lg:px-4">
                                <input type="checkbox" class="rounded border-slate-300 text-xander-navy focus:ring-xander-navy" aria-label="Select row">
                            </td>
                            <td class="max-w-[14rem] px-4 py-3 align-top lg:px-5">
                                <div class="truncate font-medium text-slate-900" title="{{ $adset->name }}">{{ $adset->name }}</div>
                                <div class="text-xs text-slate-400">ID {{ $adset->id }}</div>
                            </td>
                            <td class="max-w-[12rem] px-4 py-3 align-top lg:px-5">
                                <span class="line-clamp-2 text-slate-700" title="{{ $adset->campaign->name ?? '' }}">{{ $adset->campaign->name ?? '—' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-top tabular-nums lg:px-5">
                                @if($adset->daily_budget)
                                    <span class="font-medium text-slate-900">${{ number_format($adset->daily_budget / 100, 2) }}</span>
                                    <div class="text-xs text-slate-500">Daily</div>
                                @else
                                    <span class="text-slate-400">No budget</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-top lg:px-5">
                                @switch($adset->status)
                                    @case('ACTIVE')
                                        <span class="inline-flex rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-600/15">Active</span>
                                        @break
                                    @case('PAUSED')
                                        <span class="inline-flex rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-600/15">Paused</span>
                                        @break
                                    @case('ARCHIVED')
                                        <span class="inline-flex rounded-md bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-400/30">Archived</span>
                                        @break
                                    @default
                                        <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-400/20">Draft</span>
                                @endswitch
                            </td>
                            <td class="max-w-[7rem] truncate px-4 py-3 align-top font-mono text-xs text-slate-600 lg:px-5" title="{{ $adset->meta_id ?? '' }}">
                                {{ $adset->meta_id ?? '—' }}
                            </td>
                            <td class="sticky right-0 z-10 min-w-[11rem] border-l border-slate-200 bg-white px-3 py-3 align-top shadow-[-12px_0_24px_-12px_rgba(15,23,42,0.1)] backdrop-blur-[2px] transition-colors group-hover:bg-slate-50/95 lg:min-w-[12rem] lg:px-4">
                                <div class="flex flex-col items-stretch gap-1.5">
                                    <a href="{{ route('admin.ads.create', ['adset' => $adset->id]) }}" class="rounded-lg bg-slate-50 px-2.5 py-1.5 text-center text-xs font-semibold text-violet-800 ring-1 ring-violet-600/15 transition hover:bg-violet-50">Create ad</a>
                                    <a href="{{ route('admin.ads.index', ['adset' => $adset->id]) }}" class="rounded-lg bg-slate-50 px-2.5 py-1.5 text-center text-xs font-semibold text-xander-navy ring-1 ring-slate-200/80 transition hover:bg-white hover:ring-xander-navy/25">View ads</a>
                                    <a href="{{ route('admin.adsets.edit', $adset->id) }}" class="rounded-lg bg-slate-50 px-2.5 py-1.5 text-center text-xs font-semibold text-xander-secondary ring-1 ring-slate-200/80 transition hover:bg-white">Edit</a>
                                    @if($adset->status == 'PAUSED' || $adset->status == 'DRAFT')
                                        <form method="POST" action="{{ route('admin.adsets.activate', $adset) }}" class="m-0">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="w-full rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-600/15 transition hover:bg-emerald-100">Activate</button>
                                        </form>
                                    @endif
                                    @if($adset->status == 'ACTIVE')
                                        <form method="POST" action="{{ route('admin.adsets.pause', $adset) }}" class="m-0">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="w-full rounded-lg bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-900 ring-1 ring-amber-600/15 transition hover:bg-amber-100">Pause</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.adsets.duplicate', $adset) }}" class="m-0">
                                        @csrf
                                        <button type="submit" class="w-full rounded-lg bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-800 ring-1 ring-slate-200/80 transition hover:bg-white">Duplicate</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.adsets.sync', $adset) }}" class="m-0">
                                        @csrf
                                        <button type="submit" class="w-full rounded-lg bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200/80 transition hover:bg-white">Sync</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.adsets.destroy', $adset) }}" class="m-0" onsubmit="return confirm('Delete this ad set?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="w-full rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 ring-1 ring-red-600/15 transition hover:bg-red-100">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center text-slate-500">
                                <div class="flex flex-col items-center gap-4">
                                    <p class="text-lg font-medium text-slate-700">No ad sets found</p>
                                    <p class="text-sm text-slate-500">Create an ad set from a campaign to start running ads.</p>
                                    <a href="{{ route('admin.campaigns.index') }}" class="inline-flex rounded-xl bg-xander-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-xander-secondary">Go to campaigns</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($adsets, 'hasPages') && $adsets->hasPages())
            <div class="border-t border-slate-200 bg-slate-50/50 px-4 py-3">
                {{ $adsets->links() }}
            </div>
        @endif
    </div>

</div>

@endsection
