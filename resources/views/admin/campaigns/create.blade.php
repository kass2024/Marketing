@extends('layouts.app')

@section('content')
<div class="bg-white p-10 rounded-2xl shadow max-w-3xl mx-auto">

    <h2 class="text-2xl font-bold mb-6">Create Campaign</h2>

    <form method="POST" action="{{ route('admin.campaigns.store') }}">
    @csrf

    {{-- Campaign Name --}}
    <div class="mb-5">
        <label class="block font-medium mb-2">Campaign Name</label>
        <input type="text"
               name="name"
               class="w-full border rounded-xl px-4 py-3"
               required>
    </div>

    {{-- Objective --}}
    <div class="mb-5">
        <label class="block font-medium mb-2">Objective</label>
        <select name="objective"
                class="w-full border rounded-xl px-4 py-3"
                required>
            <option value="MESSAGES">WhatsApp Messages</option>
            <option value="TRAFFIC">Traffic</option>
            <option value="LEADS">Lead Generation</option>
            <option value="ENGAGEMENT">Engagement</option>
        </select>
    </div>

    {{-- Daily Budget --}}
    <div class="mb-5">
        <label class="block font-medium mb-2">Daily Budget (CAD)</label>
        <input type="number"
               name="daily_budget"
               class="w-full border rounded-xl px-4 py-3"
               required>
    </div>

    <div class="flex justify-end mt-6">
        <button type="submit"
            class="bg-blue-600 text-white px-6 py-3 rounded-xl shadow hover:bg-blue-700">
            Continue →
        </button>
    </div>

    </form>

</div>
@endsection