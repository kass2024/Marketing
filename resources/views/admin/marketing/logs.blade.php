@extends('layouts.admin')

@section('title', 'Meta API Logs')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Meta API Logs</h1>
            <p class="text-sm text-slate-600">{{ $errorCount }} errors in the last 24 hours</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.marketing.logs') }}" class="rounded-xl border px-4 py-2 text-sm {{ !request()->boolean('errors_only') ? 'bg-xander-navy text-white' : '' }}">All</a>
            <a href="{{ route('admin.marketing.logs', ['errors_only' => 1]) }}" class="rounded-xl border px-4 py-2 text-sm {{ request()->boolean('errors_only') ? 'bg-red-600 text-white' : '' }}">Errors only</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Time</th>
                    <th class="px-4 py-3">Method</th>
                    <th class="px-4 py-3">Endpoint</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Duration</th>
                    <th class="px-4 py-3">Result</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($logs as $log)
                    <tr class="align-top hover:bg-slate-50">
                        <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ $log->created_at->format('M j H:i:s') }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $log->method }}</td>
                        <td class="px-4 py-3 font-mono text-xs max-w-xs truncate" title="{{ $log->endpoint }}">{{ $log->endpoint }}</td>
                        <td class="px-4 py-3">{{ $log->http_status }}</td>
                        <td class="px-4 py-3">{{ $log->duration_ms }}ms</td>
                        <td class="px-4 py-3">
                            @if($log->success)
                                <span class="text-emerald-700">OK</span>
                            @else
                                <span class="text-red-700">{{ $log->readableError() }}</span>
                                @if($log->is_retryable)
                                    <span class="ml-1 text-xs text-amber-600">(retryable)</span>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
</div>
@endsection
