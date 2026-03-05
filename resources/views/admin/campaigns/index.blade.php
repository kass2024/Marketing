@extends('layouts.app')

@section('content')

<div class="space-y-8">

    {{-- ================= PAGE HEADER ================= --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">
                Campaigns
            </h1>
            <p class="text-gray-500 mt-2">
                Manage your advertising campaigns across Meta platforms. Create campaigns first, then add ad sets.
            </p>
        </div>

        @can('create', App\Models\Campaign::class)
        <a href="{{ route('admin.campaigns.create') }}"
           class="inline-flex items-center gap-3 bg-blue-600 text-white px-6 py-3 rounded-xl shadow hover:bg-blue-700 transition">
            <span class="text-lg">＋</span>
            New Campaign
        </a>
        @endcan
    </div>


    {{-- ================= METRICS CARDS ================= --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-xl shadow border">
            <p class="text-sm text-gray-500">Total Campaigns</p>
            <p class="text-2xl font-bold mt-1">{{ $campaigns ? $campaigns->total() : 0 }}</p>
        </div>

        <div class="bg-white p-6 rounded-xl shadow border">
            <p class="text-sm text-gray-500">Active Campaigns</p>
            <p class="text-2xl font-bold text-green-600 mt-1">
                {{ $campaigns ? $campaigns->where('status','ACTIVE')->count() : 0 }}
            </p>
        </div>

        <div class="bg-white p-6 rounded-xl shadow border">
            <p class="text-sm text-gray-500">Paused Campaigns</p>
            <p class="text-2xl font-bold text-yellow-600 mt-1">
                {{ $campaigns ? $campaigns->where('status','PAUSED')->count() : 0 }}
            </p>
        </div>

        <div class="bg-white p-6 rounded-xl shadow border">
            <p class="text-sm text-gray-500">Ad Sets</p>
            <p class="text-2xl font-bold text-purple-600 mt-1">
                {{ $totalAdSets ?? 0 }}
            </p>
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
            <select id="objective-filter" class="w-full border rounded-lg px-4 py-2 bg-white">
                <option value="">All Objectives</option>
                <option value="OUTCOME_AWARENESS">Awareness</option>
                <option value="OUTCOME_TRAFFIC">Traffic</option>
                <option value="OUTCOME_ENGAGEMENT">Engagement</option>
                <option value="OUTCOME_LEADS">Leads</option>
                <option value="OUTCOME_SALES">Sales</option>
            </select>
        </div>

        <button class="bg-gray-100 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-200 transition">
            Apply Filters
        </button>
    </div>


    {{-- ================= CAMPAIGN TABLE ================= --}}
    <div class="bg-white rounded-2xl shadow overflow-hidden border">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Campaign</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Objective</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Budget</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Ad Sets</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase">Created</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($campaigns ?? [] as $campaign)
                        <tr class="hover:bg-gray-50 transition">
                            {{-- Campaign --}}
                            <td class="px-6 py-4">
                                <div class="font-semibold text-gray-900">
                                    <a href="{{ route('admin.campaigns.show', $campaign) }}" class="hover:text-blue-600">
                                        {{ $campaign->name }}
                                    </a>
                                </div>
                                @if($campaign->meta_id)
                                    <div class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                                        <span class="w-1 h-1 bg-gray-400 rounded-full"></span>
                                        Meta ID: {{ $campaign->meta_id }}
                                    </div>
                                @endif
                                @if($campaign->ad_account_id)
                                    <div class="text-xs text-gray-400 mt-1">
                                        Account: {{ $campaign->ad_account_id }}
                                    </div>
                                @endif
                            </td>

                            {{-- Objective --}}
                            <td class="px-6 py-4">
                                <span class="text-sm text-gray-700">{{ $campaign->objective ?? 'Not set' }}</span>
                                @if($campaign->buying_type)
                                    <div class="text-xs text-gray-400 mt-1">{{ $campaign->buying_type }}</div>
                                @endif
                            </td>

                            {{-- Budget --}}
                            <td class="px-6 py-4">
                                @if($campaign->daily_budget)
                                    <div class="text-sm font-medium text-gray-900">
                                        ${{ number_format($campaign->daily_budget / 100, 2) }}/day
                                    </div>
                                @elseif($campaign->lifetime_budget)
                                    <div class="text-sm font-medium text-gray-900">
                                        ${{ number_format($campaign->lifetime_budget / 100, 2) }} lifetime
                                    </div>
                                @else
                                    <span class="text-sm text-gray-400">No budget set</span>
                                @endif
                                
                                @if($campaign->spend > 0)
                                    <div class="text-xs text-gray-500 mt-1">
                                        Spent: ${{ number_format($campaign->spend / 100, 2) }}
                                    </div>
                                @endif
                            </td>

                            {{-- Ad Sets Count --}}
                            <td class="px-6 py-4">
                                <a href="{{ route('admin.campaigns.adsets.index', $campaign->id) }}" 
                                   class="inline-flex items-center gap-2 text-purple-600 hover:text-purple-800"
                                   title="View ad sets for this campaign">
                                    <span class="text-sm font-medium">{{ $campaign->ad_sets_count ?? 0 }}</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </a>
                            </td>

                            {{-- Status --}}
                            <td class="px-6 py-4">
                                @switch($campaign->status)
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
                                    @case('DRAFT')
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">
                                            Draft
                                        </span>
                                        @break
                                    @default
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">
                                            {{ $campaign->status }}
                                        </span>
                                @endswitch
                            </td>

                            {{-- Created --}}
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $campaign->created_at ? $campaign->created_at->format('d M Y') : 'N/A' }}
                                @if($campaign->started_at)
                                    <div class="text-xs text-gray-400 mt-1">
                                        Started: {{ $campaign->started_at->format('d M Y') }}
                                    </div>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    {{-- Quick Actions --}}
                                    <a href="{{ route('admin.campaigns.adsets.index', $campaign->id) }}"
                                       class="text-purple-600 hover:text-purple-800 text-sm font-medium inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-purple-50"
                                       title="View Ad Sets">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                                        </svg>
                                        <span class="hidden md:inline">Ad Sets</span>
                                    </a>

                                    <a href="{{ route('admin.campaigns.adsets.create', $campaign->id) }}"
                                       class="text-blue-600 hover:text-blue-800 text-sm font-medium inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-blue-50"
                                       title="Create Ad Set">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        <span class="hidden md:inline">Add</span>
                                    </a>

                                    {{-- Dropdown Menu --}}
                                    <div class="relative inline-block text-left" x-data="{ open: false }">
                                        <button @click="open = !open" class="text-gray-600 hover:text-gray-800 p-1 rounded hover:bg-gray-100">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                            </svg>
                                        </button>
                                        
                                        <div x-show="open" @click.away="open = false"
                                             class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border py-1 z-10"
                                             x-cloak>
                                            
                                            {{-- Status Toggle --}}
                                            @if($campaign->status === 'ACTIVE')
                                                <form method="POST" action="{{ route('admin.campaigns.pause', $campaign) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-yellow-600 hover:bg-gray-50">
                                                        Pause Campaign
                                                    </button>
                                                </form>
                                            @elseif($campaign->status === 'PAUSED')
                                                <form method="POST" action="{{ route('admin.campaigns.activate', $campaign) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-green-600 hover:bg-gray-50">
                                                        Activate Campaign
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- Duplicate --}}
                                            <form method="POST" action="{{ route('admin.campaigns.duplicate', $campaign) }}">
                                                @csrf
                                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                    Duplicate Campaign
                                                </button>
                                            </form>

                                            {{-- View Insights --}}
                                            <a href="{{ route('admin.campaigns.insights', $campaign) }}" 
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                View Insights
                                            </a>

                                            <div class="border-t my-1"></div>

                                            {{-- Edit --}}
                                            <a href="{{ route('admin.campaigns.edit', $campaign) }}"
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                Edit Campaign
                                            </a>

                                            {{-- Delete --}}
                                            <form method="POST"
                                                  action="{{ route('admin.campaigns.destroy', $campaign) }}"
                                                  onsubmit="return confirm('Delete this campaign? This will also delete all associated ad sets and ads. This action cannot be undone.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-50">
                                                    Delete Campaign
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-16">
                                <div class="text-5xl mb-4 text-gray-300">
                                    📊
                                </div>
                                <p class="text-gray-600 font-medium">
                                    No campaigns created yet
                                </p>
                                <p class="text-sm text-gray-400 mt-2 max-w-md mx-auto">
                                    Create your first marketing campaign, then add ad sets to define your audience and budget.
                                </p>
                                @can('create', App\Models\Campaign::class)
                                <a href="{{ route('admin.campaigns.create') }}"
                                   class="inline-block mt-6 bg-blue-600 text-white px-6 py-3 rounded-xl hover:bg-blue-700">
                                    Create Campaign
                                </a>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($campaigns && method_exists($campaigns, 'links') && $campaigns->hasPages())
            <div class="px-6 py-4 border-t bg-gray-50">
                {{ $campaigns->links() }}
            </div>
        @endif
    </div>

    {{-- Meta Connection Warning --}}
    @if(!isset($hasAdAccount) || !$hasAdAccount)
    <div class="bg-yellow-50 border-l-4 border-yellow-400 rounded-lg p-4">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-700">
                    <strong class="font-medium">Meta Ad Account Not Connected:</strong> 
                    You need to connect a Meta Ad Account before creating campaigns. 
                    <a href="{{ route('admin.accounts.index') }}" class="underline hover:text-yellow-600">Connect now →</a>
                </p>
            </div>
        </div>
    </div>
    @endif

    {{-- ================= QUICK START GUIDE ================= --}}
    @if($campaigns && $campaigns->count() > 0)
    <div class="bg-gradient-to-r from-blue-50 to-purple-50 rounded-2xl p-6 border border-blue-100">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Start: Create Ad Sets</h3>
        
        <div class="grid md:grid-cols-3 gap-4">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold">1</div>
                <div>
                    <p class="font-medium text-gray-800">Create Campaign</p>
                    <p class="text-sm text-gray-600">Set your objective, budget and schedule</p>
                </div>
            </div>
            
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center font-bold">2</div>
                <div>
                    <a href="{{ $campaigns->first() ? route('admin.campaigns.adsets.create', $campaigns->first()->id) : '#' }}" 
                       class="font-medium text-purple-600 hover:text-purple-800 hover:underline">
                        Add Ad Sets →
                    </a>
                    <p class="text-sm text-gray-600">Define audience targeting, placements and budget</p>
                </div>
            </div>
            
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-full bg-green-100 text-green-600 flex items-center justify-center font-bold">3</div>
                <div>
                    <p class="font-medium text-gray-800">Create Ads</p>
                    <p class="text-sm text-gray-600">Design creatives and launch your campaign</p>
                </div>
            </div>
        </div>
    </div>
    @endif

