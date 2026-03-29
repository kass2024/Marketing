@extends('layouts.admin')

@section('title', 'Business Manager')

@section('content')

<div class="mx-auto max-w-5xl">

    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/5">

        <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-8 py-8 sm:px-10">

            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        Master Meta Business Connection
                    </h2>
                    <p class="mt-2 text-slate-500">
                        Platform-wide Meta integration for ads and Business assets
                    </p>
                </div>

                @if(!empty($platformMeta))
                    <span class="inline-flex shrink-0 items-center rounded-full bg-emerald-100 px-4 py-1.5 text-sm font-semibold text-emerald-800 ring-1 ring-inset ring-emerald-600/20">
                        Connected
                    </span>
                @else
                    <span class="inline-flex shrink-0 items-center rounded-full bg-slate-100 px-4 py-1.5 text-sm font-semibold text-slate-600 ring-1 ring-inset ring-slate-500/10">
                        Not connected
                    </span>
                @endif
            </div>
        </div>

        <div class="px-8 py-8 sm:px-10 sm:py-10">

            @foreach (['success','error','info'] as $msg)
                @if(session($msg))
                    <div class="mb-6 rounded-xl border px-4 py-3 text-sm font-medium
                        @if($msg === 'success') border-emerald-200 bg-emerald-50 text-emerald-800
                        @elseif($msg === 'error') border-red-200 bg-red-50 text-red-800
                        @else border-blue-200 bg-blue-50 text-blue-800
                        @endif">
                        {{ session($msg) }}
                    </div>
                @endif
            @endforeach

            @if(!empty($platformMeta))

                <dl class="grid gap-6 sm:grid-cols-1">

                    <div class="rounded-xl border border-slate-100 bg-slate-50/50 px-5 py-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Business name</dt>
                        <dd class="mt-1 text-lg font-semibold text-slate-900">
                            {{ $platformMeta->business_name ?? 'N/A' }}
                        </dd>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50/50 px-5 py-4">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Business ID</dt>
                        <dd class="mt-1 font-mono text-slate-800">
                            {{ $platformMeta->business_id ?? 'N/A' }}
                        </dd>
                    </div>

                    @if(!empty($platformMeta->token_expires_at))
                        <div class="rounded-xl border border-slate-100 bg-slate-50/50 px-5 py-4">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Token expires</dt>
                            <dd class="mt-1 text-slate-800">
                                {{ \Carbon\Carbon::parse($platformMeta->token_expires_at)->format('d M Y H:i') }}
                            </dd>
                        </div>
                    @endif

                </dl>

                <div class="mt-10 flex flex-wrap gap-3">
                    <form method="POST"
                          action="{{ route('admin.meta.disconnect') }}"
                          onsubmit="return confirm('Disconnect Meta? Platform tokens will be removed.')">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center justify-center rounded-xl bg-red-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                            Disconnect business
                        </button>
                    </form>
                </div>

            @else

                <p class="text-slate-600 mb-8 max-w-lg">
                    Connect your Meta Business to sync ad accounts, campaigns, and creatives with this dashboard.
                </p>

                <a href="{{ route('admin.meta.connect') }}"
                   class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-3 text-sm font-semibold text-white shadow-md shadow-blue-500/25 transition hover:from-blue-700 hover:to-indigo-700">
                    Connect Meta Business
                </a>

            @endif

        </div>

    </div>

</div>

@endsection
