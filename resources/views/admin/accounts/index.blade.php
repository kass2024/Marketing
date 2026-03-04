@extends('layouts.app')

@section('content')

<div class="space-y-8">

    {{-- ================= HEADER ================= --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">

        <div>
            <h1 class="text-3xl font-bold text-gray-900">
                Ad Accounts
            </h1>

            <p class="text-gray-500 mt-1">
                Manage and synchronize your Meta advertising accounts.
            </p>
        </div>

        {{-- Sync Button --}}
        <form method="POST" action="{{ route('admin.accounts.store') }}" id="syncForm">
            @csrf

            <button
                id="syncBtn"
                type="submit"
                class="inline-flex items-center gap-3 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl shadow transition disabled:opacity-50">

                <svg id="syncSpinner"
                     class="hidden animate-spin h-5 w-5"
                     xmlns="http://www.w3.org/2000/svg"
                     fill="none"
                     viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10"
                        stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>

                <span id="syncText">Sync From Meta</span>

            </button>

        </form>

    </div>


    {{-- ================= ALERTS ================= --}}
    @if(session('success'))
        <div class="p-4 rounded-xl bg-green-50 border border-green-200 text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-700">
            {{ $errors->first() }}
        </div>
    @endif


    {{-- ================= CARD ================= --}}
    <div class="bg-white shadow rounded-2xl overflow-hidden">

        <div class="overflow-x-auto">

            <table class="min-w-full divide-y divide-gray-200">

                {{-- TABLE HEADER --}}
                <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500">
                    <tr>

                        <th class="px-6 py-4 text-left">
                            Account
                        </th>

                        <th class="px-6 py-4 text-left">
                            Currency
                        </th>

                        <th class="px-6 py-4 text-left">
                            Status
                        </th>

                        <th class="px-6 py-4 text-right">
                            Actions
                        </th>

                    </tr>
                </thead>


                {{-- TABLE BODY --}}
                <tbody class="divide-y divide-gray-100">

                    @forelse($accounts as $account)

                        <tr class="hover:bg-gray-50 transition">

                            {{-- Account --}}
                            <td class="px-6 py-4">

                                <div class="font-semibold text-gray-900">
                                    {{ $account->name }}
                                </div>

                                <div class="text-xs text-gray-400 mt-1">
                                    Meta ID: {{ $account->meta_id ?? $account->ad_account_id }}
                                </div>

                            </td>


                            {{-- Currency --}}
                            <td class="px-6 py-4 text-gray-700 text-sm">
                                {{ $account->currency ?? '—' }}
                            </td>


                            {{-- Status --}}
                            <td class="px-6 py-4">

                                @php
                                    $status = strtoupper($account->account_status ?? 'UNKNOWN');
                                @endphp

                                @switch($status)

                                    @case('ACTIVE')
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">
                                            Active
                                        </span>
                                        @break

                                    @case('DISABLED')
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700">
                                            Disabled
                                        </span>
                                        @break

                                    @case('PENDING')
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-700">
                                            Pending
                                        </span>
                                        @break

                                    @default
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">
                                            {{ $status }}
                                        </span>

                                @endswitch

                            </td>


                            {{-- Actions --}}
                            <td class="px-6 py-4 text-right">

                                <form method="POST"
                                      action="{{ route('admin.accounts.destroy', $account) }}"
                                      onsubmit="return confirm('Remove this account locally?')">

                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="text-red-500 hover:text-red-700 text-sm font-medium">

                                        Delete

                                    </button>

                                </form>

                            </td>

                        </tr>

                    @empty

                        {{-- EMPTY STATE --}}
                        <tr>

                            <td colspan="4" class="text-center py-20">

                                <div class="text-6xl text-gray-300 mb-4">
                                    📊
                                </div>

                                <h3 class="text-lg font-semibold text-gray-700">
                                    No Ad Accounts Found
                                </h3>

                                <p class="text-gray-500 mt-2">
                                    Click <strong>Sync From Meta</strong> to import your advertising accounts.
                                </p>

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        {{-- Pagination --}}
        @if(method_exists($accounts, 'links'))
            <div class="p-4 border-t bg-gray-50">
                {{ $accounts->links() }}
            </div>
        @endif

    </div>

</div>



{{-- ================= SYNC UX ================= --}}
<script>

document.getElementById('syncForm').addEventListener('submit', function(){

    const btn = document.getElementById('syncBtn')
    const spinner = document.getElementById('syncSpinner')
    const text = document.getElementById('syncText')

    btn.disabled = true
    spinner.classList.remove('hidden')
    text.innerText = 'Syncing...'

})

</script>

@endsection