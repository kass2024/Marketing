@extends('layouts.app')

@section('content')

<div class="space-y-8">

    {{-- Page Header --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Ad Accounts</h1>
            <p class="text-gray-500 mt-1">
                Manage and sync your Meta advertising accounts
            </p>
        </div>

        <form method="POST" action="{{ route('admin.accounts.store') }}">
            @csrf
            <button type="submit"
                onclick="this.innerHTML='⏳ Syncing...'; this.disabled=true;"
                class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-xl shadow hover:bg-blue-700 transition">
                🔄 Sync From Meta
            </button>
        </form>
    </div>


    {{-- Alerts --}}
    @if(session('success'))
        <div class="p-4 bg-green-100 border border-green-200 text-green-700 rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->has('meta'))
        <div class="p-4 bg-red-100 border border-red-200 text-red-700 rounded-xl">
            {{ $errors->first('meta') }}
        </div>
    @endif


    {{-- Accounts Card --}}
    <div class="bg-white rounded-2xl shadow overflow-hidden">

        <div class="overflow-x-auto">

            <table class="min-w-full divide-y divide-gray-200">

                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Account Name
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

                            {{-- Name --}}
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
                                {{ $account->currency ?? '-' }}
                            </td>

                            {{-- Status Badge --}}
                            <td class="px-6 py-4">
                                @php
                                    $status = $account->status;
                                @endphp

                                @if($status == 1 || $status == 'ACTIVE')
                                    <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">
                                        Active
                                    </span>
                                @elseif($status == 2 || $status == 'DISABLED')
                                    <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700">
                                        Disabled
                                    </span>
                                @else
                                    <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">
                                        {{ $status ?? 'Unknown' }}
                                    </span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="px-6 py-4 text-right">

                                <form method="POST"
                                      action="{{ route('admin.accounts.destroy', $account) }}"
                                      onsubmit="return confirm('Remove this account locally?')"
                                      class="inline">

                                    @csrf
                                    @method('DELETE')

                                    <button class="text-red-500 hover:text-red-700 text-sm font-medium transition">
                                        Delete
                                    </button>
                                </form>

                            </td>

                        </tr>

                    @empty

                        {{-- Empty State --}}
                        <tr>
                            <td colspan="4" class="px-6 py-16 text-center">

                                <div class="text-gray-400 text-6xl mb-4">
                                    📊
                                </div>

                                <h3 class="text-lg font-semibold text-gray-700">
                                    No Ad Accounts Found
                                </h3>

                                <p class="text-gray-500 mt-2">
                                    Click "Sync From Meta" to import your advertising accounts.
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

@endsection