<x-app-layout>
<div class="min-h-screen bg-gray-50">

    <div class="max-w-7xl mx-auto px-6 py-10 space-y-10">

        {{-- SUCCESS MESSAGE --}}
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-xl shadow-sm">
                {{ session('success') }}
            </div>
        @endif

        {{-- HEADER --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">

            <div>
                <a href="{{ route('dashboard') }}"
                   class="text-sm text-gray-500 hover:text-gray-900 transition">
                    ← Back to Dashboard
                </a>

                <h1 class="text-3xl font-bold text-gray-900 mt-3">
                    FAQ Knowledge Base
                </h1>

                <p class="text-gray-500 mt-2">
                    Manage your AI-powered responses and improve automation quality.
                </p>
            </div>

            <div class="flex gap-3">
                <a href="{{ route('admin.faq.template') }}"
                   class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-100 transition">
                    Download Template
                </a>

                <a href="{{ route('admin.faq.create') }}"
                   class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold shadow hover:bg-blue-700 transition">
                    + Add FAQ
                </a>
            </div>
        </div>

        {{-- ========================= --}}
        {{-- 🔥 PRODUCTION IMPORT CARD --}}
        {{-- ========================= --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">

            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">
                        Bulk Import FAQs
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Upload Excel (.xlsx) or CSV file to instantly update your knowledge base.
                    </p>
                </div>
            </div>

            <div class="mt-6">

                <div id="uploadBox"
                     class="relative border-2 border-dashed border-gray-300 rounded-2xl p-10 text-center cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition">

                    <div id="uploadContent">
                        <p class="text-sm text-gray-600">
                            Click to upload or drag & drop
                        </p>
                        <p class="text-xs text-gray-400 mt-2">
                            .xlsx or .csv
                        </p>
                    </div>

                    {{-- Progress --}}
                    <div id="progressWrapper" class="hidden mt-6">
                        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div id="progressBar"
                                 class="bg-blue-600 h-3 rounded-full transition-all duration-300"
                                 style="width:0%">
                            </div>
                        </div>

                        <p id="progressText"
                           class="text-xs text-gray-500 mt-2">
                            Uploading...
                        </p>
                    </div>

                    <input type="file"
                           id="fileInput"
                           accept=".xlsx,.csv"
                           class="hidden">
                </div>

            </div>
        </div>


        {{-- SEARCH --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">

            <form method="GET"
                  action="{{ route('admin.faq.index') }}"
                  class="flex flex-col md:flex-row gap-4">

                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Search question or answer..."
                       class="flex-1 border border-gray-300 rounded-xl px-5 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">

                <button type="submit"
                        class="px-8 py-3 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 transition">
                    Search
                </button>

            </form>
        </div>


        {{-- TABLE --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">

            <table class="w-full text-sm">

                <thead class="bg-gray-50 border-b text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-8 py-5 text-left">Question</th>
                        <th class="px-8 py-5 text-left w-40">Status</th>
                        <th class="px-8 py-5 text-right w-48">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">

                    @forelse($faqs as $faq)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-8 py-6">
                                <div class="font-semibold text-gray-900">
                                    {{ $faq->question }}
                                </div>
                                <div class="text-gray-500 text-xs mt-2">
                                    {{ \Illuminate\Support\Str::limit($faq->answer, 140) }}
                                </div>
                            </td>

                            <td class="px-8 py-6">
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

                            <td class="px-8 py-6 text-right">
                                <div class="flex justify-end gap-3">
                                    <a href="{{ route('admin.faq.edit', $faq->id) }}"
                                       class="px-4 py-2 text-xs font-medium border border-blue-600 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition">
                                        Edit
                                    </a>

                                    <form action="{{ route('admin.faq.destroy', $faq->id) }}"
                                          method="POST"
                                          onsubmit="return confirm('Delete this FAQ?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="px-4 py-2 text-xs font-medium border border-red-500 text-red-500 rounded-lg hover:bg-red-500 hover:text-white transition">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-16 text-gray-400">
                                No FAQs found.
                            </td>
                        </tr>
                    @endforelse

                </tbody>
            </table>
        </div>

        <div>
            {{ $faqs->appends(request()->query())->links() }}
        </div>

    </div>
</div>


{{-- ========================= --}}
{{-- 🚀 AUTO UPLOAD SCRIPT --}}
{{-- ========================= --}}
<script>
document.addEventListener('DOMContentLoaded', function () {

    const uploadBox = document.getElementById('uploadBox');
    const fileInput = document.getElementById('fileInput');
    const progressWrapper = document.getElementById('progressWrapper');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const uploadContent = document.getElementById('uploadContent');

    uploadBox.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', function () {
        if (!this.files.length) return;
        uploadFile(this.files[0]);
    });

    function uploadFile(file) {

        const formData = new FormData();
        formData.append('file', file);
        formData.append('_token', '{{ csrf_token() }}');

        uploadContent.innerHTML = `
            <p class="text-sm font-medium text-gray-800">${file.name}</p>
        `;

        progressWrapper.classList.remove('hidden');
        progressBar.style.width = '0%';
        progressText.innerText = 'Uploading...';

        const xhr = new XMLHttpRequest();
        xhr.open('POST', "{{ route('admin.faq.import') }}", true);

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                let percent = (e.loaded / e.total) * 100;
                progressBar.style.width = percent + '%';
            }
        });

        xhr.onload = function () {
            if (xhr.status === 200) {
                progressBar.style.width = '100%';
                progressText.innerText = 'Processing...';

                setTimeout(() => {
                    progressText.innerText = 'Upload complete ✓';
                    setTimeout(() => location.reload(), 1200);
                }, 800);
            } else {
                progressText.innerText = 'Upload failed. Please try again.';
            }
        };

        xhr.send(formData);
    }

});
</script>

</x-app-layout>