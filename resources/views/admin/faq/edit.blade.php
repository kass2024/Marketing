@extends('layouts.app')

@section('content')

<div class="max-w-3xl mx-auto py-10">

    <h1 class="text-2xl font-bold mb-6">
        Edit FAQ
    </h1>

    <form method="POST"
          action="{{ route('admin.faq.update', $faq->id) }}"
          enctype="multipart/form-data">

        @csrf
        @method('PUT')

        <div class="mb-4">
            <label class="block mb-2 font-semibold">
                Question
            </label>

            <input type="text"
                   name="question"
                   value="{{ old('question', $faq->question) }}"
                   class="w-full border rounded p-3">
        </div>

        <div class="mb-4">
            <label class="block mb-2 font-semibold">
                Answer
            </label>

            <textarea name="answer"
                      rows="5"
                      class="w-full border rounded p-3">{{ old('answer', $faq->answer) }}</textarea>
        </div>

        <div class="mb-4">
            <label class="flex items-center gap-2">
                <input type="checkbox"
                       name="is_active"
                       {{ $faq->is_active ? 'checked' : '' }}>
                Active
            </label>
        </div>

        <div class="mb-4">
            <label class="block mb-2 font-semibold">
                Replace / Add Attachment
            </label>

            <input type="file"
                   name="attachment"
                   class="border p-2 rounded w-full">
        </div>

        <button class="bg-blue-600 text-white px-6 py-3 rounded-xl">
            Update FAQ
        </button>

    </form>

</div>

@endsection