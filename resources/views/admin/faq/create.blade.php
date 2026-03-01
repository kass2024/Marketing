<x-app-layout>

<div class="max-w-4xl mx-auto py-12">

    <div class="bg-white p-8 rounded-2xl shadow border">

        <h2 class="text-2xl font-bold mb-6">Create FAQ</h2>

        <form method="POST"
              action="{{ route('admin.faq.store') }}"
              enctype="multipart/form-data"
              class="space-y-6">

            @csrf

            <div>
                <label class="block font-semibold mb-2">
                    Question
                </label>
                <input type="text"
                       name="question"
                       class="w-full border rounded-xl px-4 py-3"
                       required>
            </div>

            <div>
                <label class="block font-semibold mb-2">
                    Answer
                </label>
                <textarea name="answer"
                          rows="6"
                          class="w-full border rounded-xl px-4 py-3"
                          required></textarea>
            </div>

            <div>
                <label class="block font-semibold mb-2">
                    Optional Attachment
                </label>
                <input type="file"
                       name="attachment"
                       class="w-full border rounded-xl px-4 py-3">
            </div>

            <button type="submit"
                    class="bg-blue-600 text-white px-6 py-3 rounded-xl shadow hover:bg-blue-700">
                Save FAQ
            </button>

        </form>

    </div>

</div>

</x-app-layout>