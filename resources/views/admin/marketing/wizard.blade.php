@extends('layouts.admin')

@section('title', 'Click-to-WhatsApp Campaign Wizard')

@section('content')
@php
    $steps = [
        1 => 'Meta connection',
        2 => 'Page, IG & WhatsApp',
        3 => 'Objective',
        4 => 'Audience',
        5 => 'Budget & schedule',
        6 => 'Media',
        7 => 'Ad copy',
        8 => 'WhatsApp CTA',
        9 => 'Preview',
        10 => 'Publish',
    ];
    $d = $draft;
@endphp

<div class="mx-auto max-w-5xl space-y-8" x-data="marketingWizard({{ $step }})">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Click-to-WhatsApp Campaign Wizard</h1>
        <p class="mt-1 text-sm text-slate-600">Publish Facebook & Instagram ads with a WhatsApp message button — no unsolicited WhatsApp ads.</p>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="flex flex-wrap gap-2">
        @foreach($steps as $num => $label)
            <a href="{{ route('admin.marketing.wizard', ['step' => $num]) }}"
               class="rounded-full px-3 py-1 text-xs font-semibold {{ $step === $num ? 'bg-xander-navy text-white' : 'bg-slate-100 text-slate-600' }}">
                {{ $num }}. {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('admin.marketing.wizard.step') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="step" value="{{ $step }}">

                @if($step === 1)
                    <h2 class="text-lg font-semibold text-slate-900">Step 1 — Meta connection</h2>
                    @if($connectionStatus['valid'])
                        <p class="mt-2 text-sm text-emerald-700">Connected: {{ $connection?->business_name }} · Ad account {{ $connection?->ad_account_id }}</p>
                    @else
                        <ul class="mt-3 space-y-2 text-sm text-red-700">
                            @foreach($connectionStatus['errors'] as $err)
                                <li><strong>{{ $err['message'] }}</strong> — {{ $err['fix'] }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ route('admin.meta.connect') }}" class="mt-4 inline-flex rounded-xl bg-xander-navy px-4 py-2 text-sm font-semibold text-white">Connect Meta</a>
                    @endif
                    <div class="mt-6">
                        <label class="block text-sm font-semibold">Campaign name</label>
                        <input type="text" name="name" value="{{ old('name', $d['name'] ?? '') }}" required class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3">
                    </div>
                @endif

                @if($step === 2)
                    <h2 class="text-lg font-semibold">Step 2 — Facebook Page + Instagram + WhatsApp</h2>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="block text-sm font-semibold">Facebook Page</label>
                            <select name="page_id" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3" required>
                                <option value="">Select page</option>
                                @foreach($pages as $page)
                                    <option value="{{ $page['id'] }}" @selected(($d['page_id'] ?? $connection?->page_id) == $page['id'])>{{ $page['name'] }} ({{ $page['id'] }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold">Instagram account ID</label>
                            <input type="text" name="instagram_user_id" value="{{ old('instagram_user_id', $d['instagram_user_id'] ?? $connection?->instagram_business_account_id) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3" placeholder="From Meta Business Suite">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold">WhatsApp chat link (any wa.me URL)</label>
                            <input type="text" name="whatsapp_chat_url" value="{{ old('whatsapp_chat_url', $d['whatsapp_chat_url'] ?? '') }}" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 font-mono text-sm" placeholder="https://wa.me/14389009784?text=Hello">
                            <p class="mt-1 text-xs text-slate-500">Paste any WhatsApp link — wa.me, api.whatsapp.com — or use phone below.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold">Or WhatsApp phone (E.164 digits)</label>
                            <input type="text" name="whatsapp_phone_number" value="{{ old('whatsapp_phone_number', $d['whatsapp_phone_number'] ?? $connection?->whatsapp_phone_number) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3" placeholder="14389009784">
                        </div>
                    </div>
                @endif

                @if($step === 3)
                    <h2 class="text-lg font-semibold">Step 3 — Campaign objective</h2>
                    <select name="objective" class="mt-4 w-full rounded-xl border border-slate-200 px-4 py-3" required>
                        @foreach($objectives as $value => $label)
                            <option value="{{ $value }}" @selected(($d['objective'] ?? 'OUTCOME_ENGAGEMENT') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                @endif

                @if($step === 4)
                    <h2 class="text-lg font-semibold">Step 4 — Audience</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold">Countries (comma codes)</label>
                            <input type="text" name="countries" value="{{ old('countries', implode(',', $d['countries'] ?? [])) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3" placeholder="e.g. CA,US">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold">Age min / max</label>
                            <div class="mt-1 flex gap-2">
                                <input type="number" name="age_min" value="{{ $d['age_min'] ?? 18 }}" min="18" class="w-full rounded-xl border border-slate-200 px-4 py-3">
                                <input type="number" name="age_max" value="{{ $d['age_max'] ?? 65 }}" max="65" class="w-full rounded-xl border border-slate-200 px-4 py-3">
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Placements: Facebook feed, stories, reels + Instagram feed, stories, reels (Click-to-WhatsApp compatible).</p>
                @endif

                @if($step === 5)
                    <h2 class="text-lg font-semibold">Step 5 — Budget & schedule</h2>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="block text-sm font-semibold">Daily budget (cents)</label>
                            <input type="number" name="daily_budget" value="{{ $d['daily_budget'] ?? 1000 }}" min="100" required class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3">
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-semibold">Start date</label>
                                <input type="datetime-local" name="start_date" value="{{ $d['start_date'] ?? now()->addHour()->format('Y-m-d\TH:i') }}" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold">End date (optional)</label>
                                <input type="datetime-local" name="end_date" value="{{ $d['end_date'] ?? '' }}" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3">
                            </div>
                        </div>
                    </div>
                @endif

                @if($step === 6)
                    <h2 class="text-lg font-semibold">Step 6 — Upload image</h2>
                    <input type="file" name="image" accept="image/*" class="mt-4 w-full rounded-xl border border-dashed border-slate-300 p-6">
                    @if(!empty($d['image_path']))
                        <p class="mt-2 text-sm text-slate-600">Current: {{ $d['image_path'] }}</p>
                    @endif
                @endif

                @if($step === 7)
                    <h2 class="text-lg font-semibold">Step 7 — Ad text</h2>
                    <div class="mt-4 space-y-4">
                        <input type="text" name="headline" value="{{ $d['headline'] ?? '' }}" placeholder="Headline" class="w-full rounded-xl border border-slate-200 px-4 py-3">
                        <textarea name="primary_text" rows="4" placeholder="Primary text" class="w-full rounded-xl border border-slate-200 px-4 py-3" required>{{ $d['primary_text'] ?? $d['body'] ?? '' }}</textarea>
                        <input type="text" name="description" value="{{ $d['description'] ?? '' }}" placeholder="Description (optional)" class="w-full rounded-xl border border-slate-200 px-4 py-3">
                    </div>
                @endif

                @if($step === 8)
                    <h2 class="text-lg font-semibold">Step 8 — WhatsApp CTA message</h2>
                    <p class="mt-1 text-sm text-slate-600">Pre-filled message when users tap the ad and open WhatsApp.</p>
                    <textarea name="whatsapp_prefill_message" rows="3" class="mt-4 w-full rounded-xl border border-slate-200 px-4 py-3" placeholder="Hello I am interested in your service">{{ $d['whatsapp_prefill_message'] ?? '' }}</textarea>
                    <input type="hidden" name="call_to_action" value="WHATSAPP_MESSAGE">
                @endif

                @if($step === 9)
                    <h2 class="text-lg font-semibold">Step 9 — Preview</h2>
                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                        <p><strong>Name:</strong> {{ $d['name'] ?? '—' }}</p>
                        <p><strong>Objective:</strong> {{ $d['objective'] ?? 'OUTCOME_ENGAGEMENT' }}</p>
                        <p><strong>Budget:</strong> {{ $d['daily_budget'] ?? '—' }} cents/day</p>
                        <p><strong>Primary text:</strong> {{ $d['primary_text'] ?? $d['body'] ?? '—' }}</p>
                        <p><strong>WhatsApp:</strong> {{ $d['whatsapp_chat_url'] ?? $d['whatsapp_phone_number'] ?? '—' }}</p>
                    </div>
                    <button type="button" @click="runPreflight()" class="mt-4 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold">Run preflight check</button>
                    <div x-show="preflightErrors.length" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        <template x-for="err in preflightErrors" :key="err.code">
                            <p x-text="err.message + ' — ' + err.fix"></p>
                        </template>
                    </div>
                @endif

                @if($step === 10)
                    <h2 class="text-lg font-semibold">Step 10 — Publish or save draft</h2>
                    <p class="mt-2 text-sm text-slate-600">Campaign will be created on Meta as PAUSED unless you activate immediately.</p>
                @endif

                @if($step < 10)
                    <button type="submit" class="mt-6 rounded-xl bg-xander-navy px-6 py-3 text-sm font-semibold text-white">Save & continue</button>
                @endif
            </form>

            @if($step === 10)
                <div class="mt-6 flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('admin.marketing.wizard.publish') }}">
                        @csrf
                        <button type="submit" class="rounded-xl bg-xander-navy px-6 py-3 text-sm font-semibold text-white">Publish as PAUSED</button>
                    </form>
                    <form method="POST" action="{{ route('admin.marketing.wizard.publish') }}">
                        @csrf
                        <input type="hidden" name="activate" value="1">
                        <button type="submit" class="rounded-xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white">Publish & activate</button>
                    </form>
                    <form method="POST" action="{{ route('admin.marketing.wizard.draft') }}">
                        @csrf
                        <button type="submit" class="rounded-xl border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-700">Save local draft only</button>
                    </form>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Publishing checklist</h3>
                <ul class="mt-3 space-y-2 text-sm" id="checklist">
                    <li class="text-slate-500">Complete step 9 to run preflight.</li>
                </ul>
            </div>
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Recent Meta API logs</h3>
                <ul class="mt-3 space-y-2 text-xs">
                    @forelse($recentLogs as $log)
                        <li class="{{ $log->success ? 'text-slate-600' : 'text-red-600' }}">
                            {{ $log->method }} {{ Str::limit($log->endpoint, 40) }} — {{ $log->success ? 'OK' : $log->readableError() }}
                        </li>
                    @empty
                        <li class="text-slate-400">No API calls yet.</li>
                    @endforelse
                </ul>
                <a href="{{ route('admin.marketing.logs') }}" class="mt-3 inline-block text-xs font-semibold text-xander-secondary">View all logs →</a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function marketingWizard(step) {
    return {
        step,
        preflightErrors: [],
        async runPreflight() {
            const res = await fetch('{{ route('admin.marketing.wizard.preflight') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            });
            const data = await res.json();
            this.preflightErrors = data.errors || [];
            const list = document.getElementById('checklist');
            if (list && data.checklist) {
                list.innerHTML = data.checklist.map(item =>
                    `<li class="${item.ok ? 'text-emerald-700' : 'text-red-600'}">${item.ok ? '✓' : '✗'} ${item.label}</li>`
                ).join('');
            }
        }
    };
}
</script>
@endpush
@endsection
