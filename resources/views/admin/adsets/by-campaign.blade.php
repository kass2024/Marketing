@extends('layouts.admin')

@section('content')

<div class="space-y-8">

    {{-- ================= PAGE HEADER ================= --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
        <div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.campaigns.index') }}" 
                   class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h1 class="text-3xl font-bold text-gray-900">
                    Ad Sets: {{ $campaign->name }}
                </h1>
            </div>
            <p class="text-gray-500 mt-2">
                Manage ad sets for this campaign. Each ad set defines your audience, budget, and placements.
            </p>
        </div>

        <div class="flex gap-3">
            <a href="{{ route('admin.campaigns.show', $campaign->id) }}"
               class="inline-flex items-center gap-2 bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                View Campaign
            </a>
            
            <a href="{{ route('admin.campaigns.adsets.create', $campaign->id) }}"
               class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                <span class="text-lg">＋</span>
                New Ad Set
            </a>
        </div>
    </div>


    {{-- ================= CAMPAIGN SUMMARY ================= --}}
    <div class="bg-gradient-to-r from-blue-50 to-purple-50 rounded-2xl p-6 border border-blue-100">
        <div class="grid md:grid-cols-4 gap-6">
            <div>
                <p class="text-sm text-gray-600">Campaign Objective</p>
                <p class="text-lg font-semibold mt-1">{{ $campaign->objective ?? 'Not set' }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Campaign Budget</p>
                <p class="text-lg font-semibold mt-1">
                    @if($campaign->daily_budget)
                        ${{ number_format($campaign->daily_budget / 100, 2) }}/day
                    @else
                        Not set
                    @endif
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Campaign Status</p>
                <p class="mt-1">
                    @switch($campaign->status)
                        @case('ACTIVE')
                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">Active</span>
                            @break
                        @case('PAUSED')
                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700">Paused</span>
                            @break
                        @default
                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">{{ $campaign->status }}</span>
                    @endswitch
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Meta Sync</p>
                <p class="mt-1">
                    @if($campaign->meta_id)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">Synced</span>
                    @else
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700">Not Synced</span>
                    @endif
                </p>
            </div>
        </div>
    </div>


    {{-- ================= METRICS CARDS ================= --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-xl shadow border">
            <p class="text-sm text-gray-500">Total Ad Sets</p>
            <p class="text-2xl font-bold mt-1">{{ $stats['total'] }}</p>
        </div>

        <div class="bg-white p-6 rounded-xl shadow border">
            <p class="text-sm text-gray-500">Active</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ $stats['active'] }}</p>
        </div>

        <div class="bg-white p-6 rounded-xl shadow border">
            <p class="text-sm text-gray-500">Paused</p>
            <p class="text-2xl font-bold text-yellow-600 mt-1">{{ $stats['paused'] }}</p>
        </div>

        <div class="bg-white p-6 rounded-xl shadow border">
            <p class="text-sm text-gray-500">Total Daily Budget</p>
            <p class="text-2xl font-bold text-purple-600 mt-1">${{ number_format($totalBudget, 2) }}</p>
        </div>
    </div>


    {{-- ================= FILTERS ================= --}}
    <div class="bg-white rounded-xl shadow p-4 flex flex-wrap gap-4 items-center">
        <div class="flex-1 min-w-[200px]">
            <select id="status-filter" class="w-full border rounded-lg px-4 py-2 bg-white">
                <option value="">All Statuses</option>
                <option value="ACTIVE">Active</option>
                <option value="PAUSED">Paused</option>
                <option value="DRAFT">Draft</option>
            </select>
        </div>
        
        <div class="flex-1 min-w-[200px]">
            <input type="text" 
                   id="search" 
                   placeholder="Search ad sets..." 
                   class="w-full border rounded-lg px-4 py-2">
        </div>

        <button class="bg-gray-100 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-200 transition">
            Apply Filters
        </button>
    </div>


    {{-- ================= AD SETS TABLE ================= --}}
    <div class="bg-white rounded-2xl shadow overflow-hidden border">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Ad Set</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Budget</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Targeting</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Created</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($adsets as $adset)
                        <tr class="hover:bg-gray-50 transition">
                            {{-- Ad Set --}}
                            <td class="px-6 py-4">
                                <div class="font-semibold text-gray-900">
                                    <a href="{{ route('admin.adsets.show', $adset->id) }}" class="hover:text-blue-600">
                                        {{ $adset->name }}
                                    </a>
                                </div>
                                @if($adset->meta_id)
                                    <div class="text-xs text-gray-400 mt-1">
                                        Meta ID: {{ $adset->meta_id }}
                                    </div>
                                @endif
                            </td>

                            {{-- Budget --}}
                            <td class="px-6 py-4">
                                @if($adset->daily_budget)
                                    <div class="text-sm font-medium text-gray-900">
                                        ${{ number_format($adset->daily_budget / 100, 2) }}/day
                                    </div>
                                @else
                                    <span class="text-sm text-gray-400">No budget</span>
                                @endif
                            </td>

                            {{-- Targeting Summary --}}
                            <td class="px-6 py-4">
                                @php
                                    $targeting = json_decode($adset->targeting, true) ?? [];
                                    $countries = $targeting['geo_locations']['countries'] ?? [];
                                    $ageRange = ($targeting['age_min'] ?? 18) . '-' . ($targeting['age_max'] ?? 65);
                                @endphp
                                <div class="text-sm text-gray-700">
                                    <span class="font-medium">Age:</span> {{ $ageRange }}
                                </div>
                                <div class="text-sm text-gray-700">
                                    <span class="font-medium">Countries:</span> {{ count($countries) }}
                                </div>
                            </td>

                            {{-- Status --}}
                            <td class="px-6 py-4">
                                @switch($adset->status)
                                    @case('ACTIVE')
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">
                                            Active
                                        </span>
                                        @break
                                    @case('PAUSED')
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700">
                                            Paused
                                        </span>
                                        @break
                                    @default
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">
                                            {{ $adset->status }}
                                        </span>
                                @endswitch
                            </td>

                            {{-- Created --}}
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $adset->created_at->format('d M Y') }}
                            </td>

                            {{-- Actions --}}
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.adsets.show', $adset->id) }}"
                                       class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        View
                                    </a>

                                    <a href="{{ route('admin.adsets.edit', $adset->id) }}"
                                       class="text-gray-600 hover:text-gray-800 text-sm font-medium">
                                        Edit
                                    </a>

                                    @if($adset->status === 'ACTIVE')
                                        <form method="POST" 
                                              action="{{ route('admin.adsets.pause', $adset->id) }}"
                                              class="inline"
                                              onsubmit="event.preventDefault(); confirmAction('pause', {{ $adset->id }})">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-yellow-600 hover:text-yellow-800 text-sm font-medium">
                                                Pause
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" 
                                              action="{{ route('admin.adsets.activate', $adset->id) }}"
                                              class="inline"
                                              onsubmit="event.preventDefault(); confirmAction('activate', {{ $adset->id }})">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-green-600 hover:text-green-800 text-sm font-medium">
                                                Activate
                                            </button>
                                        </form>
                                    @endif

                                    <form method="POST"
                                          action="{{ route('admin.adsets.destroy', $adset->id) }}"
                                          class="inline"
                                          onsubmit="return confirm('Delete this ad set? This action cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-red-500 hover:text-red-700 text-sm font-medium">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-16">
                                <div class="text-5xl mb-4 text-gray-300">
                                    🎯
                                </div>
                                <p class="text-gray-600 font-medium">
                                    No ad sets created yet
                                </p>
                                <p class="text-sm text-gray-400 mt-2 max-w-md mx-auto">
                                    Create your first ad set to define your audience, budget, and placements for this campaign.
                                </p>
                                <a href="{{ route('admin.campaigns.adsets.create', $campaign->id) }}"
                                   class="inline-block mt-6 bg-blue-600 text-white px-6 py-3 rounded-xl hover:bg-blue-700">
                                    Create First Ad Set
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if(method_exists($adsets, 'links'))
            <div class="px-6 py-4 border-t bg-gray-50">
                {{ $adsets->links() }}
            </div>
        @endif
    </div>

</div>

@endsection

@push('scripts')
<script>
function confirmAction(action, id) {
    if (confirm(`Are you sure you want to ${action} this ad set?`)) {
        const form = event.target;
        form.submit();
    }
}

// Filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const statusFilter = document.getElementById('status-filter');
    const searchInput = document.getElementById('search');
    const applyButton = document.querySelector('button.bg-gray-100');

    applyButton.addEventListener('click', function() {
        const params = new URLSearchParams(window.location.search);
        
        if (statusFilter.value) {
            params.set('status', statusFilter.value);
        } else {
            params.delete('status');
        }
        
        if (searchInput.value) {
            params.set('search', searchInput.value);
        } else {
            params.delete('search');
        }
        
        window.location.search = params.toString();
    });

    // Pre-select filters from URL
    const urlParams = new URLSearchParams(window.location.search);
    const urlStatus = urlParams.get('status');
    const urlSearch = urlParams.get('search');
    
    if (urlStatus) statusFilter.value = urlStatus;
    if (urlSearch) searchInput.value = urlSearch;
});
</script>
@endpush