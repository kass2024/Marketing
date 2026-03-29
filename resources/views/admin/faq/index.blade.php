@extends('layouts.admin')

@section('title', 'FAQ')

@section('content')

<div class="mx-auto max-w-6xl space-y-8">

        {{-- SUCCESS --}}
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-5 py-3 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        {{-- HEADER --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-xander-navy">
                    FAQ Knowledge Base
                </h1>

                <p class="text-sm text-gray-500 mt-1">
                    Manage automated AI responses.
                </p>
            </div>

            <div class="flex gap-3">
                <a href="{{ route('admin.faq.template') }}"
                   class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-100">
                    Download Template
                </a>

                <a href="{{ route('admin.faq.create') }}"
                   class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    + Add FAQ
                </a>
            </div>
        </div>


        {{-- ============================ --}}
        {{-- MODERN IMPORT SECTION --}}
        {{-- ============================ --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 space-y-5">

            <div>
                <h3 class="text-base font-semibold text-gray-900">
                    Bulk Import
                </h3>
                <p class="text-sm text-gray-500 mt-1">
                    Upload a .xlsx or .csv file to update your FAQs.
                </p>
            </div>

            {{-- Upload Row --}}
            <div class="flex items-center gap-4">

                <button id="selectFileBtn"
                        class="px-4 py-2 text-sm bg-gray-900 text-white rounded-lg hover:bg-black transition">
                    Select File
                </button>

                <span class="text-sm text-gray-500">
                    or drag and drop file here
                </span>

                <input type="file"
                       id="fileInput"
                       accept=".xlsx,.csv"
                       class="hidden">
            </div>

            {{-- Selected File Display --}}
            <div id="filePreview" class="hidden border border-gray-200 rounded-lg p-4 bg-gray-50">

                <div class="flex items-center justify-between">
                    <div>
                        <p id="fileName" class="text-sm font-medium text-gray-800"></p>
                        <p id="uploadStatus" class="text-xs text-gray-500 mt-1"></p>
                    </div>

                    <span id="uploadBadge"
                          class="text-xs px-3 py-1 rounded-full bg-blue-100 text-blue-700 hidden">
                        Uploading
                    </span>
                </div>

                {{-- Progress --}}
                <div class="mt-3 w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
                    <div id="progressBar"
                         class="bg-blue-600 h-1.5 rounded-full transition-all duration-300"
                         style="width:0%">
                    </div>
                </div>
            </div>

        </div>


        {{-- SEARCH --}}
        <div class="bg-white border border-gray-200 rounded-xl p-4">
            <form method="GET"
                  action="{{ route('admin.faq.index') }}"
                  class="flex gap-3">

                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Search FAQs..."
                       class="flex-1 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">

                <button type="submit"
                        class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Search
                </button>
            </form>
        </div>


        {{-- TABLE --}}
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">

            <table class="w-full text-sm">

                <thead class="bg-gray-50 border-b text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-4 text-left">Question</th>
                        <th class="px-6 py-4 text-left w-32">Status</th>
                        <th class="px-6 py-4 text-right w-40">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">

                    @forelse($faqs as $faq)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-5">
                                <div class="font-medium text-gray-900">
                                    {{ $faq->question }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    {{ \Illuminate\Support\Str::limit($faq->answer, 120) }}
                                </div>
                            </td>

                            <td class="px-6 py-5">
                                @if($faq->is_active)
                                    <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">
                                        Active
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-gray-200 text-gray-600">
                                        Disabled
                                    </span>
                                @endif
                            </td>

                            <td class="px-6 py-5 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.faq.edit', $faq->id) }}"
                                       class="px-3 py-1.5 text-xs border border-gray-300 rounded-md hover:bg-gray-100">
                                        Edit
                                    </a>

                                    <form action="{{ route('admin.faq.destroy', $faq->id) }}"
                                          method="POST"
                                          onsubmit="return confirm('Delete this FAQ?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="px-3 py-1.5 text-xs border border-red-300 text-red-600 rounded-md hover:bg-red-50">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-12 text-gray-400 text-sm">
                                No FAQs found.
                            </td>
                        </tr>
                    @endforelse

                </tbody>
            </table>
        </div>

        <div>
            {{ $faqs->links() }}
        </div>

</div>


{{-- ======================= --}}
{{-- CLEAN MODERN JS --}}
{{-- ======================= --}}
<script>
document.addEventListener('DOMContentLoaded', function () {

    const btn = document.getElementById('selectFileBtn');
    const input = document.getElementById('fileInput');
    const preview = document.getElementById('filePreview');
    const name = document.getElementById('fileName');
    const status = document.getElementById('uploadStatus');
    const badge = document.getElementById('uploadBadge');
    const bar = document.getElementById('progressBar');

    btn.addEventListener('click', () => input.click());

    input.addEventListener('change', function () {
        if (!this.files.length) return;
        upload(this.files[0]);
    });

    function upload(file) {

        preview.classList.remove('hidden');
        name.textContent = file.name;
        status.textContent = "Uploading...";
        badge.classList.remove('hidden');
        badge.textContent = "Uploading";
        bar.style.width = "0%";

        const formData = new FormData();
        formData.append('file', file);
        formData.append('_token', '{{ csrf_token() }}');

        const xhr = new XMLHttpRequest();
        xhr.open('POST', "{{ route('admin.faq.import') }}");

        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percent = (e.loaded / e.total) * 100;
                bar.style.width = percent + "%";
            }
        };

        xhr.onload = function () {
            if (xhr.status === 200) {
                badge.textContent = "Completed";
                badge.classList.remove('bg-blue-100','text-blue-700');
                badge.classList.add('bg-green-100','text-green-700');
                status.textContent = "Import successful";
                bar.style.width = "100%";
                setTimeout(() => location.reload(), 1200);
            } else {
                badge.textContent = "Failed";
                badge.classList.remove('bg-blue-100','text-blue-700');
                badge.classList.add('bg-red-100','text-red-700');
                status.textContent = "Upload failed";
            }
        };

        xhr.send(formData);
    }
});
</script>

@endsection