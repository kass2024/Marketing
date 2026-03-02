<x-app-layout>

<div class="w-full px-10 py-10 space-y-8">

    {{-- SUCCESS ALERT --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-lg">
            {{ session('success') }}
        </div>
    @endif


    {{-- HEADER --}}
    <div class="flex justify-between items-center">

        <div>
            <h1 class="text-2xl font-semibold text-gray-900">
                FAQ Knowledge Base
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Manage AI-powered knowledge responses
            </p>
        </div>

        <div class="flex gap-3">
            <a href="{{ route('admin.faq.template') }}"
               class="px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-100 transition">
                Download Template
            </a>

            <a href="{{ route('admin.faq.create') }}"
               class="px-5 py-2 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700 transition">
                + Add FAQ
            </a>
        </div>
    </div>


    {{-- IMPORT SECTION --}}
    <div class="bg-white border rounded-lg p-6">

        <h3 class="text-sm font-semibold text-gray-800 mb-4">
            Import FAQs (Excel / CSV)
        </h3>

        <form method="POST"
              action="{{ route('admin.faq.import') }}"
              enctype="multipart/form-data"
              class="flex items-center gap-4">

            @csrf

            {{-- FILE INPUT --}}
            <input type="file"
                   name="file"
                   accept=".xlsx,.csv"
                   required
                   class="flex-1 border border-gray-300 rounded-md px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">

            {{-- UPLOAD BUTTON --}}
            <button type="submit"
                    class="px-6 py-2 bg-green-600 text-white rounded-md text-sm hover:bg-green-700 transition whitespace-nowrap">
                Upload File
            </button>

        </form>

    </div>


    {{-- SEARCH --}}
    <div class="bg-white border rounded-lg p-5">

        <form method="GET" action="{{ route('admin.faq.index') }}"
              class="flex gap-4">

            <input type="text"
                   name="search"
                   value="{{ request('search') }}"
                   placeholder="Search question or answer..."
                   class="flex-1 border border-gray-300 rounded-md px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">

            <button type="submit"
                    class="px-6 py-2 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700 transition">
                Search
            </button>

        </form>

    </div>


    {{-- TABLE --}}
    <div class="bg-white border rounded-lg overflow-hidden">

        <table class="w-full text-sm">

            <thead class="bg-gray-50 border-b text-xs uppercase tracking-wide text-gray-600">
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
                            <div class="font-medium text-gray-900">
                                {{ $faq->question }}
                            </div>
                            <div class="text-gray-500 text-xs mt-1">
                                {{ \Illuminate\Support\Str::limit($faq->answer, 120) }}
                            </div>
                        </td>

                        <td class="px-6 py-5">
                            @if($faq->is_active)
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-700">
                                    Active
                                </span>
                            @else
                                <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-600">
                                    Disabled
                                </span>
                            @endif
                        </td>

                        <td class="px-6 py-5 text-right">
                            <div class="flex justify-end gap-2">

                                <a href="{{ route('admin.faq.edit', $faq->id) }}"
                                   class="px-3 py-1 text-xs border border-blue-600 text-blue-600 rounded hover:bg-blue-600 hover:text-white transition">
                                    Edit
                                </a>

                                <form action="{{ route('admin.faq.destroy', $faq->id) }}"
                                      method="POST"
                                      onsubmit="return confirm('Delete this FAQ?')">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit"
                                            class="px-3 py-1 text-xs border border-red-500 text-red-500 rounded hover:bg-red-500 hover:text-white transition">
                                        Delete
                                    </button>
                                </form>

                            </div>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center py-8 text-gray-400">
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