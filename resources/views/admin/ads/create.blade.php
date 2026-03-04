@extends('layouts.app')

@section('content')

<div class="bg-white p-8 rounded-2xl shadow max-w-3xl">

    <h2 class="text-2xl font-bold mb-6">Create Ad</h2>

    <form method="POST" action="{{ route('admin.ads.store') }}">
        @csrf

        <div class="mb-4">
            <label class="block mb-2 font-medium">Ad Name</label>
            <input type="text" name="name"
                   class="w-full border rounded px-3 py-2"
                   required>
        </div>

        <div class="mb-4">
            <label class="block mb-2 font-medium">Ad Set</label>
            <select name="adset_id"
                    class="w-full border rounded px-3 py-2"
                    required>
                @foreach($adsets as $adset)
                    <option value="{{ $adset->id }}">
                        {{ $adset->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="mb-6">
            <label class="block mb-2 font-medium">Creative</label>
            <select name="creative_id"
                    class="w-full border rounded px-3 py-2">
                <option value="">None</option>
                @foreach($creatives as $creative)
                    <option value="{{ $creative->id }}">
                        {{ $creative->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <button class="bg-blue-600 text-white px-6 py-2 rounded-xl hover:bg-blue-700">
            Save Ad
        </button>

    </form>

</div>

@endsection