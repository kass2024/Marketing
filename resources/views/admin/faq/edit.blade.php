@extends('layouts.admin')

@section('title', 'Edit FAQ')

@section('content')

<div class="mx-auto max-w-3xl space-y-8">

    {{-- PAGE TITLE --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900">
            Edit FAQ
        </h1>
        <p class="text-gray-500 mt-1">
            Update knowledge base entry
        </p>
    </div>

    {{-- VALIDATION ERRORS --}}
    @if ($errors->any())
        <div class="bg-red-100 text-red-700 p-4 rounded-xl">
            <ul class="list-disc list-inside text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- FORM --}}
    <div class="bg-white p-6 rounded-2xl shadow border">

        <form method="POST"
              action="{{ route('admin.faq.update', $faq->id) }}"
              enctype="multipart/form-data"
              class="space-y-6">

            @csrf
            @method('PUT')

            {{-- QUESTION --}}
            <div>
                <label class="block mb-2 font-semibold text-gray-700">
                    Question
                </label>

                <input type="text"
                       name="question"
                       value="{{ old('question', $faq->question) }}"
                       required
                       class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-blue-500 outline-none">
            </div>

            {{-- ANSWER --}}
            <div>
                <label class="block mb-2 font-semibold text-gray-700">
                    Answer
                </label>

                <textarea name="answer"
                          rows="6"
                          required
                          class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-blue-500 outline-none">{{ old('answer', $faq->answer) }}</textarea>
            </div>

            {{-- ACTIVE STATUS --}}
            <div>
                <label class="flex items-center gap-3 text-gray-700">
                    <input type="checkbox"
                           name="is_active"
                           value="1"
                           {{ $faq->is_active ? 'checked' : '' }}
                           class="rounded">
                    Active
                </label>
            </div>

            {{-- ATTACHMENT --}}
            <div>
                <label class="block mb-2 font-semibold text-gray-700">
                    Replace / Add Attachment
                </label>

                <input type="file"
                       name="attachment"
                       class="border border-gray-300 p-3 rounded-xl w-full">
            </div>

            {{-- ACTION BUTTONS --}}
            <div class="flex justify-between items-center">

                <a href="{{ route('admin.faq.index') }}"
                   class="text-gray-600 hover:underline">
                    ← Back to FAQs
                </a>

                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-xl shadow">
                    Update FAQ
                </button>

            </div>

        </form>

    </div>

</div>

@endsection