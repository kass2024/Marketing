@extends('layouts.app')

@section('content')

<div class="space-y-8">

    {{-- ================= PAGE HEADER ================= --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">

        <div>
            <h1 class="text-3xl font-bold text-gray-900">
                Ad Accounts
            </h1>
            <p class="text-gray-500 mt-2">
                Manage and synchronize your Meta advertising accounts.
            </p>
        </div>

        <form method="POST"
              action="{{ route('admin.accounts.store') }}"
              id="syncForm"
              class="inline">
            @csrf

            <button type="submit"
                    id="syncBtn"
                    class="inline-flex items-center gap-3 bg-blue-600 text-white px-6 py-3 rounded-xl shadow hover:bg-blue-700 active:scale-95 transition disabled:opacity-60 disabled:cursor-not-allowed">

                <svg id="syncSpinner"
                     class="hidden animate-spin h-5 w-5"
                     xmlns="http://www.w3.org/2000/svg"
                     fill="none"
                     viewBox="0 0 24 24">
                    <circle class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"></circle>
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
        <div class="flex items-center gap-3 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl">
            <span class="text-xl">✅</span>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->has('meta') || $errors->has('error'))
        <div class="flex items-center gap-3 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl">
            <span class="text-xl">⚠</span>
            <span>
                {{ $errors->first('meta') ?? $errors->first('error') }}
            </span>
        </div>
    @endif


    {{-- ================= MAIN CARD ================= --}}
    <div class="bg-white rounded-2xl shadow overflow-hidden">

        <div class="overflow-x-auto">

            <table class="min-w-full divide-y divide-gray-200">

                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Account
                        </th>

                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Currency
                        </th>

                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Status
                        </th>

                        <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-100">

                    @forelse($accounts as $account)

                        <tr class="hover:bg-gray-50 transition">

                            {{-- Account Info --}}
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ $account->name }}
                                </div>
                                <div class="text-xs text-gray-400 mt-1">
                                    Meta ID: {{ $account->meta_id }}
                                </div>
                            </td>

                            {{-- Currency --}}
                            <td class="px-6 py-4 text-sm text-gray-700">
                                {{ $account->currency ?? '—' }}
                            </td>

                            {{-- Status Badge --}}
                            <td class="px-6 py-4">
                                @php
                                    $normalized = strtoupper($account->status ?? '');
                                @endphp

                                @switch($normalized)
                                    @case('1')
                                    @case('ACTIVE')
                                        <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">
                                            Active
                                        </span>
                                        @break

                                    @case('2')
                                    @case('DISABLED')
                                        <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700">
                                            Disabled
                                        </span>
                                        @break

                                    @default
                                        <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">
                                            {{ $account->status ?? 'Unknown' }}
                                        </span>
                                @endswitch
                            </td>

                            {{-- Actions --}}
                            <td class="px-6 py-4 text-right">

                                <form method="POST"
                                      action="{{ route('admin.accounts.destroy', $account) }}"
                                      class="inline"
                                      onsubmit="return confirm('Remove this account locally? This will not delete it from Meta.')">

                                    @csrf
                                    @method('DELETE')

                                    <button type="submit"
                                            class="text-red-500 hover:text-red-700 text-sm font-medium transition">
                                        Delete
                                    </button>
                                </form>

                            </td>

                        </tr>

                    @empty

                        {{-- Empty State --}}
                        <tr>
                            <td colspan="4" class="px-6 py-20 text-center">

                                <div class="text-6xl mb-6 text-gray-300">
                                    📊
                                </div>

                                <h3 class="text-lg font-semibold text-gray-700">
                                    No Ad Accounts Found
                                </h3>

                                <p class="text-gray-500 mt-2 max-w-md mx-auto">
                                    Click <strong>“Sync From Meta”</strong> to import your advertising accounts.
                                </p>

                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        {{-- Pagination --}}
        @if(method_exists($accounts, 'links'))
            <div class="px-6 py-4 bg-gray-50 border-t">
                {{ $accounts->links() }}
            </div>
        @endif

    </div>

</div>


{{-- ================= SYNC BUTTON HANDLER ================= --}}
<script>
document.getElementById('syncForm').addEventListener('submit', function () {

    const btn = document.getElementById('syncBtn');
    const spinner = document.getElementById('syncSpinner');
    const text = document.getElementById('syncText');

    btn.disabled = true;
    spinner.classList.remove('hidden');
    text.innerText = 'Syncing...';

    // Fallback safety after 15 seconds
    setTimeout(() => {
        if (btn.disabled) {
            btn.disabled = false;
            spinner.classList.add('hidden');
            text.innerText = 'Retry Sync';
        }
    }, 15000);

});
</script>

@endsection