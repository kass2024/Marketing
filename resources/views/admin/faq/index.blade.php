<x-app-layout>

<div class="container py-5">

    {{-- SUCCESS ALERT --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- HEADER --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">FAQ Knowledge Base</h2>
            <p class="text-muted mb-0">Manage AI-powered knowledge responses</p>
        </div>

        <div>
            <a href="{{ route('admin.faq.template') }}" class="btn btn-outline-secondary me-2">
                Download Template
            </a>

            <a href="{{ route('admin.faq.create') }}" class="btn btn-primary">
                + Add FAQ
            </a>
        </div>
    </div>

    {{-- IMPORT EXCEL --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Import FAQs (Excel / CSV)</h5>

            <form method="POST"
                  action="{{ route('admin.faq.import') }}"
                  enctype="multipart/form-data"
                  class="row g-3 align-items-center">

                @csrf

                <div class="col-md-9">
                    <input type="file"
                           name="file"
                           accept=".xlsx,.csv"
                           class="form-control"
                           required>
                </div>

                <div class="col-md-3">
                    <button type="submit" class="btn btn-success w-100">
                        Upload File
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- SEARCH --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.faq.index') }}">
                <div class="input-group">
                    <input type="text"
                           name="search"
                           value="{{ request('search') }}"
                           class="form-control"
                           placeholder="Search question or answer...">

                    <button class="btn btn-outline-primary" type="submit">
                        Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- FAQ TABLE --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">
                        <tr>
                            <th>Question</th>
                            <th width="150">Status</th>
                            <th width="180" class="text-end">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($faqs as $faq)
                            <tr>

                                <td>
                                    <div class="fw-semibold">
                                        {{ $faq->question }}
                                    </div>
                                    <div class="text-muted small">
                                        {{ \Illuminate\Support\Str::limit($faq->answer, 120) }}
                                    </div>
                                </td>

                                <td>
                                    @if($faq->is_active)
                                        <span class="badge bg-success">
                                            Active
                                        </span>
                                    @else
                                        <span class="badge bg-danger">
                                            Disabled
                                        </span>
                                    @endif
                                </td>

                                <td class="text-end">

                                    <a href="{{ route('admin.faq.edit', $faq->id) }}"
                                       class="btn btn-sm btn-outline-primary me-2">
                                        Edit
                                    </a>

                                    <form action="{{ route('admin.faq.destroy', $faq->id) }}"
                                          method="POST"
                                          class="d-inline"
                                          onsubmit="return confirm('Delete this FAQ?')">

                                        @csrf
                                        @method('DELETE')

                                        <button type="submit"
                                                class="btn btn-sm btn-outline-danger">
                                            Delete
                                        </button>
                                    </form>

                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center py-5 text-muted">
                                    No FAQs found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                </table>
            </div>

        </div>
    </div>

    {{-- PAGINATION --}}
    <div class="mt-4">
        {{ $faqs->appends(request()->query())->links('pagination::bootstrap-5') }}
    </div>

</div>

</x-app-layout>