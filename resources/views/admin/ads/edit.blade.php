@extends('layouts.app')

@section('content')

<div class="bg-white p-8 rounded-2xl shadow max-w-3xl">

    <h2 class="text-2xl font-bold mb-6">Edit Ad</h2>

    <form method="POST" action="{{ route('admin.ads.update', $ad->id) }}">
        @csrf
        @method('PUT')

        <div class="mb-4">
            <label class="block mb-2 font-medium">Ad Name</label>
            <input type="text"
                   name="name"
                   value="{{ $ad->name }}"
                   class="w-full border rounded px-3 py-2"
                   required>
        </div>

        <div class="mb-6">
            <label class="block mb-2 font-medium">Status</label>
            <select name="status"
                    class="w-full border rounded px-3 py-2">
                <option value="ACTIVE" {{ $ad->status == 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
                <option value="PAUSED" {{ $ad->status == 'PAUSED' ? 'selected' : '' }}>PAUSED</option>
            </select>
        </div>

        <button class="bg-blue-600 text-white px-6 py-2 rounded-xl hover:bg-blue-700">
            Update Ad
        </button>

    </form>

</div>

@endsection