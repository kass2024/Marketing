@extends('layouts.admin')

@section('title', 'Creative builder')

@section('content')
@php
    $r = $reuse;
    $d = $r?->builder_inputs ?? [];
    $defaultPhone = $r?->whatsapp_phone_number ?? $connection?->whatsapp_phone_number ?? '';
    $defaultChatUrl = $r?->whatsapp_chat_url ?? $r?->whatsapp_fallback_url ?? '';
    $initialCampaignId = old('campaign_id', $selectedCampaign?->id ?? $r?->campaign_id);
    $initialAdsetId = old('adset_id', $selectedAdset?->id ?? $r?->adset_id);
@endphp

<div class="mx-auto max-w-[1600px] space-y-6" x-data="creativeBuilder(@js([
    'templates' => $templates,
    'defaultPhone' => $defaultPhone,
    'defaultChatUrl' => $defaultChatUrl,
    'initialCampaignId' => $initialCampaignId,
    'initialAdsetId' => $initialAdsetId,
    'initialContext' => $context,
    'initialAdsets' => $selectedCampaign ? app(\App\Services\Meta\CreativeContextResolver::class)->adsetsPayload($selectedCampaign) : [],
    'placementKeys' => array_keys($placementOptions),
    'reuse' => $r ? [
        'service_name' => $r->service_name,
        'campaign_goal' => $r->campaign_goal,
        'target_audience' => $r->target_audience,
        'pain_point' => $r->pain_point,
        'main_benefit' => $r->main_benefit,
        'offer_discount' => $r->offer_discount,
        'template_key' => $r->template_key,
        'primary_text' => $r->body,
        'headline' => $r->headline,
        'description' => $r->description,
        'whatsapp_prefill_message' => $r->whatsapp_prefill_message,
        'placements' => $r->placements ?? array_keys($placementOptions),
    ] : [],
]))" x-init="init()">

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl">Dynamic creative builder</h1>
            <p class="mt-1 text-sm text-slate-600">Link to an existing campaign & ad set — copy, placements, and audience load dynamically.</p>
        </div>
        <a href="{{ route('admin.creatives.index') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Creative library</a>
    </div>

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if($reusableCreatives->isNotEmpty())
    <div class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="text-sm font-semibold text-slate-700">Reuse saved creative</p>
        <div class="mt-2 flex flex-wrap gap-2">
            @foreach($reusableCreatives as $c)
                <a href="{{ route('admin.creatives.builder', ['reuse' => $c->id]) }}" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-xander-navy hover:text-white">
                    {{ $c->name }} @if($c->ab_variant)<span class="opacity-70">({{ $c->ab_variant }})</span>@endif
                </a>
            @endforeach
        </div>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.creatives.builder.store') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-5">
        @csrf

        <div class="xl:col-span-3 space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

            {{-- Templates --}}
            <div>
                <label class="block text-sm font-semibold text-slate-800">Ready-made template</label>
                <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($templates as $key => $tpl)
                        <button type="button" @click="applyTemplate('{{ $key }}')"
                            class="rounded-xl border border-slate-200 p-3 text-left text-sm transition hover:border-xander-navy hover:bg-slate-50"
                            :class="form.template_key === '{{ $key }}' ? 'border-xander-navy ring-2 ring-xander-navy/20' : ''">
                            <span class="text-lg">{{ $tpl['icon'] }}</span>
                            <span class="mt-1 block font-semibold">{{ $tpl['label'] }}</span>
                        </button>
                    @endforeach
                </div>
                <input type="hidden" name="template_key" x-model="form.template_key">
            </div>

            {{-- Linked campaign & ad set --}}
            <div class="rounded-xl border border-xander-navy/20 bg-slate-50 p-4">
                <p class="text-sm font-semibold text-slate-800">Linked campaign & ad set</p>
                <p class="mt-1 text-xs text-slate-500">Select an existing campaign and ad set — fields below auto-fill from their targeting and objective.</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold">Campaign</label>
                        <select name="campaign_id" x-model="selectedCampaignId" @change="onCampaignChange()" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5">
                            <option value="">Select campaign</option>
                            @foreach($campaigns as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->adsets_count ?? 0 }} ad sets)</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold">Ad set</label>
                        <select name="adset_id" x-model="selectedAdsetId" @change="onAdsetChange()" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5" :disabled="!adsets.length">
                            <option value="">Select ad set</option>
                            <template x-for="a in adsets" :key="a.id">
                                <option :value="a.id" x-text="a.name + (a.meta_synced ? ' ✓ Meta' : ' (local)')"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div x-show="linkedContext.adset_name" class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-lg bg-white p-3 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase text-slate-500">Objective</p>
                        <p class="mt-1 font-medium" x-text="linkedContext.campaign_objective || '—'"></p>
                    </div>
                    <div class="rounded-lg bg-white p-3 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase text-slate-500">Ad set budget</p>
                        <p class="mt-1 font-medium" x-text="linkedContext.adset_daily_budget ? (linkedContext.adset_daily_budget / 100).toFixed(2) + '/day' : '—'"></p>
                    </div>
                    <div class="rounded-lg bg-white p-3 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase text-slate-500">Meta sync</p>
                        <p class="mt-1 font-medium" :class="linkedContext.adset_meta_synced ? 'text-emerald-700' : 'text-amber-700'" x-text="linkedContext.adset_meta_synced ? 'Campaign & ad set on Meta' : 'Ad set not on Meta yet'"></p>
                    </div>
                    <div class="rounded-lg bg-white p-3 ring-1 ring-slate-200 sm:col-span-2">
                        <p class="text-xs font-semibold uppercase text-slate-500">Audience (from ad set)</p>
                        <p class="mt-1 text-slate-700" x-text="linkedContext.target_audience || '—'"></p>
                    </div>
                    <div class="rounded-lg bg-white p-3 ring-1 ring-slate-200">
                        <p class="text-xs font-semibold uppercase text-slate-500">Existing</p>
                        <p class="mt-1" x-text="(linkedContext.existing_creatives_count || 0) + ' creatives · ' + (linkedContext.existing_ads_count || 0) + ' ads'"></p>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-semibold">Facebook Page</label>
                    <select name="page_id" x-model="form.page_id" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5">
                        @foreach($pages as $page)
                            <option value="{{ $page['id'] }}">{{ $page['name'] }}</option>
                        @endforeach
                        @if(empty($pages) && $connection?->page_id)
                            <option value="{{ $connection->page_id }}">{{ $connection->page_name ?? $connection->page_id }}</option>
                        @endif
                    </select>
                </div>
            </div>

            {{-- Core inputs --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold">1. Service / product name</label>
                    <input type="text" name="service_name" x-model="form.service_name" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5">
                </div>
                <div>
                    <label class="block text-sm font-semibold">2. Campaign goal</label>
                    <input type="text" name="campaign_goal" x-model="form.campaign_goal" list="goal-list" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5">
                    <datalist id="goal-list">@foreach($goals as $g)<option value="{{ $g }}">@endforeach</datalist>
                </div>
                <div>
                    <label class="block text-sm font-semibold">3. Target audience</label>
                    <input type="text" name="target_audience" x-model="form.target_audience" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5">
                </div>
                <div>
                    <label class="block text-sm font-semibold">4. Pain point</label>
                    <textarea name="pain_point" x-model="form.pain_point" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold">5. Main benefit</label>
                    <textarea name="main_benefit" x-model="form.main_benefit" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5"></textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold">6. Offer / discount</label>
                    <input type="text" name="offer_discount" x-model="form.offer_discount" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5">
                </div>
            </div>

            {{-- A/B tabs --}}
            <div>
                <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-2">
                    <template x-for="v in ['A','B','C']" :key="v">
                        <button type="button" @click="activeVariant = v"
                            class="rounded-lg px-4 py-2 text-sm font-semibold"
                            :class="activeVariant === v ? 'bg-xander-navy text-white' : 'bg-slate-100 text-slate-600'"
                            x-text="v === 'A' ? 'Creative A — Benefits' : (v === 'B' ? 'Creative B — Urgency' : 'Creative C — Trust')"></button>
                    </template>
                    <button type="button" @click="generateAll()" class="ml-auto rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Auto-generate all</button>
                </div>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold">7. Primary text</label>
                        <textarea name="primary_text" x-model="variants[activeVariant].primary_text" rows="4" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5"></textarea>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold">8. Headline</label>
                            <input type="text" name="headline" x-model="variants[activeVariant].headline" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold">9. Description</label>
                            <input type="text" name="description" x-model="variants[activeVariant].description" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5">
                        </div>
                    </div>
                </div>

                <template x-for="v in ['A','B','C']" :key="'hidden-'+v">
                    <input type="hidden" :name="'variant_'+v.toLowerCase()+'_primary'" :value="variants[v].primary_text">
                    <input type="hidden" :name="'variant_'+v.toLowerCase()+'_headline'" :value="variants[v].headline">
                    <input type="hidden" :name="'variant_'+v.toLowerCase()+'_description'" :value="variants[v].description">
                    <input type="hidden" :name="'variant_'+v.toLowerCase()+'_whatsapp'" :value="variants[v].whatsapp_prefill_message">
                </template>
                <input type="hidden" name="active_variant" x-model="activeVariant">
            </div>

            {{-- CTA & WhatsApp --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-semibold">10. CTA button</label>
                    <select name="call_to_action" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5">
                        <option value="WHATSAPP_MESSAGE">WhatsApp Message</option>
                        <option value="SEND_MESSAGE">Send Message</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold">WhatsApp chat link (any wa.me URL)</label>
                    <input type="text" name="whatsapp_chat_url" x-model="form.whatsapp_chat_url"
                        placeholder="https://wa.me/14389009784?text=Hello%20I%20am%20interested"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 font-mono text-sm">
                    <p class="mt-1 text-xs text-slate-500">Paste any WhatsApp link — wa.me, api.whatsapp.com — or leave blank and use phone below.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold">Or phone number (digits, international)</label>
                    <input type="text" name="whatsapp_phone_number" x-model="form.whatsapp_phone_number"
                        placeholder="14389009784"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-semibold">11. WhatsApp prefilled message</label>
                    <textarea name="whatsapp_prefill_message" x-model="variants[activeVariant].whatsapp_prefill_message" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5" placeholder="Added to link if not already in URL"></textarea>
                    <p class="mt-1 text-xs text-slate-500 break-all font-mono" x-show="waLink" x-text="'Final link: ' + waLink"></p>
                </div>
            </div>

            {{-- Media --}}
            <div>
                <label class="block text-sm font-semibold">12. Image / video upload</label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" @change="previewMedia($event)" class="mt-1 w-full rounded-xl border border-dashed border-slate-300 p-4">
                <input type="file" name="video" accept="video/mp4,video/quicktime" class="mt-2 w-full rounded-xl border border-dashed border-slate-300 p-3 text-sm">
                <p class="mt-1 text-xs text-slate-500">Image: JPG/PNG/WebP, min 600×600, max 4 MB. Square or 4:5 recommended.</p>
            </div>

            {{-- Placements --}}
            <div>
                <label class="block text-sm font-semibold">13. Placement selection</label>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach($placementOptions as $key => $pl)
                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <input type="checkbox" name="placements[]" value="{{ $key }}" x-model="form.placements" class="rounded">
                            {{ $pl['label'] }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-4 border-t border-slate-200 pt-4">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="create_ab_variants" value="1" checked class="rounded"> Save A/B/C variants</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="publish_ads" value="1" checked class="rounded"> Create ads on linked ad set (Meta)</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_reusable" value="1" checked class="rounded"> Reusable in future campaigns</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="sync_meta" value="1" checked class="rounded"> Sync creative to Meta</label>
                <select name="ad_status" class="rounded-lg border border-slate-200 px-2 py-1 text-sm">
                    <option value="PAUSED">Publish ads as PAUSED</option>
                    <option value="ACTIVE">Publish ads as ACTIVE</option>
                </select>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="button" @click="runValidation()" class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold">Validate before publish</button>
                <button type="submit" class="rounded-xl bg-xander-navy px-6 py-2.5 text-sm font-semibold text-white">Save creative(s)</button>
            </div>
            <div x-show="validationErrors.length" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <template x-for="e in validationErrors" :key="e.field">
                    <p x-text="e.message + ' — ' + e.fix"></p>
                </template>
            </div>
        </div>

        {{-- Previews --}}
        <div class="xl:col-span-2 space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-800">14. Facebook preview</h3>
                <div class="mt-3 max-w-sm rounded-xl border border-slate-200 bg-white overflow-hidden">
                    <div class="flex items-center gap-2 p-3 text-sm">
                        <div class="h-8 w-8 rounded-full bg-blue-600"></div>
                        <span class="font-semibold">Your Page</span>
                    </div>
                    <p class="px-3 pb-2 text-sm text-slate-700" x-text="variants[activeVariant].primary_text || 'Primary text…'"></p>
                    <img :src="previewImage" x-show="previewImage" class="w-full object-cover max-h-64" alt="">
                    <div class="border-t border-slate-100 p-3">
                        <p class="text-xs text-slate-500 uppercase">Sponsored</p>
                        <p class="font-semibold text-sm" x-text="variants[activeVariant].headline || 'Headline'"></p>
                        <p class="text-xs text-slate-500" x-text="variants[activeVariant].description"></p>
                        <button type="button" class="mt-2 w-full rounded-lg bg-[#25D366] py-2 text-sm font-semibold text-white">WhatsApp</button>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-800">15. Instagram preview</h3>
                <div class="mt-3 max-w-sm rounded-xl border border-slate-200 overflow-hidden">
                    <div class="flex items-center gap-2 p-3 text-sm border-b">
                        <div class="h-8 w-8 rounded-full bg-gradient-to-tr from-yellow-400 via-pink-500 to-purple-600 p-0.5"><div class="h-full w-full rounded-full bg-white"></div></div>
                        <span class="font-semibold">yourbrand</span>
                    </div>
                    <img :src="previewImage" x-show="previewImage" class="w-full aspect-square object-cover" alt="">
                    <div class="p-3 text-sm">
                        <p x-text="variants[activeVariant].primary_text"></p>
                        <p class="mt-1 font-semibold" x-text="variants[activeVariant].headline"></p>
                        <button type="button" class="mt-2 rounded-lg bg-[#25D366] px-4 py-1.5 text-xs font-semibold text-white">Send message</button>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-bold text-slate-800">16. WhatsApp chat preview</h3>
                <div class="mt-3 max-w-sm rounded-xl bg-[#e5ddd5] p-4">
                    <div class="ml-auto max-w-[85%] rounded-lg bg-[#dcf8c6] p-3 text-sm shadow" x-text="variants[activeVariant].whatsapp_prefill_message || 'Prefilled message…'"></div>
                    <p class="mt-3 text-center text-xs text-slate-600 break-all" x-text="waLink"></p>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function creativeBuilder(config) {
    const templates = config.templates || {};
    const reuse = config.reuse || {};
    const ctx = config.initialContext || {};
    return {
        selectedCampaignId: String(config.initialCampaignId || ''),
        selectedAdsetId: String(config.initialAdsetId || ''),
        adsets: config.initialAdsets || [],
        linkedContext: ctx,
        form: {
            template_key: reuse.template_key || '',
            service_name: reuse.service_name || ctx.service_name || '',
            campaign_goal: reuse.campaign_goal || ctx.campaign_goal || '',
            target_audience: reuse.target_audience || ctx.target_audience || '',
            pain_point: reuse.pain_point || '',
            main_benefit: reuse.main_benefit || '',
            offer_discount: reuse.offer_discount || '',
            whatsapp_phone_number: config.defaultPhone || ctx.whatsapp_phone_number || '',
            whatsapp_chat_url: config.defaultChatUrl || ctx.whatsapp_chat_url || '',
            page_id: ctx.page_id || '',
            placements: reuse.placements || ctx.placements || config.placementKeys || [],
        },
        activeVariant: 'A',
        variants: {
            A: { primary_text: reuse.primary_text || '', headline: reuse.headline || '', description: reuse.description || '', whatsapp_prefill_message: reuse.whatsapp_prefill_message || '' },
            B: { primary_text: '', headline: '', description: '', whatsapp_prefill_message: '' },
            C: { primary_text: '', headline: '', description: '', whatsapp_prefill_message: '' },
        },
        previewImage: null,
        validationErrors: [],
        init() {
            if (this.selectedCampaignId && !this.adsets.length) {
                this.onCampaignChange(false);
            }
            if (this.selectedAdsetId) {
                this.onAdsetChange(false);
            }
        },
        get waLink() {
            const custom = (this.form.whatsapp_chat_url || '').trim();
            const text = this.variants[this.activeVariant].whatsapp_prefill_message || '';
            if (custom.startsWith('http')) {
                if (text && !/[?&]text=/i.test(custom)) {
                    return custom + (custom.includes('?') ? '&' : '?') + 'text=' + encodeURIComponent(text);
                }
                return custom;
            }
            const phone = (custom || this.form.whatsapp_phone_number || '').replace(/\D/g, '');
            if (!phone) return '';
            return 'https://wa.me/' + phone + (text ? '?text=' + encodeURIComponent(text) : '');
        },
        applyLinkedContext(c) {
            if (!c) return;
            this.linkedContext = c;
            if (c.service_name && !this.form.service_name) this.form.service_name = c.service_name;
            if (c.campaign_goal) this.form.campaign_goal = c.campaign_goal;
            if (c.target_audience) this.form.target_audience = c.target_audience;
            if (c.page_id) this.form.page_id = c.page_id;
            if (c.whatsapp_phone_number) this.form.whatsapp_phone_number = c.whatsapp_phone_number;
            if (c.whatsapp_chat_url) this.form.whatsapp_chat_url = c.whatsapp_chat_url;
            if (c.placements?.length) this.form.placements = c.placements;
        },
        async onCampaignChange(clearAdset = true) {
            if (clearAdset) {
                this.selectedAdsetId = '';
                this.adsets = [];
            }
            if (!this.selectedCampaignId) return;
            const res = await fetch(`{{ url('/admin/creatives/builder/campaigns') }}/${this.selectedCampaignId}/adsets`, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();
            this.adsets = data.adsets || [];
            if (data.campaign?.context) this.applyLinkedContext(data.campaign.context);
        },
        onAdsetChange(runGenerate = true) {
            const adset = this.adsets.find(a => String(a.id) === String(this.selectedAdsetId));
            if (adset?.context) {
                this.applyLinkedContext(adset.context);
                if (runGenerate && this.form.service_name) this.generateAll();
            }
        },
        applyTemplate(key) {
            const t = templates[key];
            if (!t) return;
            this.form.template_key = key;
            if (!this.form.campaign_goal) this.form.campaign_goal = t.default_goal;
            if (!this.form.target_audience || this.form.target_audience === 'Broad audience') this.form.target_audience = t.default_audience;
            this.form.pain_point = t.default_pain;
            this.form.main_benefit = t.default_benefit;
            this.form.offer_discount = t.default_offer;
            if (!this.form.service_name) this.form.service_name = t.label;
            this.generateAll();
        },
        async generateAll() {
            const res = await fetch('{{ route('admin.creatives.builder.generate') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: JSON.stringify({ ...this.form, variant: 'all' }),
            });
            const data = await res.json();
            if (data.variants) {
                ['A','B','C'].forEach(v => {
                    if (data.variants[v]) {
                        this.variants[v] = {
                            primary_text: data.variants[v].primary_text || '',
                            headline: data.variants[v].headline || '',
                            description: data.variants[v].description || '',
                            whatsapp_prefill_message: data.variants[v].whatsapp_prefill_message || '',
                        };
                    }
                });
            }
        },
        previewMedia(e) {
            const f = e.target.files[0];
            if (!f) return;
            const r = new FileReader();
            r.onload = () => { this.previewImage = r.result; };
            r.readAsDataURL(f);
        },
        async runValidation() {
            const fd = new FormData(document.querySelector('form'));
            const res = await fetch('{{ route('admin.creatives.builder.validate') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: fd,
            });
            const data = await res.json();
            this.validationErrors = data.valid ? [{ field: 'ok', message: 'All checks passed', fix: 'Safe to publish to linked ad set.' }] : (data.errors || []);
        },
    };
}
</script>
@endpush
@endsection
