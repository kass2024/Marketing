@extends('layouts.app')

@section('content')
<div class="bg-white p-10 rounded-2xl shadow">

    <div class="flex justify-between mb-6">
        <h2 class="text-2xl font-bold">Ad Accounts</h2>

        <form method="POST" action="{{ route('admin.accounts.store') }}">
            @csrf
            <button class="bg-blue-600 text-white px-5 py-2 rounded-xl hover:bg-blue-700">
                Sync From Meta
            </button>
        </form>
    </div>

    @if(session('success'))
        <div class="mb-4 text-green-600">{{ session('success') }}</div>
    @endif

    <table class="w-full text-left border-collapse">
        <thead>
            <tr class="border-b">
                <th>Name</th>
                <th>Currency</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($accounts as $account)
            <tr class="border-b hover:bg-gray-50">
                <td class="py-3">{{ $account->name }}</td>
                <td>{{ $account->currency }}</td>
                <td>{{ $account->status }}</td>
                <td>
                    <form method="POST" action="{{ route('admin.accounts.destroy',$account) }}">
                        @csrf
                        @method('DELETE')
                        <button class="text-red-500">Delete</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

</div>
@endsection