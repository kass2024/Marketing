@extends('layouts.admin')

@section('title', 'Creatives')

@section('content')

<div class="mx-auto max-w-[1600px] space-y-6 sm:space-y-8">

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Creative library</h1>
            <p class="mt-1 text-sm text-slate-600">
                Monitor and manage Meta ad creatives synced with Facebook Ads.
            </p>
        </div>
        <div class="flex flex-shrink-0 flex-wrap items-center gap-2 sm:gap-3">
            <a
                href="{{ route('admin.ads.index') }}"
                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-xander-navy/25 hover:bg-slate-50 hover:text-xander-navy"
            >
                Ads
            </a>
            <a
                href="{{ route('admin.creatives.create') }}"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-xander-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-xander-secondary"
            >
                <span class="text-lg leading-none">+</span>
                Create creative
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
            <ul class="list-inside list-disc text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-5">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ $creativeStats['total'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Approved</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-600">{{ $creativeStats['approved'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Pending</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-amber-600">{{ $creativeStats['pending'] }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Rejected</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-red-600">{{ $creativeStats['rejected'] }}</p>
        </div>
        <div class="col-span-2 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm ring-1 ring-slate-900/5 sm:col-span-1 sm:p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Active delivery</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-xander-navy">{{ $creativeStats['active'] }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/5">
        <div class="overflow-x-auto overscroll-x-contain [-webkit-overflow-scrolling:touch]">
            <table class="w-full min-w-[1100px] border-collapse text-left text-sm text-slate-700">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/95 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th class="whitespace-nowrap px-4 py-3 lg:px-5">Preview</th>
                        <th class="px-4 py-3 lg:px-5">Creative</th>
                        <th class="min-w-[8rem] px-4 py-3 lg:px-5">Headline</th>
                        <th class="whitespace-nowrap px-4 py-3 lg:px-5">Review</th>
                        <th class="whitespace-nowrap px-4 py-3 lg:px-5">Delivery</th>
                        <th class="whitespace-nowrap px-4 py-3 text-right tabular-nums lg:px-5">Spend</th>
                        <th class="whitespace-nowrap px-4 py-3 text-right tabular-nums lg:px-5">Impr.</th>
                        <th class="whitespace-nowrap px-4 py-3 lg:px-5">Created</th>
                        <th class="sticky right-0 z-20 min-w-[10.5rem] border-l border-slate-200 bg-slate-50/95 px-4 py-3 text-right shadow-[-12px_0_24px_-12px_rgba(15,23,42,0.12)] backdrop-blur-sm lg:min-w-[11rem] lg:px-5">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($creatives as $creative)
                        @php
                            $review = strtoupper($creative->review_status ?? '');
                            $delivery = strtoupper($creative->effective_status ?? '');
                        @endphp
                        <tr class="group transition-colors hover:bg-slate-50/80">
                            <td class="px-4 py-3 align-top lg:px-5">
                                @if(!empty($creative->image_url))
                                    <div class="relative h-14 w-14 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-100">
                                        <img
                                            src="{{ $creative->image_url }}"
                                            alt=""
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                            onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden')"
                                        >
                                        <div class="hidden h-full w-full items-center justify-center bg-slate-200 text-[10px] font-medium text-slate-500" aria-hidden="true">—</div>
                                    </div>
                                @elseif(!empty($creative->video_url))
                                    <div class="flex h-14 w-14 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-xs font-medium text-slate-600">Video</div>
                                @else
                                    <div class="flex h-14 w-14 items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50 text-xs text-slate-400">No media</div>
                                @endif
                            </td>
                            <td class="max-w-[14rem] px-4 py-3 align-top lg:max-w-[18rem] lg:px-5">
                                <div class="truncate font-medium text-slate-900" title="{{ $creative->name }}">{{ $creative->name }}</div>
                                @if(!empty($creative->meta_id))
                                    <div class="mt-0.5 truncate text-xs text-slate-400">Meta {{ $creative->meta_id }}</div>
                                    @if(empty($creative->review_status) && $creative->effective_status !== 'ACTIVE')
                                        <div class="mt-1 text-xs text-amber-700">Waiting for Meta review</div>
                                    @endif
                                @else
                                    <div class="mt-1 text-xs text-red-600">Not on Meta</div>
                                @endif
                            </td>
                            <td class="max-w-[12rem] px-4 py-3 align-top text-slate-700 lg:px-5">
                                <span class="line-clamp-2" title="{{ $creative->headline ?? '' }}">{{ $creative->headline ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3 align-top lg:px-5">
                                @if($review === 'DISAPPROVED')
                                    <span class="inline-flex rounded-md bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-800 ring-1 ring-red-600/15">Rejected</span>
                                @elseif($review === 'APPROVED' || $delivery === 'ACTIVE')
                                    <span class="inline-flex rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-600/15">Approved</span>
                                @elseif($review === 'PENDING_REVIEW' || empty($review))
                                    <span class="inline-flex rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-600/15">Pending</span>
                                @else
                                    <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-400/20">Draft</span>
                                @endif
                                @if(!empty($creative->review_feedback))
                                    <div class="mt-2 max-w-xs text-xs text-red-600">{{ $creative->review_feedback }}</div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-top lg:px-5">
                                @if($creative->effective_status === 'ACTIVE')
                                    <span class="inline-flex rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-600/15">Active</span>
                                @elseif($creative->effective_status === 'PAUSED')
                                    <span class="inline-flex rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-600/15">Paused</span>
                                @else
                                    <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-400/20">Inactive</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-700 lg:px-5">
                                @if(isset($creative->spend) && $creative->spend > 0)
                                    ${{ number_format($creative->spend, 2) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-600 lg:px-5">
                                @if(isset($creative->impressions))
                                    {{ number_format($creative->impressions) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 align-top text-slate-500 lg:px-5">
                                {{ optional($creative->created_at)->format('d M Y') }}
                            </td>
                            <td class="sticky right-0 z-10 min-w-[10.5rem] border-l border-slate-200 bg-white px-3 py-3 align-top shadow-[-12px_0_24px_-12px_rgba(15,23,42,0.1)] backdrop-blur-[2px] transition-colors group-hover:bg-slate-50/95 lg:min-w-[11rem] lg:px-4">
                                <div class="flex flex-col items-stretch gap-1.5">
                                    <a href="{{ route('admin.creatives.preview', $creative->id) }}" class="rounded-lg bg-slate-50 px-2.5 py-1.5 text-center text-xs font-semibold text-xander-navy ring-1 ring-slate-200/80 transition hover:bg-white hover:ring-xander-navy/25">Preview</a>
                                    <a href="{{ route('admin.creatives.edit', $creative->id) }}" class="rounded-lg bg-slate-50 px-2.5 py-1.5 text-center text-xs font-semibold text-xander-secondary ring-1 ring-slate-200/80 transition hover:bg-white">Edit</a>
                                    @if($creative->effective_status !== 'ACTIVE')
                                        <form method="POST" action="{{ route('admin.creatives.activate', $creative->id) }}" class="m-0">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="w-full rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-600/15 transition hover:bg-emerald-100">Activate</button>
                                        </form>
                                    @endif
                                    @if($creative->effective_status === 'ACTIVE')
                                        <form method="POST" action="{{ route('admin.creatives.pause', $creative->id) }}" class="m-0">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="w-full rounded-lg bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-900 ring-1 ring-amber-600/15 transition hover:bg-amber-100">Pause</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.creatives.sync', $creative->id) }}" class="m-0">
                                        @csrf
                                        <button type="submit" class="w-full rounded-lg bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200/80 transition hover:bg-white">Sync</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.creatives.destroy', $creative->id) }}" class="m-0" onsubmit="return confirm('Delete this creative?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="w-full rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-700 ring-1 ring-red-600/15 transition hover:bg-red-100">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-16 text-center text-slate-500">
                                <div class="flex flex-col items-center gap-4">
                                    <p class="text-lg font-medium text-slate-700">No creatives yet</p>
                                    <a href="{{ route('admin.creatives.create') }}" class="inline-flex rounded-xl bg-xander-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-xander-secondary">Create first creative</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($creatives, 'hasPages') && $creatives->hasPages())
            <div class="border-t border-slate-200 bg-slate-50/50 px-4 py-3">
                {{ $creatives->links() }}
            </div>
        @endif
    </div>

</div>

@endsection
