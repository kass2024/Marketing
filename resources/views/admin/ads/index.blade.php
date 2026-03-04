@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto py-10 px-6">

    <div class="bg-white p-8 rounded-2xl shadow">

        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900">
                Ads Management
            </h2>

            <a href="{{ route('admin.ads.create') }}"
               class="bg-blue-600 text-white px-5 py-2 rounded-xl hover:bg-blue-700 transition">
                + Create Ad
            </a>
        </div>

        @if(session('success'))
            <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b bg-gray-50">
                        <th class="py-3 px-2">Name</th>
                        <th class="px-2">Status</th>
                        <th class="px-2">Ad Set</th>
                        <th class="px-2">Creative</th>
                        <th class="px-2">Spend</th>
                        <th class="px-2 text-right">Actions</th>
                    </tr>
                </thead>

                <tbody>
                @forelse($ads as $ad)
                    <tr class="border-b hover:bg-gray-50">

                        <td class="py-3 px-2 font-medium">
                            {{ $ad->name }}
                        </td>

                        <td class="px-2">
                            <span class="px-3 py-1 text-sm rounded-full
                                {{ $ad->status == 'ACTIVE'
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-yellow-100 text-yellow-700' }}">
                                {{ $ad->status }}
                            </span>
                        </td>

                        <td class="px-2">
                            {{ $ad->adSet->name ?? '-' }}
                        </td>

                        <td class="px-2">
                            {{ $ad->creative->name ?? '-' }}
                        </td>

                        <td class="px-2">
                            ${{ number_format($ad->spend ?? 0, 2) }}
                        </td>

                        <td class="px-2 text-right space-x-4">

                            <a href="{{ route('admin.ads.edit', $ad->id) }}"
                               class="text-blue-600 hover:underline">
                                Edit
                            </a>

                            <form method="POST"
                                  action="{{ route('admin.ads.destroy', $ad->id) }}"
                                  class="inline">
                                @csrf
                                @method('DELETE')

                                <button class="text-red-500 hover:underline"
                                        onclick="return confirm('Delete this ad?')">
                                    Delete
                                </button>
                            </form>

                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6"
                            class="py-6 text-center text-gray-500">
                            No ads found.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

    </div>

</div>

@endsection