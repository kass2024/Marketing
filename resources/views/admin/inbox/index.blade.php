@extends('layouts.admin')

@section('title', 'Inbox')

@section('content')

@php
    $pollUrl = $activeConversation
        ? route('admin.inbox.fetch', $activeConversation)
        : null;
@endphp

<div id="inbox-root"
     class="inbox-root -m-4 flex min-h-[calc(100vh-3.5rem)] overflow-hidden font-sans antialiased lg:-m-8"
     data-theme="dark"
     style="min-height: 560px;">

    <aside id="inbox-sidebar"
           class="inbox-sidebar fixed inset-y-0 left-0 z-40 flex w-full max-w-[380px] flex-col border-r transition-transform duration-200 md:static md:z-0 md:max-w-[360px] md:translate-x-0 -translate-x-full">
        <div class="inbox-surface-strong flex items-center justify-between gap-2 border-b px-3 py-3">
            <h2 class="text-sm font-semibold tracking-wide inbox-text">Chats</h2>
            <div class="flex flex-wrap items-center justify-end gap-1.5">
                <button type="button" id="inbox-theme-toggle"
                        class="inbox-btn-ghost rounded-lg px-2.5 py-1 text-[11px] font-semibold"
                        title="Toggle light/dark">Theme</button>
                <a href="{{ route('admin.dashboard') }}"
                   class="rounded-lg bg-xander-navy px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-xander-secondary">Overview</a>
                <a href="{{ url('/admin/bulk') }}"
                   class="rounded-lg bg-emerald-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-emerald-700">Bulk</a>
            </div>
        </div>

        <form method="get" action="{{ route('admin.inbox.index') }}" class="inbox-surface border-b p-2">
            @if(request('conversation'))
                <input type="hidden" name="conversation" value="{{ request('conversation') }}">
            @endif
            <input type="hidden" name="filter" value="{{ $filter }}">
            <div class="flex gap-2">
                <input type="search" name="search" value="{{ $search }}"
                       placeholder="Search or start new chat"
                       class="inbox-input min-w-0 flex-1 rounded-xl px-3 py-2 text-sm">
                <button type="submit" class="inbox-btn-primary shrink-0 rounded-xl px-3 py-2 text-xs font-bold">Go</button>
            </div>
        </form>

        <div class="inbox-surface flex gap-1 overflow-x-auto border-b px-2 py-2 text-[11px] font-semibold">
            @foreach(['all','unread','human','bot','closed'] as $f)
                <a href="{{ route('admin.inbox.index', array_filter(['filter' => $f, 'search' => $search, 'conversation' => request('conversation')])) }}"
                   class="inbox-filter-pill whitespace-nowrap rounded-full px-3 py-1 {{ $filter === $f ? 'inbox-filter-pill--on' : 'inbox-filter-pill--off' }}">
                    {{ ucfirst($f) }}
                </a>
            @endforeach
        </div>

        <div class="inbox-sidebar-inner flex-1 overflow-y-auto">
            @foreach($conversations as $conversation)
                <a href="{{ route('admin.inbox.index', ['conversation' => $conversation->id, 'filter' => $filter, 'search' => $search]) }}"
                   class="inbox-conv-row flex items-center gap-3 border-b px-3 py-2.5 {{ (string)request('conversation') === (string)$conversation->id ? 'inbox-conv-row--active' : '' }}">
                    <div class="relative shrink-0">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full inbox-avatar text-sm font-bold text-white">
                            {{ strtoupper(substr($conversation->customer_name ?? 'U', 0, 1)) }}
                        </div>
                        @if($conversation->is_online)
                            <span class="absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 inbox-online-ring bg-emerald-500" title="Online"></span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold inbox-text">
                            {{ $conversation->customer_name ?? $conversation->phone_number }}
                            @if($conversation->status === 'human')
                                <span class="ml-1 rounded bg-amber-500/90 px-1.5 py-0.5 text-[10px] font-bold text-neutral-900">AGENT</span>
                            @elseif($conversation->status === 'escalated')
                                <span class="ml-1 rounded bg-orange-500/90 px-1.5 py-0.5 text-[10px] font-bold text-white">ESC</span>
                            @endif
                        </p>
                        <p class="truncate text-xs inbox-muted">{{ $conversation->customer_email ?? $conversation->phone_number }}</p>
                    </div>
                    @if($conversation->unread_count > 0)
                        <span class="inbox-unread-badge shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold">{{ $conversation->unread_count }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="inbox-surface border-t p-2 text-center text-[11px] inbox-muted">
            {{ $conversations->links() }}
        </div>
    </aside>

    <section class="inbox-main flex min-w-0 flex-1 flex-col">

        @if($activeConversation)

            <header class="inbox-header flex flex-wrap items-center justify-between gap-2 border-b px-2 py-2 sm:px-4">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" onclick="document.getElementById('inbox-sidebar').classList.toggle('-translate-x-full')"
                            class="inbox-icon-btn flex h-10 w-10 shrink-0 items-center justify-center rounded-full md:hidden" aria-label="Open chats">☰</button>
                    <div class="relative shrink-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full inbox-avatar text-sm font-bold text-white">
                            {{ strtoupper(substr($activeConversation->customer_name ?? 'U', 0, 1)) }}
                        </div>
                        <span class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full border-2 inbox-header-ring {{ $activeConversation->is_online ? 'bg-emerald-500' : 'bg-neutral-400' }}" title="{{ $activeConversation->is_online ? 'Online' : 'Offline' }}"></span>
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate text-sm font-semibold inbox-text">{{ $activeConversation->customer_name }}</p>
                            <span id="mode-chip" class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $activeConversation->status === 'bot' ? 'inbox-chip-bot' : 'inbox-chip-human' }}">
                                {{ $activeConversation->status === 'bot' ? 'Bot' : 'Human' }}
                            </span>
                        </div>
                        <p id="presence-line" class="truncate text-xs inbox-muted">
                            @if($activeConversation->is_online)
                                <span class="font-medium text-emerald-600 dark:text-emerald-400">Online</span>
                            @else
                                {{ $activeConversation->customerLastSeenLabel() }}
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex w-full flex-wrap items-center justify-end gap-1.5 sm:w-auto">
                    <form method="POST" action="{{ route('admin.inbox.toggle', $activeConversation) }}" class="inline">@csrf
                        <button type="submit" class="inbox-action-btn rounded-lg px-3 py-1.5 text-xs font-semibold {{ $activeConversation->status === 'bot' ? 'bg-amber-500 text-neutral-900' : 'inbox-btn-primary' }}">
                            {{ $activeConversation->status === 'bot' ? 'Handoff to human' : 'Back to bot' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.inbox.close', $activeConversation) }}" class="inline">@csrf
                        <button type="submit" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">Close</button>
                    </form>
                    <form method="POST" action="{{ route('admin.inbox.delete', $activeConversation) }}" class="inline" onsubmit="return confirm('Delete conversation?')">@csrf @method('DELETE')
                        <button type="submit" class="inbox-action-muted rounded-lg px-3 py-1.5 text-xs font-semibold">Delete</button>
                    </form>
                </div>
            </header>

            <div id="chatBox" class="inbox-chat-bg flex-1 overflow-y-auto px-3 py-4 sm:px-8"
                 data-poll-url="{{ $pollUrl }}"
                 data-inbox-delete-base="{{ url('/admin/inbox/'.$activeConversation->id.'/messages') }}">
                <div class="mx-auto max-w-3xl">
                    @foreach($activeConversation->messages as $message)
                        @include('admin.inbox.partials.message-bubble', ['message' => $message])
                    @endforeach
                </div>
            </div>

            <div class="inbox-composer border-t p-2 sm:p-3">
                <div id="composer-draft" class="mx-auto mb-2 hidden max-w-3xl rounded-xl border border-dashed border-[var(--inbox-accent)]/50 bg-[var(--inbox-input-bg)] px-3 py-2.5 shadow-sm inbox-draft-strip">
                    <div class="flex items-center gap-2">
                        <span id="draft-icon" class="text-xl" aria-hidden="true">📎</span>
                        <div class="min-w-0 flex-1">
                            <p id="draft-label" class="text-xs font-semibold uppercase tracking-wide opacity-70">Ready to send</p>
                            <p id="draft-name" class="truncate text-sm font-medium"></p>
                        </div>
                        <span id="draft-meta" class="shrink-0 text-xs opacity-70"></span>
                        <button type="button" id="draft-clear" class="shrink-0 rounded-lg px-2 py-1 text-xs font-bold text-red-600 hover:bg-red-500/10">Remove</button>
                    </div>
                </div>

                <div id="record-panel" class="mx-auto mb-2 hidden max-w-3xl rounded-xl border border-red-500/40 bg-red-600/10 px-3 py-2.5">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span class="flex items-center gap-2 text-sm font-semibold text-red-800 dark:text-red-200">
                            <span class="inbox-rec-dot h-2.5 w-2.5 shrink-0 rounded-full bg-red-500"></span>
                            Recording <span id="record-timer" class="tabular-nums">0:00</span>
                        </span>
                        <button type="button" id="record-stop-btn" class="rounded-lg bg-red-600 px-3 py-1 text-xs font-bold text-white hover:bg-red-700">Stop</button>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-black/10 dark:bg-white/10">
                        <div id="record-progress-fill" class="h-full rounded-full bg-red-500 transition-[width] duration-100 ease-linear" style="width:0%"></div>
                    </div>
                    <div id="record-waveform" class="inbox-waveform mt-2 flex h-11 items-end justify-center gap-px overflow-hidden rounded-lg bg-black/10 px-1 py-1 dark:bg-white/10" aria-hidden="true"></div>
                    <p class="mt-1.5 text-[10px] opacity-80">Hold the mic, or click <strong>Stop</strong> — preview appears above Send. Then press <strong>Send</strong>.</p>
                </div>

                <form id="reply-form" method="POST" action="{{ route('admin.inbox.reply', $activeConversation) }}" enctype="multipart/form-data" class="mx-auto flex max-w-3xl items-end gap-2">
                    @csrf
                    <input type="file" id="attachment-input" name="attachment" accept="image/*,video/mp4,video/quicktime,.mp4,.mov,.3gp,audio/*,.mp3,.m4a,.ogg,.opus,.webm,application/pdf,.doc,.docx" class="hidden">

                    <button type="button" id="attach-btn" onclick="document.getElementById('attachment-input').click()"
                            class="inbox-composer-icon flex h-11 w-11 shrink-0 items-center justify-center rounded-full" title="Attach" aria-label="Attach file">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    </button>

                    <button type="button" id="mic-btn" class="inbox-composer-icon flex h-11 w-11 shrink-0 items-center justify-center rounded-full" title="Hold to record" aria-label="Record voice note">
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/><path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/></svg>
                    </button>

                    <div class="relative min-w-0 flex-1">
                        <input type="text" name="message" id="message-input" placeholder="Type a message"
                               class="inbox-input w-full rounded-3xl px-4 py-3 text-sm">
                    </div>

                    <button type="submit" class="inbox-btn-primary flex h-11 min-w-[4.5rem] items-center justify-center rounded-full px-4 text-sm font-bold">
                        Send
                    </button>
                </form>
                <p class="mx-auto mt-2 max-w-3xl text-center text-[10px] leading-relaxed inbox-muted">
                    <strong>Browser</strong> needs microphone permission (HTTPS). <strong>Linux server:</strong>
                    <code class="rounded bg-black/10 px-1">sudo apt update &amp;&amp; sudo apt install -y ffmpeg</code>,
                    then <code class="rounded bg-black/10 px-1">which ffmpeg</code> → set <code class="rounded bg-black/10 px-1">FFMPEG_BINARY</code> in <code class="rounded bg-black/10 px-1">.env</code> if not on PATH.
                    Ensure <code class="rounded bg-black/10 px-1">php artisan storage:link</code>, writable <code class="rounded bg-black/10 px-1">storage/</code>, and <code class="rounded bg-black/10 px-1">APP_URL</code> is HTTPS so Meta can fetch media.
                </p>
            </div>

        @else
            <div class="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center inbox-muted">
                <p class="text-lg font-semibold inbox-text">Select a conversation</p>
                <p class="max-w-sm text-sm">Choose a chat on the left to reply with text, images, video, documents, or voice notes.</p>
                <button type="button" class="inbox-btn-primary rounded-full px-4 py-2 text-sm font-bold md:hidden"
                        onclick="document.getElementById('inbox-sidebar').classList.remove('-translate-x-full')">Open chats</button>
            </div>
        @endif
    </section>
</div>

@push('styles')
<style>
.inbox-root {
    --inbox-page: #0b141a;
    --inbox-rail: #111b21;
    --inbox-rail-border: rgba(255,255,255,0.06);
    --inbox-surface: #202c33;
    --inbox-surface-strong: #202c33;
    --inbox-text: #e9edef;
    --inbox-muted: #8696a0;
    --inbox-input-bg: #2a3942;
    --inbox-border: rgba(255,255,255,0.08);
    --inbox-accent: #00a884;
    --inbox-accent-contrast: #111b21;
    --inbox-bubble-out: #005c4b;
    --inbox-bubble-in: #202c33;
    --inbox-bubble-text: #e9edef;
    --inbox-chat-dots: rgba(134,150,160,0.08);
    --inbox-avatar: #6b7c85;
    --inbox-row-hover: rgba(255,255,255,0.04);
    --inbox-row-active: #2a3942;
}
.inbox-root[data-theme="light"] {
    --inbox-page: #e5ddd5;
    --inbox-rail: #ffffff;
    --inbox-rail-border: rgba(0,0,0,0.08);
    --inbox-surface: #f0f2f5;
    --inbox-surface-strong: #ffffff;
    --inbox-text: #111b21;
    --inbox-muted: #667781;
    --inbox-input-bg: #ffffff;
    --inbox-border: rgba(0,0,0,0.1);
    --inbox-accent: #00a884;
    --inbox-accent-contrast: #ffffff;
    --inbox-bubble-out: #d9fdd3;
    --inbox-bubble-in: #ffffff;
    --inbox-bubble-text: #111b21;
    --inbox-chat-dots: rgba(0,0,0,0.06);
    --inbox-avatar: #8696a0;
    --inbox-row-hover: rgba(0,0,0,0.04);
    --inbox-row-active: #e8ecef;
}
.inbox-root { background: var(--inbox-page); color: var(--inbox-text); }
.inbox-sidebar { background: var(--inbox-rail); border-color: var(--inbox-rail-border); }
.inbox-sidebar-inner { background: var(--inbox-rail); }
.inbox-surface { background: var(--inbox-surface); border-color: var(--inbox-rail-border); color: var(--inbox-text); }
.inbox-surface-strong { background: var(--inbox-surface-strong); border-color: var(--inbox-rail-border); }
.inbox-main { background: var(--inbox-page); }
.inbox-header { background: var(--inbox-surface-strong); border-color: var(--inbox-rail-border); }
.inbox-composer { background: var(--inbox-surface-strong); border-color: var(--inbox-rail-border); }
.inbox-text { color: var(--inbox-text); }
.inbox-muted { color: var(--inbox-muted); }
.inbox-input {
    background: var(--inbox-input-bg);
    color: var(--inbox-text);
    border: 1px solid var(--inbox-border);
}
.inbox-input::placeholder { color: var(--inbox-muted); }
.inbox-input:focus { outline: none; box-shadow: 0 0 0 2px var(--inbox-accent); }
.inbox-btn-primary {
    background: var(--inbox-accent);
    color: var(--inbox-accent-contrast);
}
.inbox-btn-primary:hover { filter: brightness(1.05); }
.inbox-btn-ghost {
    border: 1px solid var(--inbox-border);
    color: var(--inbox-text);
    background: transparent;
}
.inbox-filter-pill--on { background: var(--inbox-accent); color: var(--inbox-accent-contrast); }
.inbox-filter-pill--off { background: var(--inbox-input-bg); color: var(--inbox-muted); }
.inbox-conv-row { border-color: var(--inbox-rail-border); }
.inbox-conv-row:hover { background: var(--inbox-row-hover); }
.inbox-conv-row--active { background: var(--inbox-row-active); }
.inbox-avatar { background: var(--inbox-avatar); }
.inbox-online-ring { border-color: var(--inbox-rail); }
.inbox-header-ring { border-color: var(--inbox-surface-strong); }
.inbox-unread-badge { background: var(--inbox-accent); color: var(--inbox-accent-contrast); }
.inbox-chip-bot { background: rgba(0,168,132,0.2); color: var(--inbox-accent); }
.inbox-chip-human { background: rgba(245,158,11,0.25); color: #b45309; }
.inbox-root[data-theme="light"] .inbox-chip-human { color: #92400e; }
.inbox-action-btn.inbox-btn-primary { background: var(--inbox-accent); color: var(--inbox-accent-contrast); }
.inbox-action-muted { background: var(--inbox-input-bg); color: var(--inbox-text); border: 1px solid var(--inbox-border); }
.inbox-icon-btn { color: var(--inbox-text); }
.inbox-icon-btn:hover { background: var(--inbox-row-hover); }
.inbox-composer-icon { color: var(--inbox-muted); }
.inbox-composer-icon:hover { background: var(--inbox-row-hover); color: var(--inbox-text); }
.inbox-chat-bg {
    background-color: var(--inbox-page);
    background-image: radial-gradient(circle at 1px 1px, var(--inbox-chat-dots) 1px, transparent 0);
    background-size: 24px 24px;
}
.inbox-bubble {
    border-radius: 1rem;
    padding: 0.625rem 0.875rem;
    color: var(--inbox-bubble-text);
}
.inbox-bubble--out {
    background: var(--inbox-bubble-out);
    border-bottom-right-radius: 0.25rem;
}
.inbox-bubble--in {
    background: var(--inbox-bubble-in);
    border-bottom-left-radius: 0.25rem;
    border: 1px solid var(--inbox-border);
}
.inbox-msg-text { color: var(--inbox-bubble-text); }
.inbox-msg-meta { color: var(--inbox-muted); }
.inbox-draft-strip { border-color: var(--inbox-border); background: var(--inbox-input-bg); color: var(--inbox-text); }
.inbox-composer-icon--recording { color: #ef4444 !important; animation: inbox-pulse 1s ease-in-out infinite; }
@keyframes inbox-pulse { 50% { opacity: 0.65; } }
.inbox-rec-dot { animation: inbox-pulse 1s ease-in-out infinite; }
.inbox-waveform span { display: block; width: 3px; min-height: 3px; border-radius: 1px; background: rgba(239,68,68,0.85); align-self: flex-end; transition: height 0.05s linear; }
.inbox-root[data-theme="light"] .inbox-waveform span { background: rgba(220,38,38,0.9); }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const root = document.getElementById('inbox-root');
    const stored = localStorage.getItem('inbox-theme');
    if (root && (stored === 'light' || stored === 'dark')) {
        root.setAttribute('data-theme', stored);
    }
    document.getElementById('inbox-theme-toggle')?.addEventListener('click', function () {
        if (!root) return;
        const next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        root.setAttribute('data-theme', next);
        localStorage.setItem('inbox-theme', next);
    });
})();
@if($activeConversation && $pollUrl)
(function () {
    const chatBox = document.getElementById('chatBox');
    const pollUrl = chatBox?.dataset.pollUrl;
    const presenceLine = document.getElementById('presence-line');
    const modeChip = document.getElementById('mode-chip');
    if (!chatBox || !pollUrl) return;

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function fmtDur(sec) {
        const s = Math.max(0, Math.floor(sec));
        const m = Math.floor(s / 60);
        const r = s % 60;
        return m + ':' + String(r).padStart(2, '0');
    }

    function renderMessage(m) {
        const out = m.direction === 'outgoing';
        const align = out ? 'justify-end' : 'justify-start';
        const bubble = out ? 'inbox-bubble inbox-bubble--out' : 'inbox-bubble inbox-bubble--in';
        const flexDir = out ? 'flex-row-reverse' : 'flex-row';
        const popPos = out ? 'right-0' : 'left-0';
        const mt = (m.media_type || '').toLowerCase();
        let inner = '';
        if (mt === 'image' && m.media_url) {
            inner += `<a href="${escapeHtml(m.media_url)}" target="_blank" rel="noopener" class="inbox-media-link mb-2 block overflow-hidden rounded-xl last:mb-0"><img src="${escapeHtml(m.media_url)}" alt="" class="inbox-media-img max-h-64 w-full object-cover" loading="lazy"></a>`;
        }
        if (mt === 'video' && m.media_url) {
            inner += `<div class="inbox-media-card mb-2 overflow-hidden rounded-xl last:mb-0"><video controls playsinline preload="metadata" class="inbox-video max-h-64 w-full bg-black/20" src="${escapeHtml(m.media_url)}"></video></div>`;
        }
        if (mt === 'audio' && m.media_url) {
            inner += `<div class="inbox-voice-row mb-2 flex items-center gap-2 rounded-xl bg-black/15 px-2 py-2 last:mb-0"><span class="inbox-voice-icon flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--inbox-accent)] text-[var(--inbox-accent-contrast)]"><svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/><path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/></svg></span><audio controls preload="metadata" class="h-9 min-w-0 max-w-full flex-1" src="${escapeHtml(m.media_url)}"></audio></div>`;
        }
        if (mt === 'document' && m.media_url) {
            inner += `<a href="${escapeHtml(m.media_url)}" target="_blank" rel="noopener" class="inbox-doc-card mb-2 flex items-center gap-3 rounded-xl border border-[var(--inbox-border)] bg-black/10 px-3 py-2.5 last:mb-0"><span class="text-2xl">📄</span><span class="min-w-0 flex-1 truncate text-sm font-medium">${escapeHtml(m.filename || 'Document')}</span><span class="shrink-0 text-xs font-semibold text-[var(--inbox-accent)]">Open</span></a>`;
        }
        if (m.media_url && !['image', 'video', 'audio', 'document'].includes(mt)) {
            inner += `<a href="${escapeHtml(m.media_url)}" target="_blank" rel="noopener" class="inbox-doc-card mb-2 flex items-center gap-3 rounded-xl border border-[var(--inbox-border)] bg-black/10 px-3 py-2.5 last:mb-0"><span class="text-2xl">📎</span><span class="min-w-0 flex-1 truncate text-sm font-medium">${escapeHtml(m.filename || mt || 'Attachment')}</span><span class="shrink-0 text-xs font-semibold text-[var(--inbox-accent)]">Open</span></a>`;
        }
        if (m.content && m.content !== '[Attachment]') {
            inner += `<div class="inbox-msg-text mt-1 whitespace-pre-wrap text-sm leading-relaxed first:mt-0">${escapeHtml(m.content).replace(/\n/g, '<br>')}</div>`;
        }
        if (!inner) inner = '<span class="text-xs opacity-70">(empty)</span>';
        let statusHtml = '';
        if (out) {
            if (m.status === 'failed') statusHtml = '<span class="rounded bg-red-500/90 px-1.5 py-0.5 text-[10px] font-bold text-white">Not delivered</span>';
            else if (m.status === 'sent') statusHtml = '<span class="opacity-60">✓</span>';
            if (m.source) statusHtml += ' <span class="opacity-60">· ' + escapeHtml(String(m.source).replace(/_/g, ' ')) + '</span>';
        }
        return `<div class="inbox-msg-row flex ${align} mb-3" data-message-id="${m.id}"><div class="inbox-msg-col max-w-[min(100%,28rem)] sm:max-w-[min(100%,32rem)]"><div class="flex items-start gap-1 ${flexDir}"><div class="inbox-msg-menu-wrap relative shrink-0 pt-1"><button type="button" class="inbox-msg-menu-btn rounded-md px-1 text-lg leading-none opacity-50 hover:opacity-100 inbox-text" aria-label="Message menu">⋮</button><div class="inbox-msg-popover absolute ${popPos} top-full z-30 mt-0.5 hidden min-w-[148px] rounded-lg border border-[var(--inbox-border)] bg-[var(--inbox-surface-strong)] py-1 text-sm shadow-lg" role="menu"><button type="button" class="inbox-msg-delete w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-black/10 dark:hover:bg-white/10" role="menuitem" data-message-id="${m.id}">Delete</button></div></div><div class="min-w-0 max-w-full flex-1"><div class="${bubble} shadow-sm">${inner}</div><div class="inbox-msg-meta mt-1 flex flex-wrap items-center gap-x-2 text-[11px] ${out ? 'justify-end' : 'justify-start'}"><span class="opacity-80">${escapeHtml(m.time || '')}</span>${statusHtml ? ' ' + statusHtml : ''}</div></div></div></div></div>`;
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function scrollBottom() {
        requestAnimationFrame(function () {
            chatBox.scrollTop = chatBox.scrollHeight;
            requestAnimationFrame(function () {
                chatBox.scrollTop = chatBox.scrollHeight;
            });
        });
    }

    let lastIds = new Set(@json($activeConversation->messages->pluck('id')->all()));

    async function poll() {
        try {
            const r = await fetch(pollUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!r.ok) return;
            const data = await r.json();
            if (presenceLine) {
                if (data.online) {
                    presenceLine.innerHTML = '<span class="font-medium text-emerald-600 dark:text-emerald-400">Online</span>';
                } else {
                    presenceLine.textContent = data.last_seen ? ('Last seen ' + data.last_seen) : 'Offline';
                }
            }
            if (modeChip && data.conversation_status) {
                const bot = data.conversation_status === 'bot';
                modeChip.textContent = bot ? 'Bot' : 'Human';
                modeChip.className = 'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ' + (bot ? 'inbox-chip-bot' : 'inbox-chip-human');
            }
            const msgs = data.messages || [];
            const serverIds = new Set(msgs.map(function (x) { return x.id; }));
            for (const id of Array.from(lastIds)) {
                if (!serverIds.has(id)) {
                    chatBox.querySelector('[data-message-id="' + id + '"]')?.remove();
                    lastIds.delete(id);
                }
            }
            let appended = false;
            for (const m of msgs) {
                if (!lastIds.has(m.id)) {
                    chatBox.querySelector('.max-w-3xl')?.insertAdjacentHTML('beforeend', renderMessage(m));
                    lastIds.add(m.id);
                    appended = true;
                }
            }
            if (appended) scrollBottom();
        } catch (e) {}
    }

    setInterval(poll, 2000);
    scrollBottom();
    window.addEventListener('load', function () { scrollBottom(); });

    chatBox.addEventListener('click', function (e) {
        const menuBtn = e.target.closest('.inbox-msg-menu-btn');
        if (menuBtn) {
            e.preventDefault();
            e.stopPropagation();
            const wrap = menuBtn.closest('.inbox-msg-menu-wrap');
            const pop = wrap && wrap.querySelector('.inbox-msg-popover');
            chatBox.querySelectorAll('.inbox-msg-popover').forEach(function (p) {
                if (p !== pop) p.classList.add('hidden');
            });
            if (pop) pop.classList.toggle('hidden');
            return;
        }
        const delBtn = e.target.closest('.inbox-msg-delete');
        if (delBtn) {
            e.preventDefault();
            e.stopPropagation();
            const id = parseInt(delBtn.getAttribute('data-message-id'), 10);
            const base = chatBox.dataset.inboxDeleteBase;
            if (!id || !base) return;
            if (!confirm('Delete this message?')) return;
            fetch(base + '/' + id, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            }).then(function (res) {
                if (res.ok) {
                    chatBox.querySelector('[data-message-id="' + id + '"]')?.remove();
                    lastIds.delete(id);
                    return;
                }
                return res.json().then(function (j) {
                    console.error('[inbox] delete failed', res.status, j);
                    alert((j && j.message) || 'Could not delete message (' + res.status + '). Check storage/logs/voice.log');
                });
            }).catch(function (err) {
                console.error('[inbox] delete', err);
                alert('Could not delete message. See console.');
            });
        }
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.inbox-msg-menu-wrap')) {
            document.querySelectorAll('.inbox-msg-popover').forEach(function (p) { p.classList.add('hidden'); });
        }
    });

    const micBtn = document.getElementById('mic-btn');
    const fileInput = document.getElementById('attachment-input');
    const recordPanel = document.getElementById('record-panel');
    const recordTimer = document.getElementById('record-timer');
    const recordFill = document.getElementById('record-progress-fill');
    const recordStopBtn = document.getElementById('record-stop-btn');
    const waveformEl = document.getElementById('record-waveform');
    const draftStrip = document.getElementById('composer-draft');
    const draftIcon = document.getElementById('draft-icon');
    const draftLabel = document.getElementById('draft-label');
    const draftName = document.getElementById('draft-name');
    const draftMeta = document.getElementById('draft-meta');
    const draftClear = document.getElementById('draft-clear');
    const replyForm = document.getElementById('reply-form');

    let mediaRecorder = null;
    let chunks = [];
    let recordChunksInterval = null;
    let recordUiInterval = null;
    let recordStartedAt = 0;
    let pendingVoiceBlob = null;
    let waveRafId = null;
    let audioContext = null;
    let analyserNode = null;
    const RECORD_BAR_CAP_SEC = 120;
    const WAVE_BAR_COUNT = 40;

    function stopWaveform() {
        if (waveRafId) {
            cancelAnimationFrame(waveRafId);
            waveRafId = null;
        }
        try {
            if (audioContext && audioContext.state !== 'closed') {
                audioContext.close();
            }
        } catch (e) {}
        audioContext = null;
        analyserNode = null;
    }

    function ensureWaveformBars() {
        if (!waveformEl || waveformEl.dataset.built === '1') return;
        waveformEl.innerHTML = '';
        for (let i = 0; i < WAVE_BAR_COUNT; i++) {
            const s = document.createElement('span');
            s.style.height = '3px';
            waveformEl.appendChild(s);
        }
        waveformEl.dataset.built = '1';
    }

    function startWaveform(stream) {
        stopWaveform();
        ensureWaveformBars();
        if (!waveformEl) return;
        try {
            const AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return;
            audioContext = new AC();
            analyserNode = audioContext.createAnalyser();
            analyserNode.fftSize = 256;
            const src = audioContext.createMediaStreamSource(stream);
            src.connect(analyserNode);
            const buf = new Uint8Array(analyserNode.frequencyBinCount);
            const bars = waveformEl.querySelectorAll('span');
            const n = bars.length || 1;
            function tick() {
                if (!analyserNode) return;
                analyserNode.getByteFrequencyData(buf);
                const step = Math.max(1, Math.floor(buf.length / n));
                for (let i = 0; i < n; i++) {
                    let sum = 0;
                    for (let j = 0; j < step; j++) sum += buf[Math.min(buf.length - 1, i * step + j)] || 0;
                    const avg = sum / step;
                    const h = Math.max(3, Math.min(42, avg * 1.35));
                    bars[i].style.height = h + 'px';
                }
                waveRafId = requestAnimationFrame(tick);
            }
            audioContext.resume().then(function () { tick(); });
        } catch (e) {}
    }

    function showDraft(icon, label, name, meta) {
        if (!draftStrip) return;
        draftIcon.textContent = icon;
        draftLabel.textContent = label;
        draftName.textContent = name;
        draftMeta.textContent = meta || '';
        draftStrip.classList.remove('hidden');
    }

    function hideDraft() {
        draftStrip?.classList.add('hidden');
        if (draftIcon) draftIcon.textContent = '📎';
        if (draftLabel) draftLabel.textContent = 'Ready to send';
        if (draftName) draftName.textContent = '';
        if (draftMeta) draftMeta.textContent = '';
    }

    function describeFile(f) {
        const t = f.type || '';
        let icon = '📎';
        let label = 'Attachment';
        if (t.startsWith('image/')) { icon = '🖼'; label = 'Image'; }
        else if (t.startsWith('video/')) { icon = '🎬'; label = 'Video'; }
        else if (t.startsWith('audio/')) { icon = '🎤'; label = 'Voice note'; }
        else if (t === 'application/pdf' || /\.pdf$/i.test(f.name)) { icon = '📄'; label = 'Document'; }
        const sz = f.size < 1024 ? f.size + ' B' : f.size < 1048576 ? (f.size / 1024).toFixed(1) + ' KB' : (f.size / 1048576).toFixed(1) + ' MB';
        return { icon: icon, label: label, sz: sz };
    }

    function updateDraftFromInput() {
        const f = fileInput?.files?.[0] || pendingVoiceBlob;
        if (!f) {
            hideDraft();
            return;
        }
        const d = describeFile(f);
        showDraft(d.icon, d.label, f.name, d.sz);
    }

    fileInput?.addEventListener('change', function () {
        pendingVoiceBlob = null;
        updateDraftFromInput();
        try { fileInput.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
    });

    draftClear?.addEventListener('click', function () {
        pendingVoiceBlob = null;
        if (fileInput) fileInput.value = '';
        hideDraft();
    });

    function showRecordUi() {
        recordPanel?.classList.remove('hidden');
        micBtn?.classList.add('inbox-composer-icon--recording');
        recordStartedAt = Date.now();
        if (recordTimer) recordTimer.textContent = '0:00';
        if (recordFill) recordFill.style.width = '0%';
        if (recordUiInterval) clearInterval(recordUiInterval);
        recordUiInterval = setInterval(function () {
            const sec = (Date.now() - recordStartedAt) / 1000;
            if (recordTimer) recordTimer.textContent = fmtDur(sec);
            if (recordFill) recordFill.style.width = Math.min(100, (sec / RECORD_BAR_CAP_SEC) * 100) + '%';
        }, 100);
    }

    function hideRecordUi() {
        stopWaveform();
        recordPanel?.classList.add('hidden');
        micBtn?.classList.remove('inbox-composer-icon--recording');
        if (recordUiInterval) {
            clearInterval(recordUiInterval);
            recordUiInterval = null;
        }
        if (recordFill) recordFill.style.width = '0%';
    }

    function stopRec(force) {
        if (recordChunksInterval) {
            clearInterval(recordChunksInterval);
            recordChunksInterval = null;
        }
        if (!mediaRecorder || mediaRecorder.state !== 'recording') {
            hideRecordUi();
            return;
        }
        if (!force && (Date.now() - recordStartedAt) < 250) {
            return;
        }
        mediaRecorder.stop();
    }

    async function startRec() {
        if (mediaRecorder) return;
        pendingVoiceBlob = null;
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            chunks = [];
            startWaveform(stream);
            const MR = window.MediaRecorder;
            const mime = MR.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus' : (MR.isTypeSupported('audio/webm') ? 'audio/webm' : '');
            mediaRecorder = mime ? new MR(stream, { mimeType: mime }) : new MR(stream);
            mediaRecorder.ondataavailable = function (e) { if (e.data && e.data.size) chunks.push(e.data); };
            mediaRecorder.onstop = function () {
                hideRecordUi();
                stream.getTracks().forEach(function (t) { t.stop(); });
                const blob = new Blob(chunks, { type: mediaRecorder.mimeType || 'audio/webm' });
                const ext = (blob.type || '').indexOf('mp4') >= 0 ? 'm4a' : 'webm';
                const voiceFile = new File([blob], 'voice-note.' + ext, { type: blob.type || 'audio/webm' });
                pendingVoiceBlob = null;
                if (fileInput) {
                    try {
                        const dt = new DataTransfer();
                        dt.items.add(voiceFile);
                        fileInput.files = dt.files;
                    } catch (e) {}
                }
                if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                    pendingVoiceBlob = voiceFile;
                }
                try {
                    fileInput?.dispatchEvent(new Event('change', { bubbles: true }));
                } catch (e) {}
                updateDraftFromInput();
                mediaRecorder = null;
            };
            showRecordUi();
            try {
                mediaRecorder.start(250);
            } catch (e) {
                mediaRecorder.start();
            }
            recordChunksInterval = setInterval(function () {
                if (mediaRecorder && mediaRecorder.state === 'recording') {
                    try { mediaRecorder.requestData(); } catch (e) {}
                }
            }, 1000);
        } catch (e) {
            stopWaveform();
            hideRecordUi();
            alert('Microphone access denied or not available. Use HTTPS and allow the mic for this site.');
        }
    }

    micBtn?.addEventListener('mousedown', function (e) { e.preventDefault(); startRec(); });
    micBtn?.addEventListener('touchstart', function (e) { e.preventDefault(); startRec(); }, { passive: false });
    window.addEventListener('mouseup', function () { stopRec(false); });
    window.addEventListener('touchend', function () { stopRec(false); });
    recordStopBtn?.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        stopRec(true);
    });

    const messageInput = document.getElementById('message-input');

    replyForm?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(replyForm);
        if (pendingVoiceBlob) {
            fd.delete('attachment');
            fd.append('attachment', pendingVoiceBlob, pendingVoiceBlob.name || 'voice-note.webm');
        }
        const msg = (messageInput && messageInput.value ? messageInput.value : '').trim();
        const hasFile = ((fileInput && fileInput.files && fileInput.files.length) || 0) > 0 || !!pendingVoiceBlob;
        if (!msg && !hasFile) {
            alert('Enter a message or attach a file.');
            return;
        }
        try {
            const res = await fetch(replyForm.action, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            });
            const data = await res.json().catch(function () { return {}; });
            if (!res.ok) {
                const errMsg = (data.errors && JSON.stringify(data.errors)) || data.message || ('HTTP ' + res.status);
                console.error('[inbox] reply failed', res.status, data);
                alert('Send failed: ' + errMsg + '. See storage/logs/voice.log for details.');
                return;
            }
            pendingVoiceBlob = null;
            if (fileInput) fileInput.value = '';
            hideDraft();
            if (messageInput) messageInput.value = '';
            if (data.message) {
                if (!lastIds.has(data.message.id)) {
                    chatBox.querySelector('.max-w-3xl')?.insertAdjacentHTML('beforeend', renderMessage(data.message));
                    lastIds.add(data.message.id);
                }
                scrollBottom();
            }
        } catch (err) {
            console.error('[inbox] reply', err);
            alert('Could not send. Check connection. Details in console.');
        }
    });
})();
@endif
</script>
@endpush

@endsection
