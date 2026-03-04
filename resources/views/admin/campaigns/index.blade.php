@extends('layouts.app')

@section('content')
<div class="bg-white p-8 rounded-2xl shadow">

    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Campaigns</h2>

        <a href="{{ route('admin.campaigns.create') }}"
           class="bg-blue-600 text-white px-5 py-2 rounded-xl shadow hover:bg-blue-700">
            + New Campaign
        </a>
    </div>

    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="border-b">
                <th class="py-3">Name</th>
                <th>Objective</th>
                <th>Budget</th>
                <th>Status</th>
                <th>Created</th>
                <th></th>
            </tr>
        </thead>

        <tbody>
        @foreach($campaigns as $campaign)
            <tr class="border-b hover:bg-gray-50">
                <td class="py-3 font-medium">{{ $campaign->name }}</td>
                <td>{{ $campaign->objective }}</td>
                <td>${{ $campaign->daily_budget / 100 }}</td>
                <td>
                    <span class="px-3 py-1 text-sm rounded-full
                        {{ $campaign->status == 'ACTIVE' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                        {{ $campaign->status }}
                    </span>
                </td>
                <td>{{ $campaign->created_at->format('d M Y') }}</td>
                <td>
                    <a href="{{ route('admin.campaigns.edit', $campaign->id) }}"
                       class="text-blue-600 hover:underline">Edit</a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

</div>
@endsection