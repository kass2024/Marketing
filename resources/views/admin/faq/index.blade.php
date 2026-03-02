<x-app-layout>

<div class="max-w-7xl mx-auto px-12 py-12 space-y-10">

    {{-- SUCCESS ALERT --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-xl shadow-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- HEADER --}}
    <div class="flex justify-between items-center">

        <div>
            <h1 class="text-3xl font-bold text-gray-900">
                FAQ Knowledge Base
            </h1>
            <p class="text-gray-500 mt-2">
                Manage AI-powered knowledge responses
            </p>
        </div>

        <div class="flex gap-4">
            <a href="{{ route('admin.faq.template') }}"
               class="px-5 py-3 rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-100 transition">
                Download Template
            </a>

            <a href="{{ route('admin.faq.create') }}"
               class="px-6 py-3 rounded-xl bg-blue-600 text-white shadow hover:bg-blue-700 transition">
                + Add FAQ
            </a>
        </div>
    </div>

  {{-- IMPORT SECTION --}}
<div class="bg-white rounded-2xl shadow-sm border p-8">

    <h3 class="text-lg font-semibold text-gray-800 mb-6">
        Import FAQs (Excel / CSV)
    </h3>

    <form method="POST"
          action="{{ route('admin.faq.import') }}"
          enctype="multipart/form-data"
          class="w-full">

        @csrf

        <div class="flex flex-col lg:flex-row gap-4">

            {{-- FILE INPUT --}}
            <div class="flex-1">
                <input type="file"
                       name="file"
                       accept=".xlsx,.csv"
                       required
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none">
            </div>

            {{-- UPLOAD BUTTON --}}
            <div class="lg:w-48">
                <button type="submit"
                        class="w-full px-6 py-3 bg-green-600 text-white rounded-xl shadow hover:bg-green-700 transition">
                    Upload File
                </button>
            </div>

        </div>

    </form>
</div>
    {{-- SEARCH --}}
    <div class="bg-white rounded-2xl shadow-sm border p-6">

        <form method="GET" action="{{ route('admin.faq.index') }}">
            <div class="flex gap-4">

                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Search question or answer..."
                       class="flex-1 border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none">

                <button type="submit"
                        class="px-6 py-3 bg-blue-600 text-white rounded-xl shadow hover:bg-blue-700 transition">
                    Search
                </button>

            </div>
        </form>

    </div>

    {{-- TABLE --}}
    <div class="bg-white rounded-2xl shadow-sm border overflow-hidden">

        <table class="min-w-full text-sm">

            <thead class="bg-gray-50 border-b text-gray-700 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-8 py-4 text-left">Question</th>
                    <th class="px-8 py-4 text-left w-40">Status</th>
                    <th class="px-8 py-4 text-right w-48">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y">

                @forelse($faqs as $faq)

                    <tr class="hover:bg-gray-50 transition">

                        {{-- QUESTION --}}
                        <td class="px-8 py-6">

                            <div class="font-semibold text-gray-900">
                                {{ $faq->question }}
                            </div>

                            <div class="text-gray-500 text-sm mt-2">
                                {{ \Illuminate\Support\Str::limit($faq->answer, 140) }}
                            </div>

                        </td>

                        {{-- STATUS --}}
                        <td class="px-8 py-6">

                            @if($faq->is_active)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-600">
                                    Disabled
                                </span>
                            @endif

                        </td>

                        {{-- ACTIONS --}}
                        <td class="px-8 py-6 text-right">

                            <div class="flex justify-end gap-3">

                                <a href="{{ route('admin.faq.edit', $faq->id) }}"
                                   class="px-4 py-2 text-sm border border-blue-600 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition">
                                    Edit
                                </a>

                                <form action="{{ route('admin.faq.destroy', $faq->id) }}"
                                      method="POST"
                                      onsubmit="return confirm('Delete this FAQ?')">

                                    @csrf
                                    @method('DELETE')

                                    <button type="submit"
                                            class="px-4 py-2 text-sm border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition">
                                        Delete
                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="3" class="text-center py-12 text-gray-400">
                            No FAQs found.
                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

    {{-- PAGINATION --}}
    <div>
        {{ $faqs->appends(request()->query())->links() }}
    </div>

</div>

</x-app-layout>