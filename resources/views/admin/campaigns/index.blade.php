@extends('layouts.admin')

@section('title', 'Campaigns')

@section('content')

<div class="mx-auto max-w-[1600px] space-y-6 sm:space-y-8">

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Campaigns</h1>
            <p class="mt-1 text-sm text-slate-600">
                Create campaigns first, then build ad sets, creatives and ads.
            </p>
        </div>
        <a
            href="{{ route('admin.campaigns.create') }}"
            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-xander-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-xander-secondary"
        >
            <span class="text-lg leading-none">+</span>
            New campaign
        </a>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total campaigns</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ $campaigns->total() }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Active</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-600">{{ $activeCampaigns }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Paused</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-amber-600">{{ $pausedCampaigns }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total ad sets</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-xander-secondary">{{ $totalAdSets }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/5">
        <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
            <table class="w-full min-w-[920px] border-collapse text-left text-sm text-slate-700">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/95 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-3 lg:px-5">Campaign</th>
                        <th class="whitespace-nowrap px-4 py-3 lg:px-5">Objective</th>
                        <th class="whitespace-nowrap px-4 py-3 lg:px-5">Budget</th>
                        <th class="whitespace-nowrap px-4 py-3 text-right tabular-nums lg:px-5">Ad sets</th>
                        <th class="whitespace-nowrap px-4 py-3 lg:px-5">Status</th>
                        <th class="whitespace-nowrap px-4 py-3 lg:px-5">Created</th>
                        <th class="sticky right-0 z-20 min-w-[11rem] border-l border-slate-200 bg-slate-50/95 px-4 py-3 text-right shadow-[-12px_0_24px_-12px_rgba(15,23,42,0.12)] backdrop-blur-sm lg:min-w-[12rem] lg:px-5">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($campaigns as $campaign)
                        <tr class="group transition-colors hover:bg-slate-50/80">
                            <td class="max-w-[16rem] px-4 py-3 align-top lg:max-w-[20rem] lg:px-5">
                                <a
                                    href="{{ route('admin.campaigns.show', $campaign) }}"
                                    class="font-medium text-xander-navy transition hover:text-xander-secondary"
                                >
                                    {{ $campaign->name }}
                                </a>
                                @if(!empty($campaign->meta_id))
                                    <div class="mt-0.5 truncate text-xs text-slate-400">Meta {{ $campaign->meta_id }}</div>
                                @endif
                            </td>
                            <td class="max-w-[10rem] whitespace-nowrap px-4 py-3 align-top text-slate-700 lg:px-5">
                                <span class="line-clamp-2" title="{{ $campaign->objective ?? '' }}">{{ $campaign->objective ?? 'Not set' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-top tabular-nums lg:px-5">
                                @if(!empty($campaign->daily_budget))
                                    <span class="font-medium text-slate-900">${{ number_format(($campaign->daily_budget ?? 0) / 100, 2) }}<span class="text-slate-500">/day</span></span>
                                @else
                                    <span class="text-slate-400">No budget</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right align-top lg:px-5">
                                <a
                                    href="{{ route('admin.campaigns.adsets.index', $campaign->id) }}"
                                    class="text-sm font-semibold tabular-nums text-xander-secondary transition hover:text-xander-navy"
                                >
                                    {{ $campaign->ad_sets_count ?? 0 }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-top lg:px-5">
                                @if($campaign->status == 'ACTIVE')
                                    <span class="inline-flex rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-600/15">Active</span>
                                @elseif($campaign->status == 'PAUSED')
                                    <span class="inline-flex rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-600/15">Paused</span>
                                @else
                                    <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-400/20">{{ $campaign->status ?? 'Draft' }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-top text-slate-500 lg:px-5">
                                {{ optional($campaign->created_at)->format('d M Y') }}
                            </td>
                            <td class="sticky right-0 z-10 min-w-[11rem] border-l border-slate-200 bg-white px-3 py-3 align-top shadow-[-12px_0_24px_-12px_rgba(15,23,42,0.1)] backdrop-blur-[2px] transition-colors group-hover:bg-slate-50/95 lg:min-w-[12rem] lg:px-4">
                                <div class="flex flex-col items-stretch gap-1.5">
                                    <a href="{{ route('admin.campaigns.adsets.index', $campaign->id) }}" class="rounded-lg bg-slate-50 px-2.5 py-1.5 text-center text-xs font-semibold text-xander-navy ring-1 ring-slate-200/80 transition hover:bg-white hover:ring-xander-navy/25">Ad sets</a>
                                    <a href="{{ route('admin.campaigns.adsets.create', $campaign->id) }}" class="rounded-lg bg-slate-50 px-2.5 py-1.5 text-center text-xs font-semibold text-xander-secondary ring-1 ring-slate-200/80 transition hover:bg-white hover:ring-xander-navy/25">New ad set</a>
                                    <a href="{{ route('admin.creatives.index', ['campaign' => $campaign->id]) }}" class="rounded-lg bg-slate-50 px-2.5 py-1.5 text-center text-xs font-semibold text-emerald-800 ring-1 ring-emerald-600/15 transition hover:bg-emerald-50">Creatives</a>
                                    <a href="{{ route('admin.ads.index', ['campaign' => $campaign->id]) }}" class="rounded-lg bg-slate-50 px-2.5 py-1.5 text-center text-xs font-semibold text-violet-800 ring-1 ring-violet-600/15 transition hover:bg-violet-50">Ads</a>
                                    @if($campaign->status !== 'ACTIVE')
                                        <form method="POST" action="{{ route('admin.campaigns.activate', $campaign->id) }}" class="m-0">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="w-full rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-600/15 transition hover:bg-emerald-100">Activate</button>
                                        </form>
                                    @endif
                                    @if($campaign->status === 'ACTIVE')
                                        <form method="POST" action="{{ route('admin.campaigns.pause', $campaign->id) }}" class="m-0">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="w-full rounded-lg bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-900 ring-1 ring-amber-600/15 transition hover:bg-amber-100">Pause</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.campaigns.sync', $campaign->id) }}" class="m-0">
                                        @csrf
                                        <button type="submit" class="w-full rounded-lg bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200/80 transition hover:bg-white">Sync</button>
                                    </form>
                                    <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="rounded-lg bg-slate-50 px-2.5 py-1.5 text-center text-xs font-semibold text-slate-800 ring-1 ring-slate-200/80 transition hover:bg-white">Edit</a>
                                    <form action="{{ route('admin.campaigns.destroy', $campaign) }}" method="POST" class="m-0" onsubmit="return confirm('Delete this campaign?');">
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
                                    <div class="text-4xl opacity-40" aria-hidden="true">📢</div>
                                    <p class="text-lg font-medium text-slate-700">No campaigns yet</p>
                                    <a href="{{ route('admin.campaigns.create') }}" class="inline-flex rounded-xl bg-xander-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-xander-secondary">Create first campaign</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($campaigns->hasPages())
            <div class="border-t border-slate-200 bg-slate-50/50 px-4 py-3">
                {{ $campaigns->links() }}
            </div>
        @endif
    </div>

    @if(!isset($hasAdAccount) || !$hasAdAccount)
        <div class="rounded-2xl border border-amber-200/80 bg-amber-50/90 p-4 text-sm text-amber-900 ring-1 ring-amber-400/20">
            <p>
                <strong>Meta ad account not connected.</strong>
                You can still create campaigns locally for testing.
                <a href="{{ route('admin.accounts.index') }}" class="ml-1 font-semibold text-xander-navy underline decoration-xander-navy/30 underline-offset-2 hover:text-xander-secondary">Connect account</a>
            </p>
        </div>
    @endif

</div>

@endsection
