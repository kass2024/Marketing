<x-app-layout>

<div class="max-w-7xl mx-auto px-6 py-10 space-y-8">

    {{-- SUCCESS MESSAGE --}}
    @if(session('success'))
        <div class="p-4 bg-green-100 text-green-700 rounded-xl">
            {{ session('success') }}
        </div>
    @endif

    {{-- HEADER --}}
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">
                FAQ Knowledge Base
            </h1>
            <p class="text-gray-500 mt-1">
                Manage AI-powered knowledge responses
            </p>
        </div>

        <a href="{{ route('admin.faq.create') }}"
           class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl shadow">
            + Add FAQ
        </a>
    </div>

    {{-- STATS --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <div class="bg-white p-6 rounded-2xl shadow border">
            <p class="text-gray-500 text-sm">Total FAQs</p>
            <p class="text-3xl font-bold mt-2">
                {{ $faqs->total() }}
            </p>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow border">
            <p class="text-gray-500 text-sm">With Attachments</p>
            <p class="text-3xl font-bold mt-2">
                {{ $faqs->getCollection()->filter(fn($f) => $f->attachments->count())->count() }}
            </p>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow border">
            <p class="text-gray-500 text-sm">Active</p>
            <p class="text-3xl font-bold mt-2">
                {{ $faqs->getCollection()->where('is_active', true)->count() }}
            </p>
        </div>

    </div>

    {{-- SEARCH --}}
    <div class="bg-white p-6 rounded-2xl shadow border">
        <form method="GET">
            <input type="text"
                   name="search"
                   value="{{ request('search') }}"
                   placeholder="Search question..."
                   class="w-full border rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none">
        </form>
    </div>

    {{-- TABLE --}}
    <div class="bg-white rounded-2xl shadow border overflow-hidden">

        <table class="min-w-full text-sm text-left">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-4">Question</th>
                    <th class="px-6 py-4">Attachments</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y">

                @forelse($faqs as $faq)
                    <tr class="hover:bg-gray-50 transition">

                        <td class="px-6 py-4">
                            <p class="font-semibold text-gray-900">
                                {{ $faq->question }}
                            </p>
                            <p class="text-gray-500 text-xs mt-1">
                                {{ \Illuminate\Support\Str::limit($faq->answer, 120) }}
                            </p>
                        </td>

                        <td class="px-6 py-4">
                            @if($faq->attachments->count())
                                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-semibold">
                                    {{ $faq->attachments->count() }} File(s)
                                </span>
                            @else
                                <span class="text-gray-400 text-xs">
                                    None
                                </span>
                            @endif
                        </td>

                        <td class="px-6 py-4">
                            @if($faq->is_active)
                                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-semibold">
                                    Active
                                </span>
                            @else
                                <span class="bg-red-100 text-red-600 px-3 py-1 rounded-full text-xs font-semibold">
                                    Disabled
                                </span>
                            @endif
                        </td>

                        <td class="px-6 py-4 text-right space-x-3">

                            <a href="{{ route('admin.faq.edit', $faq->id) }}"
                               class="text-blue-600 hover:underline text-sm">
                                Edit
                            </a>

                            <form action="{{ route('admin.faq.destroy', $faq->id) }}"
                                  method="POST"
                                  class="inline-block"
                                  onsubmit="return confirm('Delete this FAQ?')">
                                @csrf
                                @method('DELETE')

                                <button type="submit"
                                        class="text-red-500 hover:underline text-sm">
                                    Delete
                                </button>
                            </form>

                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-12 text-gray-400">
                            No FAQs found.
                        </td>
                    </tr>
                @endforelse

            </tbody>
        </table>

    </div>

    {{-- PAGINATION --}}
    <div>
        {{ $faqs->links() }}
    </div>

</div>

</x-app-layout>