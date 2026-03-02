<x-app-layout>

<div class="min-h-screen bg-gray-100">

    <div class="max-w-7xl mx-auto px-6 py-10 space-y-10">

        {{-- ALERT --}}
        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-6 py-4 rounded-xl shadow-sm">
                {{ session('success') }}
            </div>
        @endif


        {{-- HEADER --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">

            <div>
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center text-sm text-gray-500 hover:text-gray-900 transition mb-3">
                    ← Back to Dashboard
                </a>

                <h1 class="text-3xl font-bold text-gray-900">
                    FAQ Knowledge Base
                </h1>

                <p class="text-gray-500 mt-2">
                    Centralized AI training data management for automation workflows.
                </p>
            </div>

            <div class="flex gap-3">
                <a href="{{ route('admin.faq.template') }}"
                   class="px-5 py-2.5 bg-white border border-gray-300 rounded-xl text-sm font-medium hover:bg-gray-50 transition">
                    Download Template
                </a>

                <a href="{{ route('admin.faq.create') }}"
                   class="px-6 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-semibold shadow hover:bg-blue-700 transition">
                    + Add FAQ
                </a>
            </div>
        </div>


        {{-- KPI STATS --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm">
                <p class="text-sm text-gray-500">Total FAQs</p>
                <p class="text-2xl font-bold text-gray-900 mt-2">
                    {{ $faqs->total() }}
                </p>
            </div>

            <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm">
                <p class="text-sm text-gray-500">Active</p>
                <p class="text-2xl font-bold text-emerald-600 mt-2">
                    {{ \App\Models\Faq::where('is_active',1)->count() }}
                </p>
            </div>

            <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm">
                <p class="text-sm text-gray-500">Disabled</p>
                <p class="text-2xl font-bold text-red-500 mt-2">
                    {{ \App\Models\Faq::where('is_active',0)->count() }}
                </p>
            </div>

        </div>


        {{-- ENTERPRISE IMPORT MODULE --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8">

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">
                        Bulk Import
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Upload Excel (.xlsx) or CSV file to update FAQs at scale.
                    </p>
                </div>
            </div>

            <form id="importForm"
                  method="POST"
                  action="{{ route('admin.faq.import') }}"
                  enctype="multipart/form-data"
                  class="mt-6">

                @csrf

                <input type="file"
                       id="fileInput"
                       name="file"
                       accept=".xlsx,.csv"
                       class="hidden"
                       required>

                <div id="uploadArea"
                     class="border-2 border-dashed border-gray-300 rounded-2xl p-12 text-center cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition">

                    <div class="space-y-3">
                        <p class="font-medium text-gray-700">
                            Click or Drag & Drop file here
                        </p>

                        <p class="text-xs text-gray-400">
                            Supported formats: .xlsx, .csv
                        </p>

                        <p id="fileName" class="text-sm text-blue-600 hidden"></p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit"
                            id="uploadBtn"
                            class="px-8 py-3 bg-blue-600 text-white rounded-xl font-semibold shadow hover:bg-blue-700 transition disabled:opacity-50"
                            disabled>
                        Import File
                    </button>
                </div>

            </form>
        </div>


        {{-- SEARCH --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">

            <form method="GET"
                  action="{{ route('admin.faq.index') }}"
                  class="flex flex-col md:flex-row gap-4">

                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Search question or answer..."
                       class="flex-1 border border-gray-300 rounded-xl px-5 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">

                <button type="submit"
                        class="px-8 py-3 bg-gray-900 text-white font-semibold rounded-xl hover:bg-black transition">
                    Search
                </button>

            </form>

        </div>


        {{-- TABLE --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

            <table class="w-full text-sm">

                <thead class="bg-gray-50 border-b text-xs uppercase text-gray-500 tracking-wider">
                    <tr>
                        <th class="px-8 py-4 text-left">Question</th>
                        <th class="px-8 py-4 text-left w-40">Status</th>
                        <th class="px-8 py-4 text-right w-48">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">

                @forelse($faqs as $faq)
                    <tr class="hover:bg-gray-50 transition">

                        <td class="px-8 py-6">
                            <p class="font-semibold text-gray-900">
                                {{ $faq->question }}
                            </p>
                            <p class="text-gray-500 text-xs mt-2">
                                {{ \Illuminate\Support\Str::limit($faq->answer,150) }}
                            </p>
                        </td>

                        <td class="px-8 py-6">
                            @if($faq->is_active)
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">
                                    Active
                                </span>
                            @else
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-600">
                                    Disabled
                                </span>
                            @endif
                        </td>

                        <td class="px-8 py-6 text-right">
                            <div class="flex justify-end gap-3">

                                <a href="{{ route('admin.faq.edit',$faq->id) }}"
                                   class="px-4 py-2 border border-blue-600 text-blue-600 text-xs rounded-lg hover:bg-blue-600 hover:text-white transition">
                                    Edit
                                </a>

                                <form method="POST"
                                      action="{{ route('admin.faq.destroy',$faq->id) }}"
                                      onsubmit="return confirm('Delete this FAQ?')">
                                    @csrf
                                    @method('DELETE')

                                    <button class="px-4 py-2 border border-red-500 text-red-500 text-xs rounded-lg hover:bg-red-500 hover:text-white transition">
                                        Delete
                                    </button>
                                </form>

                            </div>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center py-20 text-gray-400">
                            No FAQs available.
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
</div>


{{-- JS ENHANCEMENT --}}
<script>
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('fileInput');
    const fileName = document.getElementById('fileName');
    const uploadBtn = document.getElementById('uploadBtn');

    uploadArea.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', function () {
        if (this.files.length > 0) {
            fileName.textContent = this.files[0].name;
            fileName.classList.remove('hidden');
            uploadBtn.disabled = false;
        }
    });

    uploadArea.addEventListener('dragover', e => {
        e.preventDefault();
        uploadArea.classList.add('border-blue-500','bg-blue-50');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('border-blue-500','bg-blue-50');
    });

    uploadArea.addEventListener('drop', e => {
        e.preventDefault();
        fileInput.files = e.dataTransfer.files;
        fileInput.dispatchEvent(new Event('change'));
    });
</script>

</x-app-layout>