</div>

@endsection

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
    // Filter functionality
    document.addEventListener('DOMContentLoaded', function() {
        const statusFilter = document.getElementById('status-filter');
        const objectiveFilter = document.getElementById('objective-filter');
        const applyButton = document.querySelector('button.bg-gray-100');

        if (applyButton) {
            applyButton.addEventListener('click', function() {
                const params = new URLSearchParams(window.location.search);
                
                if (statusFilter.value) {
                    params.set('status', statusFilter.value);
                } else {
                    params.delete('status');
                }
                
                if (objectiveFilter.value) {
                    params.set('objective', objectiveFilter.value);
                } else {
                    params.delete('objective');
                }
                
                window.location.search = params.toString();
            });
        }

        // Pre-select filters from URL
        const urlParams = new URLSearchParams(window.location.search);
        const urlStatus = urlParams.get('status');
        const urlObjective = urlParams.get('objective');
        
        if (urlStatus && statusFilter) statusFilter.value = urlStatus;
        if (urlObjective && objectiveFilter) objectiveFilter.value = urlObjective;

        // Handle dropdown clicks outside
        document.addEventListener('click', function(e) {
            const dropdowns = document.querySelectorAll('[x-data]');
            dropdowns.forEach(dropdown => {
                if (dropdown.__x) {
                    const open = dropdown.__x.getUnobservedData().open;
                    if (open && !dropdown.contains(e.target)) {
                        dropdown.__x.setData('open', false);
                    }
                }
            });
        });
    });
</script>
@endpush