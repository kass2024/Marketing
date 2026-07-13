@extends('layouts.admin')

@section('title', 'WhatsApp accounts — Business Manager')

@section('content')
@php
    $tab = request('tab', 'phones');
    $defaultPhoneId = $connection?->whatsapp_phone_number_id;
    $ownerName = $detail['owner_business_info']['name']
        ?? $detail['on_behalf_of_business_info']['name']
        ?? $connection?->business_name
        ?? null;
    $openPanel = null;
    if (session('show_link_waba') || old('waba_id')) {
        $openPanel = 'link';
    } elseif (session('show_request_waba') || old('client_business_id')) {
        $openPanel = 'request';
    } elseif (session('show_create_waba') || old('waba_name')) {
        $openPanel = 'create';
    }
@endphp

<div class="w-full min-w-0" x-data="{
    tab: '{{ $tab }}',
    showAddPhone: {{ $pendingPhoneId || old('phone_number') ? 'true' : 'false' }},
    panel: @json($openPanel),
    addOpen: false,
    linkModal: {{ session('show_link_waba') || old('waba_id') ? 'true' : 'false' }},
    linkStep: 'phone',
    linkCountry: '1',
    linkPhone: '',
    linkCode: '',
    linkPhoneNumberId: '',
    linkWabaId: '',
    linkDisplayPhone: '',
    linkMatches: [],
    linkBusy: false,
    linkError: '',
    linkMessage: '',
    linkResendIn: 0,
    linkShowAdvanced: false,
    linkAdvancedId: '',
    openPanel(name) {
        this.panel = name;
        this.addOpen = false;
        this.showAddPhone = false;
        if (name === 'link') {
            this.linkModal = true;
            this.linkStep = 'phone';
            this.linkError = '';
            this.linkMessage = '';
            this.linkCode = '';
            this.linkMatches = [];
            this.linkShowAdvanced = false;
        }
    },
    closePanel() { this.panel = null; },
    closeLinkModal() {
        this.linkModal = false;
        this.panel = null;
        this.linkBusy = false;
        this.linkError = '';
    },
    async linkStart() {
        this.linkBusy = true;
        this.linkError = '';
        try {
            const res = await fetch(@js(route('admin.meta.whatsapp.link.phone')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                },
                body: JSON.stringify({
                    country_code: this.linkCountry,
                    phone_number: this.linkPhone,
                }),
            });
            const data = await res.json();
            if (!data.ok) {
                this.linkError = data.message || 'Could not start link.';
                return;
            }
            this.linkPhoneNumberId = data.phone_number_id || '';
            this.linkWabaId = data.waba_id || '';
            this.linkDisplayPhone = data.display_phone || this.linkPhone;
            this.linkMatches = data.matches || [];
            this.linkMessage = data.message || '';
            this.linkStep = data.step || 'code';
            this.linkResendIn = data.resend_after || 0;
            if (this.linkResendIn > 0) this.tickResend();
        } catch (e) {
            this.linkError = e.message || 'Network error';
        } finally {
            this.linkBusy = false;
        }
    },
    tickResend() {
        if (this.linkResendIn <= 0) return;
        setTimeout(() => {
            this.linkResendIn = Math.max(0, this.linkResendIn - 1);
            if (this.linkResendIn > 0) this.tickResend();
        }, 1000);
    },
    async linkVerify() {
        this.linkBusy = true;
        this.linkError = '';
        try {
            const res = await fetch(@js(route('admin.meta.whatsapp.link.verify')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                },
                body: JSON.stringify({
                    phone_number_id: this.linkPhoneNumberId,
                    code: this.linkCode,
                    waba_id: this.linkWabaId || null,
                }),
            });
            const data = await res.json();
            if (!data.ok) {
                this.linkError = data.message || 'Invalid code.';
                return;
            }
            this.linkMatches = data.matches || [];
            this.linkDisplayPhone = data.display_phone || this.linkDisplayPhone;
            this.linkMessage = data.message || '';
            this.linkStep = 'businesses';
        } catch (e) {
            this.linkError = e.message || 'Network error';
        } finally {
            this.linkBusy = false;
        }
    },
    async linkResend() {
        if (this.linkResendIn > 0 || !this.linkPhoneNumberId) return;
        this.linkBusy = true;
        this.linkError = '';
        try {
            const res = await fetch(@js(route('admin.meta.whatsapp.link.resend')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                },
                body: JSON.stringify({ phone_number_id: this.linkPhoneNumberId }),
            });
            const data = await res.json();
            if (!data.ok) {
                this.linkError = data.message || 'Could not resend.';
                return;
            }
            this.linkResendIn = data.resend_after || 30;
            this.tickResend();
        } catch (e) {
            this.linkError = e.message || 'Network error';
        } finally {
            this.linkBusy = false;
        }
    },
    async linkComplete(match) {
        this.linkBusy = true;
        this.linkError = '';
        try {
            const res = await fetch(@js(route('admin.meta.whatsapp.link.complete')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                },
                body: JSON.stringify({
                    waba_id: match.waba_id,
                    phone_number_id: match.phone_number_id || this.linkPhoneNumberId,
                }),
            });
            const data = await res.json();
            if (!data.ok) {
                this.linkError = data.message || 'Could not link account.';
                return;
            }
            window.location.href = data.redirect || @js(route('admin.meta.whatsapp.index'));
        } catch (e) {
            this.linkError = e.message || 'Network error';
            this.linkBusy = false;
        }
    },
}">

    <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-medium text-slate-500">Business Manager / Accounts</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">WhatsApp accounts</h1>
            <p class="mt-0.5 text-sm text-slate-600">
                Opens instantly from cache — refreshes from Meta in the background. Use <strong>Sync now</strong> to force a full refresh.
                @isset($lastSyncedAt)
                    <span class="text-slate-400">Last sync: {{ $lastSyncedAt }}</span>
                @endisset
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="post" action="{{ route('admin.meta.whatsapp.sync') }}">
                @csrf
                <input type="hidden" name="waba" value="{{ $selectedId }}">
                <button type="submit" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Sync now
                </button>
            </form>
            <a href="{{ route('admin.meta.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Connection</a>
            <div class="relative" @click.outside="addOpen = false">
                <button type="button" @click="addOpen = !addOpen"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-[#0866FF] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#0759DB]">
                    <span aria-hidden="true">+</span>
                    Add
                    <svg class="h-3.5 w-3.5 opacity-90" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <div x-show="addOpen" x-cloak x-transition
                     class="absolute right-0 z-40 mt-2 w-[22rem] overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl ring-1 ring-black/5">
                    <button type="button" @click="openPanel('create')"
                        class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-slate-50">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-lg font-semibold text-slate-700">+</span>
                        <span>
                            <span class="block text-sm font-semibold text-slate-900">Create a new WhatsApp Business account</span>
                            <span class="mt-0.5 block text-xs leading-snug text-slate-500">Create an account to connect to the WhatsApp Business Platform.</span>
                        </span>
                    </button>
                    <button type="button" @click="openPanel('request')"
                        class="flex w-full items-start gap-3 border-t border-slate-100 px-4 py-3 text-left hover:bg-slate-50">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a8.25 8.25 0 0115 0"/><path stroke-linecap="round" stroke-linejoin="round" d="M19 8l3 3m0 0l-3 3m3-3H12"/></svg>
                        </span>
                        <span>
                            <span class="block text-sm font-semibold text-slate-900">Request a WhatsApp Business account for a client</span>
                            <span class="mt-0.5 block text-xs leading-snug text-slate-500">Create a new account on behalf of another business</span>
                        </span>
                    </button>
                    <button type="button" @click="openPanel('link')"
                        class="flex w-full items-start gap-3 border-t border-slate-100 px-4 py-3 text-left hover:bg-slate-50">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#25D366]/15 text-[#075E54]">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.86 9.86 0 0012.04 2zm.01 1.67c2.2 0 4.26.86 5.82 2.42a8.21 8.21 0 012.41 5.83c0 4.54-3.7 8.24-8.24 8.24-1.48 0-2.93-.39-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 01-1.26-4.38c0-4.54 3.7-8.25 8.25-8.25zm4.52 10.67c-.22-.11-1.3-.64-1.5-.71-.2-.07-.35-.11-.5.11-.15.22-.57.71-.7.86-.13.15-.26.16-.48.05-.22-.11-.93-.34-1.77-1.09-.65-.58-1.1-1.3-1.23-1.52-.13-.22-.01-.34.1-.45.1-.1.22-.26.33-.39.11-.13.15-.22.22-.37.07-.15.04-.28-.02-.39-.05-.11-.5-1.2-.68-1.65-.18-.43-.36-.37-.5-.38h-.42c-.15 0-.39.06-.59.28-.2.22-.78.76-.78 1.85s.8 2.15.91 2.3c.11.15 1.57 2.4 3.8 3.36.53.23.95.37 1.27.47.53.17 1.02.15 1.4.09.43-.06 1.3-.53 1.48-1.04.18-.51.18-.95.13-1.04-.05-.09-.2-.15-.42-.26z"/></svg>
                        </span>
                        <span>
                            <span class="block text-sm font-semibold text-slate-900">Link a WhatsApp Business account</span>
                            <span class="mt-0.5 block text-xs leading-snug text-slate-500">Enter the WhatsApp number, verify the code, then choose the associated business.</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Create new WABA --}}
    <div x-show="panel === 'create'" x-cloak class="mb-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-slate-900">Create a new WhatsApp Business account</h3>
                <p class="mt-1 text-xs text-slate-600">Creates a WABA under your Business Manager (owned account), same as Meta’s “Create a new WhatsApp Business account”.</p>
            </div>
            <button type="button" @click="closePanel()" class="text-xs font-semibold text-slate-500">Close</button>
        </div>
        <form method="POST" action="{{ route('admin.meta.whatsapp.create') }}" class="mt-4 grid gap-3 sm:grid-cols-3">
            @csrf
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-slate-600">Account name</label>
                <input type="text" name="waba_name" value="{{ old('waba_name', $connection?->business_name ?? 'WhatsApp Business Account') }}" required
                    class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm"
                    placeholder="e.g. Parrot Canada WhatsApp">
                @error('waba_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">Currency</label>
                <select name="currency" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm">
                    @foreach(['USD','CAD','EUR','GBP','INR'] as $cur)
                        <option value="{{ $cur }}" @selected(old('currency', 'CAD') === $cur)>{{ $cur }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-3 flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-xl bg-[#0866FF] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#0759DB]">
                    Create account
                </button>
                <a href="https://business.facebook.com/latest/whatsapp_manager/accounts" target="_blank" rel="noopener"
                    class="text-xs font-semibold text-sky-700 hover:underline">Or open Meta Business Suite →</a>
            </div>
        </form>
    </div>

    {{-- Request WABA for a client --}}
    <div x-show="panel === 'request'" x-cloak class="mb-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-slate-900">Request a WhatsApp Business account for a client</h3>
                <p class="mt-1 text-xs text-slate-600">Creates a partner request on behalf of another business. The client must accept it in Meta Business Suite.</p>
            </div>
            <button type="button" @click="closePanel()" class="text-xs font-semibold text-slate-500">Close</button>
        </div>
        <form method="POST" action="{{ route('admin.meta.whatsapp.request') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600">Client Business Manager ID</label>
                <input type="text" name="client_business_id" value="{{ old('client_business_id') }}" required
                    class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 font-mono text-sm"
                    placeholder="Client BM / portfolio ID">
                @error('client_business_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600">WABA name for client</label>
                <input type="text" name="waba_name" value="{{ old('waba_name') }}" required
                    class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm"
                    placeholder="Client WhatsApp account name">
                @error('waba_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2 flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-xl bg-[#0866FF] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#0759DB]">
                    Send request
                </button>
                <a href="https://business.facebook.com/latest/whatsapp_manager/accounts" target="_blank" rel="noopener"
                    class="text-xs font-semibold text-sky-700 hover:underline">Open Meta partner flow →</a>
            </div>
        </form>
    </div>

    {{-- Link existing WABA (Meta-style: phone → code → businesses) --}}
    <div x-show="linkModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-slate-900/50" @click="closeLinkModal()"></div>
        <div class="relative flex w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="hidden w-44 shrink-0 items-center justify-center bg-gradient-to-b from-[#25D366] to-[#128C7E] p-6 sm:flex">
                <div class="text-center text-white">
                    <svg class="mx-auto h-16 w-16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.86 9.86 0 0012.04 2z"/>
                    </svg>
                    <p class="mt-3 text-xs font-semibold leading-snug opacity-90">WhatsApp Business</p>
                </div>
            </div>
            <div class="min-w-0 flex-1 p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="text-lg font-bold text-slate-900">Link WhatsApp Business Account</h3>
                    <button type="button" @click="closeLinkModal()" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <template x-if="linkError">
                    <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="linkError"></div>
                </template>

                {{-- Step: phone --}}
                <div x-show="linkStep === 'phone'" class="mt-4">
                    <p class="text-sm text-slate-600">Enter the phone number associated with your WhatsApp Business account.</p>
                    <label class="mt-4 block text-xs font-semibold text-slate-600">Phone number</label>
                    <div class="mt-1 flex gap-2">
                        <select x-model="linkCountry" class="w-28 rounded-xl border border-slate-200 bg-white px-2 py-2.5 text-sm">
                            <option value="1">CA / US +1</option>
                            <option value="44">UK +44</option>
                            <option value="91">IN +91</option>
                            <option value="250">RW +250</option>
                            <option value="254">KE +254</option>
                            <option value="233">GH +233</option>
                            <option value="234">NG +234</option>
                        </select>
                        <div class="relative min-w-0 flex-1">
                            <input type="tel" x-model="linkPhone" @keydown.enter.prevent="linkPhone.trim() && linkStart()"
                                   placeholder="(450) 367-5329"
                                   class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#0866FF] focus:ring-[#0866FF]">
                            <span x-show="linkPhone.replace(/\D/g,'').length >= 10" class="absolute right-3 top-1/2 -translate-y-1/2 text-emerald-500" aria-hidden="true">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            </span>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">
                        Connecting business assets lets you access more professional features. We’ll use info across connected assets to enhance your experience on our business products.
                    </p>
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-2">
                        <button type="button" class="text-xs font-semibold text-sky-700 hover:underline" @click="linkShowAdvanced = !linkShowAdvanced">
                            Advanced: link by WABA ID
                        </button>
                        <div class="flex gap-2">
                            <button type="button" @click="closeLinkModal()" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                            <button type="button" @click="linkStart()"
                                    :disabled="linkBusy || linkPhone.replace(/\D/g,'').length < 7"
                                    :class="(linkBusy || linkPhone.replace(/\D/g,'').length < 7) ? 'bg-[#0866FF]/50 cursor-not-allowed' : 'bg-[#0866FF] hover:bg-[#0759DB]'"
                                    class="inline-flex items-center gap-2 rounded-xl px-5 py-2 text-sm font-semibold text-white">
                                <span x-show="linkBusy" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                                Continue
                            </button>
                        </div>
                    </div>
                    <div x-show="linkShowAdvanced" x-cloak class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <form method="POST" action="{{ route('admin.meta.whatsapp.link') }}" class="flex flex-col gap-2 sm:flex-row sm:items-end">
                            @csrf
                            <div class="min-w-0 flex-1">
                                <label class="block text-xs font-semibold text-slate-600">WhatsApp Business Account ID</label>
                                <input type="text" name="waba_id" x-model="linkAdvancedId" required
                                       class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 font-mono text-sm"
                                       placeholder="e.g. 1343806717941992">
                            </div>
                            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Link by ID</button>
                        </form>
                    </div>
                </div>

                {{-- Step: verification code --}}
                <div x-show="linkStep === 'code'" x-cloak class="mt-4">
                    <p class="text-sm text-slate-600">
                        A verification code was sent to your WhatsApp Business App for
                        <strong x-text="linkDisplayPhone"></strong>.
                        Enter it here to finish adding your WhatsApp account.
                    </p>
                    <label class="mt-4 block text-xs font-semibold text-slate-600">Verification code</label>
                    <div class="relative mt-1">
                        <input type="text" inputmode="numeric" maxlength="5" x-model="linkCode"
                               @input="linkCode = linkCode.replace(/\D/g,'').slice(0,5)"
                               @keydown.enter.prevent="linkCode.length === 5 && linkVerify()"
                               class="w-full rounded-xl border border-slate-200 px-4 py-2.5 pr-14 text-sm tracking-widest focus:border-[#0866FF] focus:ring-[#0866FF]"
                               placeholder="•••••">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" x-text="linkCode.length + '/5'"></span>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">
                        <template x-if="linkResendIn > 0">
                            <span>Didn’t receive your code? You can request a new code in <strong x-text="linkResendIn"></strong> seconds.</span>
                        </template>
                        <template x-if="linkResendIn <= 0">
                            <button type="button" class="font-semibold text-[#0866FF] hover:underline" @click="linkResend()" :disabled="linkBusy">Resend code</button>
                        </template>
                    </p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="linkStep = 'phone'" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Back</button>
                        <button type="button" @click="linkVerify()"
                                :disabled="linkBusy || linkCode.length !== 5"
                                :class="(linkBusy || linkCode.length !== 5) ? 'bg-[#0866FF]/50 cursor-not-allowed' : 'bg-[#0866FF] hover:bg-[#0759DB]'"
                                class="inline-flex items-center gap-2 rounded-xl px-5 py-2 text-sm font-semibold text-white">
                            <span x-show="linkBusy" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                            Continue
                        </button>
                    </div>
                </div>

                {{-- Step: associated businesses --}}
                <div x-show="linkStep === 'businesses'" x-cloak class="mt-4">
                    <p class="text-sm text-slate-600" x-text="linkMessage || 'Select the associated business to link to this portfolio.'"></p>
                    <ul class="mt-4 max-h-64 space-y-2 overflow-y-auto">
                        <template x-for="m in linkMatches" :key="m.waba_id + '-' + m.phone_number_id">
                            <li>
                                <button type="button" @click="linkComplete(m)" :disabled="linkBusy"
                                        class="flex w-full items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 text-left hover:border-[#0866FF] hover:bg-sky-50 disabled:opacity-60">
                                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#25D366]/15 text-[#075E54]">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.86 9.86 0 0012.04 2z"/></svg>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-slate-900" x-text="m.business_name || m.waba_name || 'WhatsApp Business Account'"></span>
                                        <span class="mt-0.5 block truncate text-xs text-slate-500" x-text="'WABA ' + m.waba_id"></span>
                                        <span class="mt-0.5 block text-xs text-slate-400" x-text="(m.verified_name ? m.verified_name + ' · ' : '') + (m.display_phone_number || '')"></span>
                                        <span x-show="m.business_verification_status" class="mt-1 inline-block rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700"
                                              x-text="'Business verification: ' + (m.business_verification_status || '')"></span>
                                    </span>
                                </button>
                            </li>
                        </template>
                    </ul>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="linkStep = linkPhoneNumberId ? 'code' : 'phone'" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Back</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Keep panel hook for + Add menu (opens modal) --}}
    <div x-show="false" x-cloak></div>

    @foreach (['success','error','info'] as $msg)
        @if(session($msg))
            <div class="mb-4 rounded-xl border px-4 py-3 text-sm font-medium
                @if($msg === 'success') border-emerald-200 bg-emerald-50 text-emerald-800
                @elseif($msg === 'error') border-red-200 bg-red-50 text-red-800
                @else border-blue-200 bg-blue-50 text-blue-800
                @endif">
                {{ session($msg) }}
            </div>
        @endif
    @endforeach

    @if($error)
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-semibold">Could not fully load from Meta</p>
            <p class="mt-1">{{ $error }}</p>
            <p class="mt-2 text-xs">Ensure <code class="rounded bg-amber-100 px-1">whatsapp_business_management</code> is granted, set <code class="rounded bg-amber-100 px-1">META_BUSINESS_ID</code> if needed, then Sync from Meta.</p>
        </div>
    @endif

    {{-- Master / detail — min-w-0 prevents narrow overlap bugs --}}
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start">
        <aside class="w-full shrink-0 xl:w-80">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-4 py-3">
                    <p class="text-sm font-bold text-slate-900">WhatsApp accounts <span class="font-normal text-slate-500">({{ count($accounts) }})</span></p>
                    <form method="GET" action="{{ route('admin.meta.whatsapp.index') }}" class="mt-2">
                        <input type="search" name="q" value="{{ $search }}" placeholder="Search by name or ID"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        @if($selectedId)<input type="hidden" name="waba" value="{{ $selectedId }}">@endif
                    </form>
                </div>
                <ul class="max-h-[70vh] divide-y divide-slate-100 overflow-y-auto">
                    @forelse($accounts as $account)
                        <li>
                            <a href="{{ route('admin.meta.whatsapp.index', ['waba' => $account['id'], 'q' => $search, 'tab' => 'phones']) }}"
                               class="block px-4 py-3 transition hover:bg-slate-50 {{ $selectedId === $account['id'] ? 'bg-sky-50' : '' }}">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $account['name'] }}</p>
                                <p class="mt-0.5 truncate font-mono text-[11px] text-slate-500">{{ $account['id'] }}</p>
                                <p class="mt-0.5 text-[11px] text-slate-400">WhatsApp Business Account</p>
                            </a>
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center text-sm text-slate-500">
                            No WhatsApp accounts found.
                            <button type="button" @click="openPanel('link')" class="mt-2 block w-full font-semibold text-sky-700">Link an existing WABA</button>
                            <form method="post" action="{{ route('admin.meta.whatsapp.sync') }}" class="mt-2">
                                @csrf
                                <button type="submit" class="font-semibold text-sky-700">Or sync from Meta</button>
                            </form>
                        </li>
                    @endforelse
                </ul>
            </div>
        </aside>

        <section class="min-w-0 flex-1">
            @if(!$detail)
                <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-12 text-center text-slate-500">
                    Select a WhatsApp Business Account to manage phone numbers.
                </div>
            @else
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <h2 class="truncate text-xl font-bold text-slate-900">{{ $detail['name'] ?? 'WhatsApp account' }}</h2>
                                <p class="mt-1 text-sm text-slate-600">
                                    ID:
                                    <span class="font-mono text-sky-700">{{ $detail['id'] }}</span>
                                    @if($ownerName)
                                        <span class="text-slate-400">·</span>
                                        Owned by: {{ $ownerName }}
                                    @endif
                                </p>
                            </div>
                            <button type="button" @click="showAddPhone = true; tab = 'phones'"
                                class="shrink-0 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                                Add phone number
                            </button>
                        </div>

                        <nav class="mt-5 flex gap-1 border-b border-slate-100">
                            @foreach(['summary' => 'Summary', 'phones' => 'Phone numbers'] as $key => $label)
                                <button type="button" @click="tab = '{{ $key }}'"
                                    class="relative -mb-px px-4 py-2.5 text-sm font-semibold transition"
                                    :class="tab === '{{ $key }}' ? 'border-b-2 border-sky-600 text-sky-800' : 'text-slate-500 hover:text-slate-800'">
                                    {{ $label }}
                                    @if($key === 'phones')
                                        <span class="ml-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">{{ count($phones) }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </nav>
                    </div>

                    {{-- Summary --}}
                    <div class="p-5 sm:p-6" x-show="tab === 'summary'" x-cloak>
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Business information</p>
                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                                <p class="text-xs font-semibold text-slate-500">Currency</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $detail['currency'] ?? '—' }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                                <p class="text-xs font-semibold text-slate-500">Time zone</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $detail['timezone_id'] ?? '—' }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                                <p class="text-xs font-semibold text-slate-500">Account review</p>
                                @php $st = strtoupper((string) ($detail['account_review_status'] ?? '')); @endphp
                                <p class="mt-1 flex items-center gap-2 text-sm font-semibold text-slate-900">
                                    <span class="h-2 w-2 rounded-full {{ $st === 'APPROVED' ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
                                    {{ $detail['account_review_status'] ?? 'Unknown' }}
                                </p>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                                <p class="text-xs font-semibold text-slate-500">Business verification</p>
                                @php $bv = strtoupper((string) ($detail['business_verification_status'] ?? '')); @endphp
                                <p class="mt-1 flex items-center gap-2 text-sm font-semibold text-slate-900">
                                    <span class="h-2 w-2 rounded-full {{ str_contains($bv, 'VERIF') ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                    {{ $detail['business_verification_status'] ?? 'Not available' }}
                                </p>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 sm:col-span-2">
                                <p class="text-xs font-semibold text-slate-500">Phone numbers on this WABA</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ count($phones) }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Phones --}}
                    <div class="p-5 sm:p-6" x-show="tab === 'phones'" x-cloak>
                        <div x-show="showAddPhone" class="mb-6 rounded-2xl border border-[#25D366]/30 bg-[#25D366]/5 p-5">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-sm font-bold text-slate-900">Add phone number to this WABA</h3>
                                <button type="button" @click="showAddPhone = false" class="text-xs font-semibold text-slate-500">Close</button>
                            </div>
                            <form method="POST" action="{{ route('admin.meta.whatsapp.phones.add') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                                @csrf
                                <input type="hidden" name="waba_id" value="{{ $detail['id'] }}">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-slate-600">Verified display name</label>
                                    <input type="text" name="verified_name" value="{{ old('verified_name', $connection?->business_name ?? $detail['name'] ?? '') }}" required
                                        class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm"
                                        placeholder="Business name shown on WhatsApp">
                                    @error('verified_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-slate-600">Phone number (international)</label>
                                    <input type="text" name="phone_number" value="{{ old('phone_number') }}" required
                                        class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm"
                                        placeholder="+1 431 301 4019">
                                    @error('phone_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <button type="submit" class="rounded-xl bg-[#075E54] px-5 py-2.5 text-sm font-semibold text-white">
                                        Add &amp; send verification SMS
                                    </button>
                                </div>
                            </form>
                        </div>

                        @if($pendingPhoneId)
                            <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-5">
                                <h3 class="text-sm font-bold text-amber-900">Verify phone number</h3>
                                <p class="mt-1 text-xs text-amber-800">Enter the code Meta sent by SMS, then we register the number for Cloud API.</p>
                                <form method="POST" action="{{ route('admin.meta.whatsapp.phones.verify') }}" class="mt-3 flex flex-wrap items-end gap-3">
                                    @csrf
                                    <input type="hidden" name="waba_id" value="{{ $detail['id'] }}">
                                    <input type="hidden" name="phone_number_id" value="{{ $pendingPhoneId }}">
                                    <div>
                                        <label class="block text-xs font-semibold text-amber-900">SMS code</label>
                                        <input type="text" name="code" required
                                            class="mt-1 w-40 rounded-xl border border-amber-200 px-3 py-2 text-sm"
                                            placeholder="123456">
                                        @error('code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <button type="submit" class="rounded-xl bg-amber-700 px-4 py-2 text-sm font-semibold text-white">Verify &amp; register</button>
                                </form>
                                <form method="POST" action="{{ route('admin.meta.whatsapp.phones.resend') }}" class="mt-2">
                                    @csrf
                                    <input type="hidden" name="waba_id" value="{{ $detail['id'] }}">
                                    <input type="hidden" name="phone_number_id" value="{{ $pendingPhoneId }}">
                                    <button type="submit" class="text-xs font-semibold text-amber-900 underline">Resend SMS code</button>
                                </form>
                            </div>
                        @endif

                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="min-w-full table-fixed text-left text-sm">
                                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="w-[28%] px-4 py-3">Phone number</th>
                                        <th class="w-[24%] px-4 py-3">Name</th>
                                        <th class="w-[14%] px-4 py-3">Status</th>
                                        <th class="w-[14%] px-4 py-3">Quality</th>
                                        <th class="w-[20%] px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($phones as $phone)
                                        @php
                                            $isVerified = ! empty($phone['verified'])
                                                || strtoupper((string) ($phone['code_verification_status'] ?? '')) === 'VERIFIED'
                                                || trim((string) ($phone['verified_name'] ?? '')) !== '';
                                            $cv = strtoupper((string) ($phone['code_verification_status'] ?? ''));
                                            $st = strtoupper((string) ($phone['status'] ?? ''));
                                            $online = in_array($st, ['CONNECTED', 'ONLINE', 'AVAILABLE'], true) || $isVerified;
                                        @endphp
                                        <tr class="{{ $defaultPhoneId === $phone['id'] ? 'bg-emerald-50/40' : '' }}">
                                            <td class="px-4 py-3 align-top">
                                                <p class="font-mono text-sm font-semibold text-slate-900">{{ $phone['display_phone_number'] ?: '—' }}</p>
                                                @if($defaultPhoneId === $phone['id'])
                                                    <span class="mt-1 inline-block rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">Default for ads</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 align-top text-slate-700">{{ $phone['verified_name'] ?: '—' }}</td>
                                            <td class="px-4 py-3 align-top">
                                                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $isVerified ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800' }}">
                                                    <span class="h-1.5 w-1.5 rounded-full {{ $isVerified ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
                                                    {{ $isVerified ? 'Verified' : ($phone['status'] ?: 'Pending') }}
                                                </span>
                                                @if(! $isVerified && $cv !== '' && $cv !== 'VERIFIED')
                                                    <p class="mt-1 text-[10px] text-slate-500">Needs SMS verify ({{ $phone['code_verification_status'] }})</p>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 align-top text-slate-600">{{ $phone['quality_rating'] ?? '—' }}</td>
                                            <td class="px-4 py-3 align-top text-right">
                                                <div class="flex flex-wrap justify-end gap-2">
                                                    @if(! $isVerified)
                                                        <form method="POST" action="{{ route('admin.meta.whatsapp.phones.resend') }}">
                                                            @csrf
                                                            <input type="hidden" name="waba_id" value="{{ $detail['id'] }}">
                                                            <input type="hidden" name="phone_number_id" value="{{ $phone['id'] }}">
                                                            <button type="submit" class="text-xs font-semibold text-sky-700">Send code</button>
                                                        </form>
                                                    @endif
                                                    @if($defaultPhoneId !== $phone['id'])
                                                        <form method="POST" action="{{ route('admin.meta.whatsapp.phones.default') }}">
                                                            @csrf
                                                            <input type="hidden" name="waba_id" value="{{ $detail['id'] }}">
                                                            <input type="hidden" name="phone_number_id" value="{{ $phone['id'] }}">
                                                            <button type="submit" class="text-xs font-semibold text-slate-700">Set default</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-4 py-12 text-center text-slate-500">
                                                No phone numbers on this account yet.
                                                <button type="button" @click="showAddPhone = true" class="mt-2 block w-full font-semibold text-[#075E54]">Add your first number</button>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">Numbers always sync into Ad Studio when you open Ad Studio or tap Refresh on the WhatsApp step.</p>
                    </div>
                </div>
            @endif
        </section>
    </div>
</div>
@endsection
