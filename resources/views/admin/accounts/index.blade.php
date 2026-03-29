@extends('layouts.admin')

@section('content')

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-10 space-y-8">

    {{-- HEADER --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">

        <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">
                    Ad Accounts
                </h1>
                <p class="text-slate-500 mt-2 max-w-xl">
                    Sync and manage Meta ad accounts linked to Parrot Canada Visa Consultant.
                </p>
        </div>

        <form method="POST" action="{{ route('admin.accounts.store') }}" id="syncForm" class="shrink-0">
            @csrf
            <button
                id="syncBtn"
                type="submit"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-md shadow-blue-500/25 transition hover:from-blue-700 hover:to-indigo-700 hover:shadow-lg disabled:opacity-50 disabled:pointer-events-none">

                <svg id="syncSpinner"
                     class="hidden h-5 w-5 animate-spin"
                     xmlns="http://www.w3.org/2000/svg"
                     fill="none"
                     viewBox="0 0 24 24"
                     aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>

                <span id="syncText">Sync from Meta</span>
            </button>
        </form>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm font-medium">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm font-medium">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/5">

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">

                <thead>
                    <tr class="bg-slate-50/80 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <th class="px-6 py-4">Account</th>
                        <th class="px-6 py-4">Currency</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">

                    @forelse($accounts as $account)

                        <tr class="transition hover:bg-slate-50/80">

                            <td class="px-6 py-4">
                                <div class="font-semibold text-slate-900">
                                    {{ $account->display_name }}
                                </div>
                                <div class="mt-1 font-mono text-xs text-slate-400">
                                    {{ $account->meta_id ?? $account->ad_account_id }}
                                </div>
                            </td>

                            <td class="px-6 py-4 text-slate-700">
                                {{ $account->currency ?? '—' }}
                            </td>

                            <td class="px-6 py-4">
                                @php
                                    $status = strtoupper($account->account_status ?? 'UNKNOWN');
                                @endphp

                                @switch($status)
                                    @case('ACTIVE')
                                        <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                                            Active
                                        </span>
                                        @break
                                    @case('DISABLED')
                                        <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                                            Disabled
                                        </span>
                                        @break
                                    @case('PENDING')
                                        <span class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">
                                            Pending
                                        </span>
                                        @break
                                    @default
                                        <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                            {{ $status }}
                                        </span>
                                @endswitch
                            </td>

                            <td class="px-6 py-4 text-right">
                                <form method="POST"
                                      action="{{ route('admin.accounts.destroy', $account) }}"
                                      onsubmit="return confirm('Remove this account from the platform?')">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="text-sm font-semibold text-red-600 hover:text-red-800">
                                        Remove
                                    </button>
                                </form>
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="4" class="px-6 py-20 text-center">
                                <div class="mx-auto max-w-sm">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl">
                                        📊
                                    </div>
                                    <h3 class="mt-4 text-lg font-semibold text-slate-800">
                                        No ad accounts yet
                                    </h3>
                                    <p class="mt-2 text-sm text-slate-500">
                                        Use <strong>Sync from Meta</strong> to import accounts from your connected Business.
                                    </p>
                                </div>
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>
        </div>

        @if(method_exists($accounts, 'links'))
            <div class="border-t border-slate-100 bg-slate-50/50 px-4 py-3">
                {{ $accounts->links() }}
            </div>
        @endif

    </div>

</div>

<script>
document.getElementById('syncForm')?.addEventListener('submit', function () {
    const btn = document.getElementById('syncBtn');
    const spinner = document.getElementById('syncSpinner');
    const text = document.getElementById('syncText');
    btn.disabled = true;
    spinner.classList.remove('hidden');
    text.textContent = 'Syncing…';
});
</script>

@endsection
