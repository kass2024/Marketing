<x-app-layout>

<div class="max-w-6xl mx-auto py-12">

    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">FAQ Knowledge Base</h2>

        <a href="{{ route('admin.faq.create') }}"
           class="bg-blue-600 text-white px-4 py-2 rounded-xl">
            + Add FAQ
        </a>
    </div>

    <div class="bg-white shadow rounded-2xl border p-6">

        @foreach($faqs as $faq)
            <div class="border-b py-4">
                <h4 class="font-semibold">
                    {{ $faq->question }}
                </h4>
                <p class="text-gray-600 mt-2">
                    {{ $faq->answer }}
                </p>
            </div>
        @endforeach

        <div class="mt-6">
            {{ $faqs->links() }}
        </div>

    </div>

</div>

</x-app-layout>