<x-app-layout>

<div class="max-w-6xl mx-auto py-10 space-y-8">

    {{-- SUCCESS ALERT --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-xl shadow-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- HEADER --}}
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-6">

        <div>
            <h1 class="text-2xl font-bold text-gray-900">
                FAQ Knowledge Base
            </h1>
            <p class="text-gray-500 mt-1 text-sm">
                Manage AI-powered knowledge responses
            </p>
        </div>

        <div class="flex gap-3">
            <a href="{{ route('admin.faq.template') }}"
               class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 transition text-sm">
                Download Template
            </a>

            <a href="{{ route('admin.faq.create') }}"
               class="px-5 py-2 rounded-lg bg-blue-600 text-white shadow hover:bg-blue-700 transition text-sm">
                + Add FAQ
            </a>
        </div>
    </div>

    {{-- IMPORT SECTION --}}
    <div class="bg-white rounded-xl shadow border p-6">

        <h3 class="text-base font-semibold text-gray-800 mb-4">
            Import FAQs (Excel / CSV)
        </h3>

        <form method="POST"
              action="{{ route('admin.faq.import') }}"
              enctype="multipart/form-data">

            @csrf

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">

                {{-- FILE INPUT --}}
                <div class="md:col-span-3">
                    <input type="file"
                           name="file"
                           accept=".xlsx,.csv"
                           required
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none">
                </div>

                {{-- BUTTON --}}
                <div>
                    <button type="submit"
                            class="w-full bg-green-600 text-white py-2.5 rounded-lg shadow hover:bg-green-700 transition">
                        Upload File
                    </button>
                </div>

            </div>

        </form>
    </div>

    {{-- SEARCH --}}
    <div class="bg-white rounded-xl shadow border p-5">

        <form method="GET" action="{{ route('admin.faq.index') }}">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">

                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Search question or answer..."
                       class="md:col-span-4 border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 outline-none">

                <button type="submit"
                        class="bg-blue-600 text-white py-2.5 rounded-lg shadow hover:bg-blue-700 transition">
                    Search
                </button>

            </div>
        </form>

    </div>

    {{-- TABLE --}}
    <div class="bg-white rounded-xl shadow border overflow-hidden">

        <table class="min-w-full text-sm">

            <thead class="bg-gray-50 border-b text-gray-700 uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-6 py-4 text-left">Question</th>
                    <th class="px-6 py-4 text-left w-32">Status</th>
                    <th class="px-6 py-4 text-right w-40">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y">

                @forelse($faqs as $faq)
                    <tr class="hover:bg-gray-50">

                        <td class="px-6 py-5">
                            <div class="font-semibold text-gray-900">
                                {{ $faq->question }}
                            </div>
                            <div class="text-gray-500 text-sm mt-1">
                                {{ \Illuminate\Support\Str::limit($faq->answer, 130) }}
                            </div>
                        </td>

                        <td class="px-6 py-5">
                            @if($faq->is_active)
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">
                                    Active
                                </span>
                            @else
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-600">
                                    Disabled
                                </span>
                            @endif
                        </td>

                        <td class="px-6 py-5 text-right">
                            <div class="flex justify-end gap-2">

                                <a href="{{ route('admin.faq.edit', $faq->id) }}"
                                   class="px-3 py-1.5 text-xs border border-blue-600 text-blue-600 rounded-md hover:bg-blue-600 hover:text-white transition">
                                    Edit
                                </a>

                                <form action="{{ route('admin.faq.destroy', $faq->id) }}"
                                      method="POST"
                                      onsubmit="return confirm('Delete this FAQ?')">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit"
                                            class="px-3 py-1.5 text-xs border border-red-500 text-red-500 rounded-md hover:bg-red-500 hover:text-white transition">
                                        Delete
                                    </button>
                                </form>

                            </div>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center py-10 text-gray-400">
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