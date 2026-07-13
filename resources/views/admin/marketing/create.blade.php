@extends('layouts.admin')

@section('title', 'Ad Studio — Create Click-to-WhatsApp Ad')

@section('content')
@php
    $defaultPhone = $connection?->whatsapp_phone_number ?? '';
    $defaultPageId = $connection?->page_id ?? ($pages[0]['id'] ?? '');
    $defaultIg = $connection?->instagram_business_account_id ?? '';
    $connValid = (bool) ($connectionStatus['valid'] ?? false);
@endphp

<div class="w-full" x-data="adStudio(@js([
    'stockImages' => $stockImages,
    'stockByFormat' => $stockByFormat,
    'imageFormats' => $imageFormats,
    'defaultImageFormat' => $defaultImageFormat,
    'templates' => $templates,
    'placementKeys' => array_keys($placementOptions),
    'defaultPhone' => preg_replace('/\D+/', '', $defaultPhone),
    'defaultPageId' => (string) $defaultPageId,
    'defaultIg' => (string) $defaultIg,
    'whatsappNumbers' => $whatsappNumbers,
    'instagramAccounts' => $instagramAccounts ?? [],
    'pages' => collect($pages)->map(fn ($p) => [
        'id' => (string) ($p['id'] ?? ''),
        'name' => (string) ($p['name'] ?? $p['id'] ?? ''),
        'instagram_id' => $p['instagram_id'] ?? null,
        'instagram_username' => $p['instagram_username'] ?? null,
    ])->values(),
    'countryOptions' => $countryOptions,
    'connectionValid' => $connValid,
    'connectionErrors' => $connectionStatus['errors'] ?? [],
    'pageName' => $connection?->page_name ?? 'Your Page',
]))" x-init="init()">

    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-medium text-slate-500">Ads workspace / Create</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Ad Studio</h1>
            <p class="mt-0.5 text-sm text-slate-600">
                Upload a creative — Gemini fills campaign, ad set, and ad copy automatically (Click-to-WhatsApp).
            </p>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white">
                <span x-text="stages[stage].label"></span>
                · <span x-text="stage + 1"></span>/<span x-text="stages.length"></span>
            </span>
            @if(!$connValid)
                <a href="{{ route('admin.tenants.index') }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700">Sync .env</a>
                <a href="{{ route('admin.meta.connect') }}" class="rounded-xl bg-xander-navy px-4 py-2 text-sm font-semibold text-white">Connect Meta</a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    {{-- Stage progress — only completed steps get a check (not future steps with defaults) --}}
    <nav class="mb-5 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm sm:p-3" aria-label="Ad creation steps">
        <ol class="grid grid-cols-2 gap-1 sm:grid-cols-3 lg:grid-cols-6">
            <template x-for="(s, i) in stages" :key="s.key">
                <li>
                    <button type="button" @click="goTo(i)"
                        class="flex w-full items-center gap-2 rounded-xl px-2.5 py-2.5 text-left text-sm transition sm:px-3"
                        :class="stage === i
                            ? 'bg-blue-50 font-semibold text-blue-800 ring-1 ring-blue-200'
                            : (i < stage ? 'text-emerald-800 hover:bg-emerald-50' : 'text-slate-500 hover:bg-slate-50')">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                            :class="stage === i
                                ? 'bg-blue-600 text-white'
                                : (i < stage ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-500')"
                            x-text="i < stage ? '✓' : (i + 1)"></span>
                        <span class="min-w-0 truncate" x-text="s.label"></span>
                    </button>
                </li>
            </template>
        </ol>
    </nav>

    <form method="POST" action="{{ route('admin.marketing.create.publish') }}" enctype="multipart/form-data"
          id="ad-studio-form" @submit="onSubmit" class="relative pb-28">
        @csrf

        {{-- Full-width until Creative; then form + live preview (Marketing API creative step) --}}
        <div class="grid gap-5" :class="showCreativePreview ? 'xl:grid-cols-12' : 'grid-cols-1'">
            <div :class="showCreativePreview ? 'xl:col-span-9' : 'w-full'">

                {{-- STAGE 0: Connection --}}
                <section x-show="stage === 0" x-cloak class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4 sm:px-8 sm:py-5">
                        <h2 class="text-lg font-bold text-slate-900">Meta connection</h2>
                        <p class="mt-1 text-sm text-slate-500">Confirm token, ad account, Page, and WhatsApp before creating Marketing API objects.</p>
                    </div>
                    <div class="space-y-4 p-5 sm:p-8">
                        <div class="rounded-xl border p-4" :class="connectionValid ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50'">
                            <p class="font-semibold" :class="connectionValid ? 'text-emerald-800' : 'text-red-800'"
                               x-text="connectionValid ? 'Ready to publish ads' : 'Connection needs attention'"></p>
                            <ul class="mt-2 list-disc pl-5 text-sm" :class="connectionValid ? 'text-emerald-800' : 'text-red-800'">
                                <template x-for="err in connectionErrors" :key="err.code + err.message">
                                    <li><span x-text="err.message"></span> — <span x-text="err.fix"></span></li>
                                </template>
                                <li x-show="connectionValid && !connectionErrors.length">Token, ad account, Page, and WhatsApp IDs look good.</li>
                            </ul>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase text-slate-500">Ad account</p>
                                <p class="mt-1 font-mono text-sm text-slate-800">{{ $connection?->ad_account_id ?? '—' }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase text-slate-500">Page</p>
                                <p class="mt-1 text-sm text-slate-800">{{ $connection?->page_name ?? $connection?->page_id ?? '—' }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase text-slate-500">WhatsApp</p>
                                <p class="mt-1 text-sm text-slate-800">{{ $connection?->whatsapp_phone_number ?? '—' }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('admin.meta.whatsapp.index') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Manage WhatsApp numbers</a>
                            <a href="{{ route('admin.meta.index') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Business Manager</a>
                        </div>
                    </div>
                </section>

                {{-- STAGE 2: Campaign (Marketing API: create campaign) --}}
                <section x-show="stage === 2" x-cloak class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4 sm:px-8 sm:py-5">
                        <h2 class="text-lg font-bold text-slate-900">Campaign</h2>
                        <p class="mt-1 text-sm text-slate-500">Confirm AI-filled name and objective — or tweak before creating the Marketing API campaign object.</p>
                    </div>
                    <div class="space-y-5 p-5 sm:p-8">
                        <div x-show="creativeReady" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            Names were generated from your creative. Review and continue.
                        </div>
                        <div class="max-w-3xl">
                            <label class="block text-sm font-semibold text-slate-800">Campaign name</label>
                            <input type="text" name="name" x-model="form.name" required
                                placeholder="e.g. CA WhatsApp — Jobs inquiry"
                                class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-3 text-base focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <label class="block text-sm font-semibold text-slate-800">Buying type</label>
                                <p class="mt-1.5 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-700">Auction</p>
                            </div>
                            <div class="sm:col-span-1 lg:col-span-2">
                                <label class="block text-sm font-semibold text-slate-800">Campaign objective</label>
                                <select name="objective" x-model="form.objective" class="mt-1.5 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
                                    @foreach($objectives as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1.5 text-xs text-slate-500">Click-to-WhatsApp uses engagement / messages outcomes.</p>
                            </div>
                        </div>
                        <div class="max-w-3xl">
                            <label class="block text-sm font-semibold text-slate-800">Budget strategy</label>
                            <label class="mt-2 flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 hover:border-blue-300">
                                <input type="radio" name="budget_strategy" value="adset" checked class="mt-1">
                                <div>
                                    <p class="text-sm font-semibold">Ad set budget</p>
                                    <p class="mt-0.5 text-sm text-slate-500">Set daily budget on the Ad set step (recommended for WhatsApp message ads).</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </section>

                {{-- STAGE 3: Ad set --}}
                <section x-show="stage === 3" x-cloak class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4 sm:px-8 sm:py-5">
                        <h2 class="text-lg font-bold text-slate-900">Ad set</h2>
                        <p class="mt-1 text-sm text-slate-500">Audience, placements, schedule, and budget — Marketing API ad set object.</p>
                    </div>
                    <div class="space-y-6 p-5 sm:p-8">
                        <div>
                            <label class="block text-sm font-semibold text-slate-800">Ad set name</label>
                            <input type="text" name="adset_name" x-model="form.adset_name"
                                :placeholder="(form.name || 'Campaign') + ' — Ad Set'"
                                class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
                        </div>

                        <div class="space-y-4 rounded-xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-bold text-slate-800">Identity</p>
                                <button type="button" @click="refreshIdentities()" :disabled="identityLoading"
                                    class="text-xs font-semibold text-sky-700 underline disabled:opacity-50">
                                    <span x-show="!identityLoading">Sync pages &amp; Instagram</span>
                                    <span x-show="identityLoading">Syncing…</span>
                                </button>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-500">Facebook Page</label>
                                <select x-model="form.page_id" @change="onPageChange()" required
                                    class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
                                    <option value="">Select a Facebook Page…</option>
                                    <template x-for="p in pages" :key="p.id">
                                        <option :value="p.id" x-text="p.name + (p.instagram_username ? ' · @' + p.instagram_username : '')"></option>
                                    </template>
                                    <option value="__manual_page__">Enter Page ID manually…</option>
                                </select>
                                <input type="hidden" name="page_id" :value="form.page_id === '__manual_page__' ? form.page_id_manual : form.page_id">
                                <div x-show="form.page_id === '__manual_page__' || (!pages.length && !identityLoading)" class="mt-2 flex flex-wrap gap-2">
                                    <input type="text" x-model="form.page_id_manual" placeholder="Facebook Page ID"
                                        class="min-w-0 flex-1 rounded-xl border border-slate-200 px-4 py-2.5 font-mono text-sm">
                                    <button type="button" @click="saveManualPage()" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white">Add page</button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-500">Instagram profile</label>
                                <select x-model="form.instagram_user_id" @change="onInstagramSelect()"
                                    class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
                                    <option value="">None (optional)</option>
                                    <template x-for="ig in instagramAccounts" :key="ig.id">
                                        <option :value="ig.id" x-text="ig.label + (ig.source === 'manual' ? ' · added' : '')"></option>
                                    </template>
                                    <option value="__manual_ig__">Add Instagram account ID…</option>
                                </select>
                                <input type="hidden" name="instagram_user_id"
                                    :value="form.instagram_user_id === '__manual_ig__' ? form.instagram_manual : form.instagram_user_id">
                                <div x-show="form.instagram_user_id === '__manual_ig__' || showAddIg" class="mt-2 space-y-2 rounded-xl border border-pink-100 bg-pink-50/40 p-3">
                                    <p class="text-xs text-slate-600">Paste the Instagram business account ID from Meta Business Settings → Instagram accounts (e.g. 17841468010858538).</p>
                                    <div class="flex flex-wrap gap-2">
                                        <input type="text" x-model="form.instagram_manual" placeholder="Instagram business account ID"
                                            class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-4 py-2.5 font-mono text-sm">
                                        <button type="button" @click="addInstagramAccount()" class="rounded-xl bg-[#0866FF] px-4 py-2 text-xs font-semibold text-white">Add &amp; sync</button>
                                    </div>
                                </div>
                                <button type="button" @click="showAddIg = !showAddIg; if (showAddIg) form.instagram_user_id = '__manual_ig__'"
                                    class="mt-2 text-xs font-semibold text-[#0866FF] hover:underline">+ Add Instagram account</button>
                                <p x-show="identityError" class="mt-2 text-xs text-amber-800" x-text="identityError"></p>
                                <p x-show="identitySuccess" class="mt-2 text-xs text-emerald-700" x-text="identitySuccess"></p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-800">Performance goal</label>
                            <select name="optimization_goal" class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
                                @foreach($performanceGoals as $value => $label)
                                    <option value="{{ $value }}" @selected($value === 'CONVERSATIONS')>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="space-y-4 rounded-xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-bold text-slate-800">Locations</p>
                                    <p class="text-xs text-slate-500">Synced to Meta <code class="rounded bg-slate-100 px-1">geo_locations</code> — countries and/or cities &amp; regions inside them.</p>
                                </div>
                                <select x-model="form.geo_mode" name="geo_mode" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                    <option value="countries_only">Entire selected countries</option>
                                    <option value="countries_and_cities">Countries + cities / regions</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600">Countries</label>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <template x-for="code in form.countries" :key="code">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-800 ring-1 ring-blue-200">
                                            <span x-text="countryLabel(code)"></span>
                                            <button type="button" class="text-blue-600 hover:text-blue-900" @click="removeCountry(code)">×</button>
                                        </span>
                                    </template>
                                    <span x-show="!form.countries.length" class="text-xs text-slate-400">No countries selected</span>
                                </div>
                                <select class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" @change="addCountry($event.target.value); $event.target.value=''">
                                    <option value="">Add a country…</option>
                                    <template x-for="(name, code) in countryOptions" :key="code">
                                        <option :value="code" x-text="name + ' (' + code + ')'" :disabled="form.countries.includes(code)"></option>
                                    </template>
                                </select>
                                <template x-for="(c, i) in form.countries" :key="'c-'+c">
                                    <input type="hidden" :name="'countries['+i+']'" :value="c">
                                </template>
                            </div>

                            <div x-show="form.geo_mode === 'countries_and_cities'" class="space-y-4 border-t border-slate-100 pt-4">
                                <div class="rounded-xl border border-blue-100 bg-blue-50/40 p-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-xs font-bold uppercase tracking-wide text-blue-800">Cities for selected countries</p>
                                            <p class="text-xs text-slate-600">Auto-loaded from Meta for
                                                <span class="font-semibold" x-text="form.countries.map(c => countryLabel(c)).join(', ') || '—'"></span>
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <select x-model="geoSearchType" @change="loadCitySuggestions(true)" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs">
                                                <option value="city">Cities</option>
                                                <option value="region">Regions / states</option>
                                            </select>
                                            <button type="button" @click="loadCitySuggestions(true)" :disabled="citySuggestionsLoading"
                                                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 disabled:opacity-60">
                                                <span x-show="!citySuggestionsLoading">Refresh</span>
                                                <span x-show="citySuggestionsLoading">Loading…</span>
                                            </button>
                                        </div>
                                    </div>

                                    <p x-show="!form.countries.length" class="mt-2 text-xs text-amber-700">Add at least one country to load cities.</p>
                                    <p x-show="citySuggestionsError" class="mt-2 text-xs text-red-600" x-text="citySuggestionsError"></p>

                                    <div x-show="citySuggestionsLoading" class="mt-3 text-xs text-slate-500">Loading cities from Meta…</div>

                                    <div x-show="!citySuggestionsLoading && citySuggestions.length" class="mt-3">
                                        <div class="mb-2 flex flex-wrap items-center gap-2">
                                            <button type="button" @click="selectAllSuggestedCities()" class="text-xs font-semibold text-blue-700 hover:underline">Select all shown</button>
                                            <span class="text-[10px] text-slate-400">·</span>
                                            <span class="text-[10px] text-slate-500" x-text="citySuggestions.length + ' suggestions'"></span>
                                        </div>
                                        <div class="flex max-h-44 flex-wrap gap-1.5 overflow-y-auto">
                                            <template x-for="hit in citySuggestions" :key="'sug-' + hit.key">
                                                <button type="button" @click="toggleSuggestedCity(hit)"
                                                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 transition"
                                                    :class="isCitySelected(hit.key)
                                                        ? 'bg-emerald-600 text-white ring-emerald-600'
                                                        : 'bg-white text-slate-700 ring-slate-200 hover:ring-blue-300'">
                                                    <span x-text="hit.name"></span>
                                                    <span class="opacity-60" x-text="hit.country_code"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>

                                    <p x-show="!citySuggestionsLoading && form.countries.length && !citySuggestions.length && !citySuggestionsError"
                                       class="mt-2 text-xs text-slate-500">No suggestions yet — try Refresh or search below.</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-600">Search more cities or regions</label>
                                    <div class="mt-1 flex flex-wrap gap-2">
                                        <input type="search" x-model="geoQuery" @input.debounce.400ms="searchGeo()"
                                            placeholder="Type to find more…"
                                            class="min-w-[220px] flex-1 rounded-xl border border-slate-200 px-4 py-2 text-sm">
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">Optional — use this if a city is missing from the auto list.</p>
                                    <div x-show="geoLoading" class="mt-2 text-xs text-slate-500">Searching Meta…</div>
                                    <ul x-show="geoResults.length" class="mt-2 max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-white divide-y divide-slate-100">
                                        <template x-for="hit in geoResults" :key="hit.key + hit.type">
                                            <li>
                                                <button type="button" @click="addGeoHit(hit)"
                                                    class="flex w-full items-start justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-slate-50">
                                                    <span>
                                                        <span class="font-semibold text-slate-900" x-text="hit.name"></span>
                                                        <span class="block text-xs text-slate-500" x-text="geoHitSubtitle(hit)"></span>
                                                    </span>
                                                    <span class="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-slate-600" x-text="hit.type"></span>
                                                </button>
                                            </li>
                                        </template>
                                    </ul>
                                </div>

                                <div x-show="form.cities.length">
                                    <p class="text-xs font-semibold uppercase text-slate-500">Selected cities</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <template x-for="city in form.cities" :key="city.key">
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-200">
                                                <span x-text="cityLabel(city)"></span>
                                                <button type="button" @click="removeCity(city.key)">×</button>
                                            </span>
                                        </template>
                                    </div>
                                </div>

                                <div x-show="form.regions.length">
                                    <p class="text-xs font-semibold uppercase text-slate-500">Selected regions</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <template x-for="region in form.regions" :key="region.key">
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-800 ring-1 ring-violet-200">
                                                <span x-text="regionLabel(region)"></span>
                                                <button type="button" @click="removeRegion(region.key)">×</button>
                                            </span>
                                        </template>
                                    </div>
                                </div>

                                <input type="hidden" name="cities_json" :value="JSON.stringify(form.cities)">
                                <input type="hidden" name="regions_json" :value="JSON.stringify(form.regions)">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600">Age range</label>
                                <div class="mt-1 flex max-w-xs gap-2">
                                    <input type="number" name="age_min" x-model="form.age_min" min="18" max="65"
                                        class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
                                    <input type="number" name="age_max" x-model="form.age_max" min="18" max="65"
                                        class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 rounded-xl border border-slate-200 p-4">
                            <p class="text-sm font-bold text-slate-800">Budget & schedule</p>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600">Daily budget (USD)</label>
                                    <div class="relative mt-1">
                                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">$</span>
                                        <input type="number" name="daily_budget_dollars" x-model="form.daily_budget_dollars"
                                            min="1" step="0.01" required
                                            class="w-full rounded-xl border border-slate-200 py-3 pl-8 pr-16 text-sm">
                                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400">USD</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600">Start date</label>
                                    <input type="datetime-local" name="start_date" x-model="form.start_date"
                                        class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
                                </div>
                            </div>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="set_end_date" value="1" x-model="form.set_end_date" class="rounded">
                                Set an end date
                            </label>
                            <div x-show="form.set_end_date">
                                <input type="datetime-local" name="end_date" x-model="form.end_date"
                                    class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-800">Placements</label>
                            <p class="text-xs text-slate-500">Facebook & Instagram feeds, stories, reels.</p>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                @foreach($placementOptions as $key => $pl)
                                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                        <input type="checkbox" name="placements[]" value="{{ $key }}"
                                            x-model="form.placements" class="rounded">
                                        {{ $pl['label'] }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>

                {{-- STAGE 4: Message destinations --}}
                <section x-show="stage === 4" x-cloak class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4 sm:px-8 sm:py-5">
                        <h2 class="text-lg font-bold text-slate-900">Message destinations</h2>
                        <p class="mt-1 text-sm text-slate-500">Choose where to chat with people after they see your ad. Sync Facebook, Instagram, and WhatsApp from Meta — or add them manually.</p>
                    </div>
                    <div class="space-y-5 p-5 sm:p-8">
                        <div class="space-y-3">
                            <label class="flex cursor-pointer gap-3 rounded-xl border p-4 transition"
                                :class="form.message_destination_mode === 'automatic' ? 'border-[#0866FF] bg-sky-50/50 ring-1 ring-[#0866FF]/30' : 'border-slate-200 hover:bg-slate-50'">
                                <input type="radio" name="message_destination_mode" value="automatic" x-model="form.message_destination_mode" class="mt-1 text-[#0866FF]">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-900">Automatic destination <span class="font-normal text-slate-500">(recommended)</span></p>
                                    <p class="mt-0.5 text-xs text-slate-600">We’ll send people to the messaging app where they engage most and lower ad costs.</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800 ring-1 ring-slate-200">
                                            <span class="text-[#0866FF]">Messenger</span>
                                            <span class="max-w-[10rem] truncate text-slate-500" x-text="selectedPageName()"></span>
                                        </span>
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800 ring-1 ring-slate-200" x-show="selectedIgLabel()">
                                            <span class="bg-gradient-to-r from-purple-500 to-pink-500 bg-clip-text text-transparent">Instagram</span>
                                            <span class="max-w-[10rem] truncate text-slate-500" x-text="selectedIgLabel()"></span>
                                        </span>
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800 ring-1 ring-slate-200">
                                            <span class="text-[#25D366]">WhatsApp</span>
                                            <span class="max-w-[10rem] truncate text-slate-500" x-text="selectedWaLabel()"></span>
                                        </span>
                                    </div>
                                </div>
                            </label>

                            <label class="flex cursor-pointer gap-3 rounded-xl border p-4 transition"
                                :class="form.message_destination_mode === 'manual' ? 'border-[#0866FF] bg-sky-50/50 ring-1 ring-[#0866FF]/30' : 'border-slate-200 hover:bg-slate-50'">
                                <input type="radio" name="message_destination_mode" value="manual" x-model="form.message_destination_mode" class="mt-1 text-[#0866FF]">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-900">Manual destination</p>
                                    <p class="mt-0.5 text-xs text-slate-600">We’ll only send people to the messaging apps you choose.</p>
                                </div>
                            </label>
                        </div>

                        <div x-show="form.message_destination_mode === 'manual'" class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                            <label class="flex items-center gap-2 text-sm font-medium text-slate-800">
                                <input type="checkbox" x-model="form.dest_messenger" class="rounded text-[#0866FF]"> Facebook Messenger
                                <span class="text-xs font-normal text-slate-500" x-text="'(' + selectedPageName() + ')'"></span>
                            </label>
                            <label class="flex items-center gap-2 text-sm font-medium text-slate-800">
                                <input type="checkbox" x-model="form.dest_instagram" class="rounded text-[#0866FF]"> Instagram
                                <span class="text-xs font-normal text-slate-500" x-text="selectedIgLabel() ? '(' + selectedIgLabel() + ')' : '(add Instagram above in Ad set)'"></span>
                            </label>
                            <label class="flex items-center gap-2 text-sm font-medium text-slate-800">
                                <input type="checkbox" x-model="form.dest_whatsapp" class="rounded text-[#0866FF]"> WhatsApp
                            </label>
                        </div>

                        <div class="space-y-4 rounded-xl border-2 border-[#25D366]/30 bg-[#25D366]/5 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm font-bold text-slate-900">WhatsApp phone number</p>
                                <div class="flex items-center gap-3">
                                    <button type="button" @click="refreshWhatsAppNumbers()" :disabled="waLoading"
                                        class="text-xs font-semibold text-[#075E54] underline disabled:opacity-50">
                                        <span x-show="!waLoading">Refresh</span>
                                        <span x-show="waLoading">Syncing…</span>
                                    </button>
                                    <a href="{{ route('admin.meta.whatsapp.index') }}" class="text-xs font-semibold text-[#075E54] underline">Manage numbers</a>
                                </div>
                            </div>

                            <div x-show="waError" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900" x-text="waError"></div>

                            <select x-show="whatsappNumbers.length" x-model="form.whatsapp_phone_number" @change="onWhatsAppSelect()"
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                                <template x-for="n in whatsappNumbers" :key="n.id">
                                    <option :value="n.phone"
                                        x-text="n.display + ' — ' + (n.label || 'WhatsApp') + (n.waba_name ? ' · ' + n.waba_name : '')"></option>
                                </template>
                                <option value="__custom__">Enter custom number…</option>
                            </select>

                            <input type="text" x-show="!whatsappNumbers.length || form.whatsapp_phone_number === '__custom__'"
                                x-model="form.whatsapp_phone_custom"
                                @input="form.whatsapp_phone_number = form.whatsapp_phone_custom.replace(/\D/g,'')"
                                placeholder="+1 438-703-0350"
                                class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
                            <input type="hidden" name="whatsapp_phone_number"
                                :value="form.whatsapp_phone_number === '__custom__' ? form.whatsapp_phone_custom.replace(/\D/g,'') : form.whatsapp_phone_number">

                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-500">Or WhatsApp link (wa.me)</label>
                                <input type="text" name="whatsapp_chat_url" x-model="form.whatsapp_chat_url"
                                    placeholder="https://wa.me/14387030350"
                                    class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 font-mono text-sm">
                            </div>

                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="notify_on_publish" value="1" x-model="form.notify_on_publish" class="rounded text-[#25D366]">
                                Notify this number on WhatsApp when ad is published
                            </label>
                            <div x-show="form.notify_on_publish">
                                <label class="block text-xs font-semibold uppercase text-slate-500">Notification number (optional)</label>
                                <input type="text" name="notification_whatsapp_number" x-model="form.notification_whatsapp_number"
                                    :placeholder="form.whatsapp_phone_number || 'Same as delivery number'"
                                    class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm">
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 text-[11px] leading-relaxed text-slate-600">
                            WhatsApp names and phone numbers are subject to Meta Advertising Policies and the WhatsApp Commerce Policy.
                            Click-to-WhatsApp ads can show an “Active on WhatsApp” status when using the WhatsApp Business app.
                        </div>

                        <div class="rounded-xl bg-[#e5ddd5] p-4">
                            <p class="text-xs font-semibold text-slate-600">Preview destination</p>
                            <p class="mt-1 font-mono text-sm font-semibold text-[#128C7E]" x-text="waLink || 'Select a business number'"></p>
                        </div>
                    </div>
                </section>

                {{-- STAGE 1: Creative (upload-first + Gemini) --}}
                <section x-show="stage === 1" x-cloak class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                        <h2 class="text-lg font-bold text-slate-900">Creative</h2>
                        <p class="mt-1 text-sm text-slate-500">Upload your image — Gemini writes headlines, primary text, and campaign names for you.</p>
                    </div>
                    <div class="space-y-4 p-5 sm:p-6">
                        <input type="hidden" name="ai_image_path" x-model="form.ai_image_path">
                        <input type="hidden" name="stock_image_id" x-model="form.stock_image_id">
                        <input type="hidden" name="template_key" x-model="form.template_key">
                        <input type="hidden" name="service_name" x-model="form.service_name">
                        <input type="hidden" name="target_audience" x-model="form.target_audience">
                        <input type="hidden" name="main_benefit" x-model="form.main_benefit">
                        <input type="hidden" name="ad_name" x-model="form.ad_name">
                        <input type="hidden" name="image_format" x-model="form.image_format">
                        <input type="hidden" name="call_to_action" value="WHATSAPP_MESSAGE">

                        {{-- Upload first --}}
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50/80 p-4"
                             :class="aiAnalyzing ? 'opacity-70' : ''">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">1. Upload creative <span class="text-red-500">*</span></p>
                                    <p class="text-xs text-slate-500">JPG, PNG, or WebP · under 4 MB · Feed 4:5 recommended</p>
                                </div>
                                <div class="flex flex-wrap gap-1">
                                    <template x-for="(fmt, key) in selectableFormats" :key="key">
                                        <button type="button" @click="setImageFormat(key)"
                                            class="rounded-lg border px-2 py-1 text-[10px] font-semibold"
                                            :class="form.image_format === key ? 'border-blue-500 bg-blue-50 text-blue-800' : 'border-slate-200 text-slate-600'">
                                            <span x-text="fmt.short || fmt.label"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <label class="mt-3 flex cursor-pointer flex-col items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-6 text-center hover:border-blue-400">
                                <input type="file" accept="image/jpeg,image/png,image/webp" x-ref="fileInput" @change="onFileUpload($event)"
                                    class="sr-only">
                                <span class="text-sm font-semibold text-slate-800" x-text="aiAnalyzing ? 'Gemini is analyzing…' : (previewImage ? 'Replace creative' : 'Drop image or click to upload')"></span>
                                <span class="mt-1 text-xs text-slate-500">Gemini auto-fills all ad copy from this image</span>
                            </label>
                            <div x-show="mediaValidation.errors.length" class="mt-2 rounded-lg border border-red-200 bg-red-50 p-2 text-xs text-red-700">
                                <template x-for="e in mediaValidation.errors" :key="e"><p x-text="e"></p></template>
                            </div>
                            <p x-show="aiAnalyzeError" class="mt-2 text-xs text-red-600" x-text="aiAnalyzeError"></p>
                            <div x-show="previewImage" class="mt-3 flex items-center gap-3 rounded-lg border border-slate-100 bg-white p-2">
                                <img :src="previewImage" class="h-14 w-14 rounded-lg object-cover" alt="">
                                <div class="min-w-0 flex-1 text-xs">
                                    <p class="font-semibold text-slate-700" x-text="creativeReady ? 'Creative ready · AI copy filled' : 'Media selected'"></p>
                                    <p class="text-slate-500" x-text="mediaSourceLabel"></p>
                                </div>
                                <button type="button" @click="clearMedia()" class="text-xs font-semibold text-red-600">Clear</button>
                            </div>
                        </div>

                        {{-- AI-filled copy (compact) --}}
                        <div x-show="creativeReady || form.primary_text" x-cloak class="space-y-3 rounded-xl border border-emerald-100 bg-emerald-50/40 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-800">2. AI ad copy <span class="font-normal text-slate-500">(edit only if needed)</span></p>
                                <button type="button" @click="regenerateCopyFromFields()" class="text-xs font-semibold text-emerald-700 hover:underline">Re-generate text</button>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-slate-600">Primary text</label>
                                    <textarea name="primary_text" x-model="form.primary_text" rows="3" required
                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600">Headline</label>
                                    <input type="text" name="headline" x-model="form.headline"
                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600">Description</label>
                                    <input type="text" name="description" x-model="form.description"
                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-slate-600">WhatsApp pre-filled message</label>
                                    <textarea name="whatsapp_prefill_message" x-model="form.whatsapp_prefill_message" rows="2"
                                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"></textarea>
                                </div>
                            </div>
                            <div class="grid gap-2 rounded-lg border border-slate-100 bg-white/80 p-3 text-xs text-slate-600 sm:grid-cols-3">
                                <p><span class="font-semibold text-slate-800">Campaign:</span> <span x-text="form.name || '—'"></span></p>
                                <p><span class="font-semibold text-slate-800">Ad set:</span> <span x-text="form.adset_name || '—'"></span></p>
                                <p><span class="font-semibold text-slate-800">Ad:</span> <span x-text="form.ad_name || '—'"></span></p>
                            </div>
                        </div>

                        {{-- Optional fallbacks --}}
                        <details class="rounded-xl border border-slate-200 bg-white">
                            <summary class="cursor-pointer px-4 py-3 text-xs font-semibold text-slate-600">Optional: stock image or AI-generated media</summary>
                            <div class="space-y-3 border-t border-slate-100 px-4 py-3">
                                <div class="flex rounded-lg border border-slate-200 bg-slate-50 p-1 text-xs">
                                    <button type="button" @click="mediaTab = 'stock'" :class="mediaTab === 'stock' ? 'bg-white shadow text-slate-900' : 'text-slate-500'" class="rounded-md px-3 py-1.5 font-semibold">Standard</button>
                                    <button type="button" @click="mediaTab = 'ai'" :class="mediaTab === 'ai' ? 'bg-white shadow text-slate-900' : 'text-slate-500'" class="rounded-md px-3 py-1.5 font-semibold">AI generate</button>
                                </div>
                                <div x-show="mediaTab === 'stock'" class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                    <template x-for="img in filteredStockImages" :key="img.id">
                                        <button type="button" @click="selectStock(img)"
                                            class="overflow-hidden rounded-lg border-2"
                                            :class="form.stock_image_id === img.id ? 'border-blue-500' : 'border-slate-200'">
                                            <img :src="img.url" :alt="img.label" class="h-20 w-full object-cover">
                                        </button>
                                    </template>
                                </div>
                                <div x-show="mediaTab === 'ai'" class="space-y-2">
                                    <textarea x-model="form.ai_image_prompt" rows="2" placeholder="Optional custom image prompt…"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
                                    <button type="button" @click="generateAiImage()" :disabled="aiGenerating"
                                        class="rounded-lg bg-violet-600 px-4 py-2 text-xs font-semibold text-white disabled:opacity-60">
                                        <span x-show="aiGenerating">Generating…</span>
                                        <span x-show="!aiGenerating">Generate AI image</span>
                                    </button>
                                    <p x-show="aiError" class="text-xs text-red-600" x-text="aiError"></p>
                                </div>
                            </div>
                        </details>
                    </div>
                </section>

                {{-- STAGE 5: Review --}}
                <section x-show="stage === 5" x-cloak class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4 sm:px-8 sm:py-5">
                        <h2 class="text-lg font-bold text-slate-900">Review &amp; publish ad</h2>
                        <p class="mt-1 text-sm text-slate-500">Creates campaign → ad set → creative → ad on Meta, then activates or leaves PAUSED.</p>
                    </div>
                    <div class="space-y-5 p-5 sm:p-8">
                        <dl class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                                <dt class="text-xs font-semibold text-slate-500">Campaign</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900" x-text="form.name"></dd>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                                <dt class="text-xs font-semibold text-slate-500">Objective</dt>
                                <dd class="mt-1 text-sm text-slate-800" x-text="form.objective"></dd>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                                <dt class="text-xs font-semibold text-slate-500">Daily budget</dt>
                                <dd class="mt-1 text-sm text-slate-800" x-text="'$' + form.daily_budget_dollars"></dd>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                                <dt class="text-xs font-semibold text-slate-500">WhatsApp</dt>
                                <dd class="mt-1 truncate font-mono text-sm text-slate-800" x-text="waLink || '—'"></dd>
                            </div>
                        </dl>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="rounded-xl border border-slate-200 p-4">
                                <p class="text-sm font-semibold text-slate-800">Publishing checklist</p>
                                <ul class="mt-3 space-y-1.5 text-sm">
                                    <template x-for="item in checklist" :key="item.label">
                                        <li :class="item.ok ? 'text-emerald-700' : 'text-slate-500'">
                                            <span x-text="item.ok ? '✓' : '○'"></span>
                                            <span x-text="item.label"></span>
                                        </li>
                                    </template>
                                </ul>
                                <button type="button" @click="runPreflight()" class="mt-3 rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700">Refresh checklist</button>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-sm font-semibold text-slate-800">Campaign score</p>
                                <div class="mt-3 flex items-center gap-4">
                                    <div class="relative h-20 w-20 shrink-0">
                                        <svg class="h-20 w-20 -rotate-90" viewBox="0 0 100 100">
                                            <circle cx="50" cy="50" r="42" fill="none" stroke="#e2e8f0" stroke-width="8"/>
                                            <circle cx="50" cy="50" r="42" fill="none" stroke="#2563eb" stroke-width="8"
                                                stroke-linecap="round" :stroke-dasharray="264" :stroke-dashoffset="264 - (264 * score / 100)"/>
                                        </svg>
                                        <span class="absolute inset-0 flex items-center justify-center text-xl font-bold text-slate-900" x-text="score"></span>
                                    </div>
                                    <p class="text-sm text-slate-600" x-text="score >= 90 ? 'Ready to publish.' : 'Complete remaining checklist items before activating.'"></p>
                                </div>
                            </div>
                        </div>

                        <div x-show="preflightErrors.length" class="rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                            <template x-for="err in preflightErrors" :key="err.code">
                                <p x-text="err.message"></p>
                            </template>
                        </div>

                        <p class="text-xs text-slate-500">By publishing, you acknowledge use of Meta ad tools. Conversations deliver to your selected WhatsApp business number.</p>
                    </div>
                </section>
            </div>

            {{-- Meta placement previews: Feed 4:5 · Square 1:1 · Stories 9:16 --}}
            <aside x-show="showCreativePreview" x-cloak class="xl:col-span-3">
                <div class="sticky top-4 space-y-3 pb-28">
                    <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-bold text-slate-900">Ad preview</p>
                                <p class="text-[10px] text-slate-500">Meta placement sizes</p>
                            </div>
                            <span class="rounded-full bg-[#25D366]/15 px-2 py-0.5 text-[9px] font-bold text-[#128C7E]">WA</span>
                        </div>

                        <div class="mt-2 flex gap-1 rounded-lg bg-slate-100 p-0.5">
                            <button type="button" @click="previewPlacement = 'feed_4x5'"
                                class="flex-1 rounded-md px-1.5 py-1 text-[10px] font-bold"
                                :class="previewPlacement === 'feed_4x5' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'">4:5</button>
                            <button type="button" @click="previewPlacement = 'square_1x1'"
                                class="flex-1 rounded-md px-1.5 py-1 text-[10px] font-bold"
                                :class="previewPlacement === 'square_1x1' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'">1:1</button>
                            <button type="button" @click="previewPlacement = 'story_9x16'"
                                class="flex-1 rounded-md px-1.5 py-1 text-[10px] font-bold"
                                :class="previewPlacement === 'story_9x16' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'">9:16</button>
                        </div>
                        <p class="mt-1 text-center text-[9px] text-slate-400" x-text="placementHint"></p>

                        <div class="mx-auto mt-2 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
                             :class="previewPlacement === 'story_9x16' ? 'max-w-[140px]' : 'max-w-[200px]'">
                            <div class="flex items-center gap-1.5 border-b border-slate-100 p-2" x-show="previewPlacement !== 'story_9x16'">
                                <div class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-[9px] font-bold text-white">P</div>
                                <div class="min-w-0">
                                    <p class="truncate text-[10px] font-bold" x-text="pageName"></p>
                                    <p class="text-[8px] text-slate-400">Sponsored</p>
                                </div>
                            </div>
                            <p class="line-clamp-2 whitespace-pre-line px-2 py-1.5 text-[10px] leading-snug text-slate-700"
                               x-show="previewPlacement !== 'story_9x16'"
                               x-text="form.primary_text || 'Upload a creative…'"></p>

                            {{-- Frame uses Meta aspect; image uses object-contain so real upload ratio is visible --}}
                            <div class="relative w-full bg-slate-100" :style="'aspect-ratio:' + placementAspectRatio">
                                <img :src="previewImage" x-show="previewImage"
                                     class="absolute inset-0 h-full w-full object-contain" alt="Ad creative">
                                <div x-show="!previewImage" class="absolute inset-0 flex items-center justify-center text-[9px] text-slate-400">No media</div>
                                <div x-show="mediaValidation.width && mediaValidation.height"
                                     class="absolute bottom-1 right-1 rounded bg-black/60 px-1.5 py-0.5 text-[8px] font-semibold text-white"
                                     x-text="(mediaValidation.width || '') + '×' + (mediaValidation.height || '')"></div>
                            </div>

                            <div class="border-t border-slate-100 p-2">
                                <p class="truncate text-[10px] font-bold text-slate-900" x-text="form.headline || 'Headline'"></p>
                                <p class="truncate text-[8px] text-slate-500" x-text="form.description || ''"></p>
                                <button type="button" class="mt-1.5 w-full rounded-md bg-[#25D366] py-1.5 text-[9px] font-bold text-white">Send WhatsApp message</button>
                            </div>
                        </div>
                        <p class="mt-2 text-center text-[9px] text-slate-400">Upload matches Meta’s 4:5 · 1:1 · 9:16 trio for best delivery.</p>
                    </div>

                    <div x-show="stage === 5 && hasWa()" class="rounded-xl border border-[#25D366]/25 bg-[#25D366]/5 p-3">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Destination</p>
                        <p class="mt-1 break-all font-mono text-[11px] font-semibold text-[#128C7E]" x-text="waLink || '—'"></p>
                    </div>
                </div>
            </aside>
        </div>

        <div class="fixed bottom-0 left-0 right-0 z-30 border-t border-slate-200 bg-white/95 backdrop-blur-sm">
            <div class="flex w-full flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.campaigns.index') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Close</a>
                    <button type="button" x-show="stage > 0" @click="prev()" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Back</button>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" formaction="{{ route('admin.marketing.create.draft') }}"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Save draft</button>
                    <button type="button" x-show="stage < stages.length - 1" @click="next()"
                        class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white">Continue</button>
                    <template x-if="stage === stages.length - 1">
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" name="activate" value="1" class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white">Publish &amp; deliver (ACTIVE)</button>
                            <button type="submit" name="activate" value="0" class="rounded-xl bg-slate-800 px-5 py-2 text-sm font-semibold text-white">Publish as PAUSED</button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function adStudio(config) {
    const templates = config.templates || {};
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    const defaultStart = now.toISOString().slice(0, 16);

    return {
        stages: [
            { key: 'connection', label: 'Connection' },
            { key: 'creative', label: 'Creative' },
            { key: 'campaign', label: 'Campaign' },
            { key: 'audience', label: 'Ad set' },
            { key: 'whatsapp', label: 'Destinations' },
            { key: 'review', label: 'Publish' },
        ],
        stage: config.connectionValid ? 1 : 0,
        connectionValid: !!config.connectionValid,
        connectionErrors: config.connectionErrors || [],
        pageName: config.pageName || 'Your Page',
        stockImages: config.stockImages || [],
        stockByFormat: config.stockByFormat || {},
        imageFormats: config.imageFormats || {},
        mediaTab: 'upload',
        mediaSource: '',
        mediaValidation: { valid: false, errors: [], warnings: [], width: null, height: null },
        aiGenerating: false,
        aiError: '',
        aiAnalyzing: false,
        aiAnalyzeError: '',
        creativeReady: false,
        whatsappNumbers: config.whatsappNumbers || [],
        pages: config.pages || [],
        instagramAccounts: config.instagramAccounts || [],
        identityLoading: false,
        identityError: '',
        identitySuccess: '',
        showAddIg: false,
        countryOptions: config.countryOptions || {},
        waLoading: false,
        waError: '',
        geoSearchType: 'city',
        geoQuery: '',
        geoResults: [],
        geoLoading: false,
        citySuggestions: [],
        citySuggestionsLoading: false,
        citySuggestionsError: '',
        citySuggestionsKey: '',
        citySuggestionsTimer: null,
        previewPlacement: 'feed_4x5',
        form: {
            name: '',
            objective: 'OUTCOME_ENGAGEMENT',
            adset_name: '',
            ad_name: '',
            page_id: config.defaultPageId || '',
            page_id_manual: '',
            instagram_user_id: config.defaultIg || '',
            instagram_manual: '',
            message_destination_mode: 'automatic',
            dest_messenger: true,
            dest_instagram: true,
            dest_whatsapp: true,
            whatsapp_phone_number: config.defaultPhone || '',
            whatsapp_phone_custom: '',
            whatsapp_chat_url: '',
            notify_on_publish: true,
            notification_whatsapp_number: '',
            geo_mode: 'countries_and_cities',
            countries: ['CA'],
            cities: [],
            regions: [],
            age_min: 18,
            age_max: 65,
            daily_budget_dollars: 5,
            start_date: defaultStart,
            set_end_date: false,
            end_date: '',
            placements: config.placementKeys || [],
            template_key: '',
            service_name: '',
            target_audience: '',
            main_benefit: '',
            primary_text: '',
            headline: '',
            description: '',
            whatsapp_prefill_message: '',
            stock_image_id: '',
            ai_image_path: '',
            ai_image_prompt: '',
            image_format: config.defaultImageFormat || 'feed_4x5',
        },
        previewImage: null,
        score: 0,
        checklist: [],
        preflightErrors: [],

        init() {
            if (!this.form.placements.length) this.form.placements = config.placementKeys || [];
            if (this.whatsappNumbers.length && !this.form.whatsapp_phone_number) {
                this.form.whatsapp_phone_number = this.whatsappNumbers[0].phone;
            }
            if (!this.form.instagram_user_id && this.instagramAccounts.length) {
                this.form.instagram_user_id = this.instagramAccounts[0].id;
            }
            this.refreshWhatsAppNumbers();
            this.refreshIdentities();
            this.$watch('form.countries', () => this.scheduleCitySuggestions());
            this.$watch('form.geo_mode', () => this.scheduleCitySuggestions());
            if (this.form.geo_mode === 'countries_and_cities') {
                this.scheduleCitySuggestions();
            }
        },

        selectedPageName() {
            const id = this.form.page_id === '__manual_page__' ? this.form.page_id_manual : this.form.page_id;
            const page = this.pages.find(p => String(p.id) === String(id));
            return page?.name || id || 'Select a Page';
        },

        selectedIgLabel() {
            const id = this.form.instagram_user_id === '__manual_ig__' ? this.form.instagram_manual : this.form.instagram_user_id;
            if (!id) return '';
            const ig = this.instagramAccounts.find(i => String(i.id) === String(id));
            return ig?.label || (id.startsWith('@') ? id : (id.length > 8 ? '@…' + id.slice(-6) : id));
        },

        selectedWaLabel() {
            const phone = this.form.whatsapp_phone_number === '__custom__'
                ? this.form.whatsapp_phone_custom
                : this.form.whatsapp_phone_number;
            const n = this.whatsappNumbers.find(w => w.phone === phone);
            return n?.display || phone || 'Select WhatsApp';
        },

        onPageChange() {
            if (this.form.page_id === '__manual_page__') return;
            const page = this.pages.find(p => String(p.id) === String(this.form.page_id));
            if (page?.instagram_id) {
                this.form.instagram_user_id = page.instagram_id;
            }
        },

        onInstagramSelect() {
            this.showAddIg = this.form.instagram_user_id === '__manual_ig__';
        },

        async refreshIdentities() {
            this.identityLoading = true;
            this.identityError = '';
            this.identitySuccess = '';
            try {
                const res = await fetch('{{ route('admin.marketing.create.identities') }}', {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (!data.success) {
                    this.identityError = data.message || 'Could not sync Pages / Instagram from Meta.';
                    return;
                }
                this.pages = data.pages || [];
                this.instagramAccounts = data.instagram || [];
                if (this.form.page_id && this.form.page_id !== '__manual_page__') {
                    const still = this.pages.find(p => String(p.id) === String(this.form.page_id));
                    if (!still && this.pages.length) this.form.page_id = this.pages[0].id;
                } else if (!this.form.page_id && this.pages.length) {
                    this.form.page_id = this.pages[0].id;
                }
                this.onPageChange();
                this.identitySuccess = 'Pages & Instagram synced from Meta.';
            } catch (e) {
                this.identityError = 'Could not reach Meta to list Pages / Instagram.';
            } finally {
                this.identityLoading = false;
            }
        },

        async saveManualPage() {
            const id = (this.form.page_id_manual || '').replace(/\D/g, '');
            if (!id) {
                this.identityError = 'Enter a Facebook Page ID.';
                return;
            }
            this.form.page_id = id;
            await this.persistIdentity({ page_id: id, page_name: id });
        },

        async addInstagramAccount() {
            const id = (this.form.instagram_manual || '').replace(/\D/g, '');
            if (!id) {
                this.identityError = 'Enter an Instagram business account ID.';
                return;
            }
            await this.persistIdentity({
                page_id: this.form.page_id === '__manual_page__' ? this.form.page_id_manual : this.form.page_id,
                instagram_user_id: id,
                add_instagram_id: id,
            });
            this.form.instagram_user_id = id;
            this.showAddIg = false;
        },

        async persistIdentity(payload) {
            this.identityError = '';
            this.identitySuccess = '';
            try {
                const res = await fetch('{{ route('admin.marketing.create.identities.save') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (!data.success) {
                    this.identityError = data.message || 'Could not save identity.';
                    return;
                }
                this.pages = data.pages || this.pages;
                this.instagramAccounts = data.instagram || this.instagramAccounts;
                if (data.page_id) this.form.page_id = data.page_id;
                if (data.instagram_user_id) this.form.instagram_user_id = data.instagram_user_id;
                this.identitySuccess = data.message || 'Saved.';
            } catch (e) {
                this.identityError = 'Could not save Page / Instagram identity.';
            }
        },

        get waLink() {
            const custom = (this.form.whatsapp_chat_url || '').trim();
            const text = this.form.whatsapp_prefill_message || '';
            if (custom.startsWith('http')) {
                if (text && !/[?&]text=/i.test(custom)) {
                    return custom + (custom.includes('?') ? '&' : '?') + 'text=' + encodeURIComponent(text);
                }
                return custom;
            }
            const phone = (custom || this.form.whatsapp_phone_number || '').replace(/\D/g, '');
            if (!phone || phone === '__custom__') return '';
            return 'https://wa.me/' + phone + (text ? '?text=' + encodeURIComponent(text) : '');
        },

        get filteredStockImages() {
            return this.stockByFormat[this.form.image_format] || [];
        },

        get selectableFormats() {
            const out = {};
            Object.keys(this.imageFormats || {}).forEach(key => {
                if (key === 'portrait_191') return; // legacy alias
                out[key] = this.imageFormats[key];
            });
            return out;
        },

        get previewAspectRatio() {
            const fmt = this.imageFormats[this.form.image_format];
            if (!fmt) return '4/5';
            return fmt.width + '/' + fmt.height;
        },

        get placementAspectRatio() {
            const map = {
                feed_4x5: '4/5',
                square_1x1: '1/1',
                story_9x16: '9/16',
                landscape_191: '1.91/1',
            };
            return map[this.previewPlacement] || this.previewAspectRatio;
        },

        get placementHint() {
            const hints = {
                feed_4x5: 'Feed · recommended 1080×1350',
                square_1x1: 'Square · recommended 1080×1080',
                story_9x16: 'Stories / Reels / WA Status · 1080×1920',
            };
            return hints[this.previewPlacement] || '';
        },

        get mediaSourceLabel() {
            if (this.mediaSource === 'ai') return 'AI generated';
            if (this.mediaSource === 'stock') return 'Standard template';
            if (this.mediaSource === 'upload') return 'Uploaded · Gemini analyzed';
            return '';
        },

        get showCreativePreview() {
            return this.stage === 1 || this.stage === 5;
        },

        hasWa() {
            const p = this.form.whatsapp_phone_number;
            if (p && p !== '__custom__') return true;
            if (this.form.whatsapp_phone_custom?.replace(/\D/g, '')) return true;
            return !!(this.form.whatsapp_chat_url || '').trim();
        },

        stageComplete(i) {
            if (i === 0) return this.connectionValid;
            if (i === 1) return !!this.form.primary_text && (!!this.previewImage || !!this.form.stock_image_id || !!this.form.ai_image_path);
            if (i === 2) return !!this.form.name && !!this.form.objective;
            if (i === 3) return this.form.daily_budget_dollars >= 1 && this.form.countries.length > 0;
            if (i === 4) return this.hasWa();
            return false;
        },

        countryLabel(code) {
            return (this.countryOptions[code] || code) + ' (' + code + ')';
        },

        addCountry(code) {
            if (!code || this.form.countries.includes(code)) return;
            this.form.countries.push(code);
            this.geoResults = [];
            this.scheduleCitySuggestions();
        },

        removeCountry(code) {
            this.form.countries = this.form.countries.filter(c => c !== code);
            this.form.cities = this.form.cities.filter(c => c.country !== code);
            this.form.regions = this.form.regions.filter(r => r.country !== code);
            this.citySuggestions = this.citySuggestions.filter(h => (h.country_code || '') !== code);
            this.scheduleCitySuggestions();
        },

        cityLabel(city) {
            return [city.name, city.region, city.country].filter(Boolean).join(', ');
        },

        regionLabel(region) {
            return [region.name, region.country].filter(Boolean).join(', ');
        },

        geoHitSubtitle(hit) {
            return [hit.region, hit.country_code || hit.country_name].filter(Boolean).join(' · ');
        },

        scheduleCitySuggestions() {
            if (this.citySuggestionsTimer) clearTimeout(this.citySuggestionsTimer);
            this.citySuggestionsTimer = setTimeout(() => this.loadCitySuggestions(false), 350);
        },

        async loadCitySuggestions(force = false) {
            if (this.form.geo_mode !== 'countries_and_cities') {
                this.citySuggestions = [];
                this.citySuggestionsKey = '';
                return;
            }
            if (!this.form.countries.length) {
                this.citySuggestions = [];
                this.citySuggestionsKey = '';
                return;
            }

            const key = this.form.countries.slice().sort().join(',') + ':' + (this.geoSearchType || 'city');
            if (!force && key === this.citySuggestionsKey && this.citySuggestions.length) {
                return;
            }

            this.citySuggestionsLoading = true;
            this.citySuggestionsError = '';
            try {
                const params = new URLSearchParams({
                    countries: this.form.countries.join(','),
                    type: this.geoSearchType || 'city',
                });
                const res = await fetch('{{ route('admin.meta.geo.suggest') }}?' + params.toString(), {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (!data.success) {
                    this.citySuggestionsError = data.message || 'Could not load cities from Meta.';
                    this.citySuggestions = [];
                    return;
                }
                this.citySuggestions = data.data || [];
                this.citySuggestionsKey = key;
            } catch (e) {
                this.citySuggestionsError = 'Could not reach Meta city suggestions.';
                this.citySuggestions = [];
            } finally {
                this.citySuggestionsLoading = false;
            }
        },

        isCitySelected(key) {
            if (this.geoSearchType === 'region') {
                return this.form.regions.some(r => r.key === key);
            }
            return this.form.cities.some(c => c.key === key);
        },

        toggleSuggestedCity(hit) {
            if (this.isCitySelected(hit.key)) {
                if ((hit.type || this.geoSearchType) === 'region') {
                    this.removeRegion(hit.key);
                } else {
                    this.removeCity(hit.key);
                }
                return;
            }
            this.addGeoHit(hit);
        },

        selectAllSuggestedCities() {
            (this.citySuggestions || []).forEach(hit => {
                if (!this.isCitySelected(hit.key)) this.addGeoHit(hit);
            });
        },

        async searchGeo() {
            const q = (this.geoQuery || '').trim();
            if (q.length < 2) {
                this.geoResults = [];
                return;
            }
            this.geoLoading = true;
            try {
                const params = new URLSearchParams({
                    q,
                    type: this.geoSearchType || 'city',
                });
                if (this.form.countries.length === 1) {
                    params.set('country', this.form.countries[0]);
                }
                const res = await fetch('{{ route('admin.meta.geo') }}?' + params.toString(), {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                this.geoResults = data.data || [];
            } catch (e) {
                this.geoResults = [];
            } finally {
                this.geoLoading = false;
            }
        },

        addGeoHit(hit) {
            const country = (hit.country_code || '').toUpperCase();
            if (country && !this.form.countries.includes(country)) {
                this.form.countries.push(country);
            }
            if ((hit.type || this.geoSearchType) === 'region') {
                if (this.form.regions.some(r => r.key === hit.key)) return;
                this.form.regions.push({
                    key: hit.key,
                    name: hit.name,
                    country: country,
                });
            } else {
                if (this.form.cities.some(c => c.key === hit.key)) return;
                this.form.cities.push({
                    key: hit.key,
                    name: hit.name,
                    region: hit.region || '',
                    country: country,
                    region_id: hit.region_id || null,
                });
            }
            this.geoQuery = '';
            this.geoResults = [];
        },

        removeCity(key) {
            this.form.cities = this.form.cities.filter(c => c.key !== key);
        },

        removeRegion(key) {
            this.form.regions = this.form.regions.filter(r => r.key !== key);
        },

        async refreshWhatsAppNumbers() {
            this.waLoading = true;
            this.waError = '';
            try {
                const res = await fetch('{{ route('admin.marketing.create.whatsapp-numbers') }}', {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (!data.success) {
                    this.waError = 'Could not sync WhatsApp numbers from Meta.';
                    return;
                }
                this.whatsappNumbers = data.data || [];
                if (this.whatsappNumbers.length) {
                    const match = this.whatsappNumbers.find(n => n.phone === this.form.whatsapp_phone_number);
                    if (!match) {
                        this.form.whatsapp_phone_number = this.whatsappNumbers[0].phone;
                    }
                }
            } catch (e) {
                this.waError = 'Could not reach Meta to list WhatsApp numbers. Check token / Business Manager sync.';
            } finally {
                this.waLoading = false;
            }
        },

        goTo(i) {
            // Only allow jumping back, or forward one step at a time if current step is valid
            if (i < this.stage) {
                this.stage = i;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }
            if (i === this.stage) return;
            if (i === this.stage + 1) {
                this.next();
            }
        },

        next() {
            if (this.stage === 0 && !this.connectionValid) {
                alert('Fix Meta connection before continuing. Sync from .env or Connect Meta.');
                return;
            }
            if (this.stage === 1) {
                if (!this.form.primary_text) { alert('Upload a creative so Gemini can generate ad copy.'); return; }
                if (!this.previewImage && !this.form.stock_image_id && !this.form.ai_image_path) {
                    alert('Upload a creative before continuing.');
                    return;
                }
                if (!this.form.name) this.form.name = (this.form.service_name || 'WhatsApp') + ' — Campaign';
                if (!this.form.adset_name) this.form.adset_name = this.form.name + ' — Ad Set';
                if (!this.form.ad_name) this.form.ad_name = this.form.name + ' — Ad';
            }
            if (this.stage === 2 && !this.form.name) {
                alert('Enter a campaign name.');
                return;
            }
            if (this.stage === 3 && (this.form.daily_budget_dollars < 1 || !this.form.countries.length)) {
                alert('Select at least one country and a daily budget of at least $1.');
                return;
            }
            if (this.stage === 4 && !this.hasWa()) {
                alert('Select or enter a WhatsApp delivery number.');
                return;
            }
            if (this.stage < this.stages.length - 1) {
                this.stage++;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                if (this.stage === 5) this.runPreflight();
            }
        },

        prev() {
            if (this.stage > 0) {
                this.stage--;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        },

        onFormatChange() {
            this.form.stock_image_id = '';
            this.form.ai_image_path = '';
            this.previewImage = null;
            this.mediaSource = '';
            this.creativeReady = false;
            this.mediaValidation = { valid: false, errors: [], warnings: [], width: null, height: null };
            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },

        setImageFormat(key) {
            if (this.form.image_format === key) return;
            const hadMedia = !!(this.previewImage || this.form.stock_image_id || this.form.ai_image_path);
            this.form.image_format = key;
            if (hadMedia) this.onFormatChange();
        },

        onWhatsAppSelect() {
            if (this.form.whatsapp_phone_number === '__custom__') {
                this.form.whatsapp_phone_custom = '';
                return;
            }
            this.form.whatsapp_chat_url = '';
        },

        applyTemplate(key) {
            const t = templates[key];
            if (!t) return;
            this.form.template_key = key;
            if (!this.form.service_name) this.form.service_name = t.label;
            if (!this.form.target_audience) this.form.target_audience = t.default_audience;
            if (!this.form.main_benefit) this.form.main_benefit = t.default_benefit;
            this.generateCopy();
        },

        applyAiCreative(data) {
            if (data.campaign_name) this.form.name = data.campaign_name;
            if (data.adset_name) this.form.adset_name = data.adset_name;
            if (data.ad_name) this.form.ad_name = data.ad_name;
            if (data.service_name) this.form.service_name = data.service_name;
            if (data.target_audience) this.form.target_audience = data.target_audience;
            if (data.main_benefit) this.form.main_benefit = data.main_benefit;
            if (data.primary_text) this.form.primary_text = data.primary_text;
            if (data.headline) this.form.headline = data.headline;
            if (data.description) this.form.description = data.description;
            if (data.whatsapp_prefill_message) this.form.whatsapp_prefill_message = data.whatsapp_prefill_message;
            if (data.image_format) this.form.image_format = data.image_format;
            if (data.image_path) {
                this.form.ai_image_path = data.image_path;
                this.form.stock_image_id = '';
            }
            if (data.image_url) this.previewImage = data.image_url;
            this.mediaSource = 'upload';
            this.creativeReady = true;
            this.mediaValidation = {
                valid: true,
                errors: [],
                warnings: [],
                width: data.width || null,
                height: data.height || null,
            };
            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },

        async generateCopy() {
            const res = await fetch('{{ route('admin.marketing.create.generate') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ ...this.form, variant: 'A' }),
            });
            const data = await res.json();
            if (data.primary_text) this.form.primary_text = data.primary_text;
            if (data.headline) this.form.headline = data.headline;
            if (data.description) this.form.description = data.description;
            if (data.whatsapp_prefill_message) this.form.whatsapp_prefill_message = data.whatsapp_prefill_message;
        },

        async regenerateCopyFromFields() {
            await this.generateCopy();
        },

        selectStock(img) {
            this.mediaTab = 'stock';
            this.form.stock_image_id = img.id;
            this.form.ai_image_path = '';
            this.previewImage = img.url;
            this.mediaSource = 'stock';
            this.mediaValidation = { valid: true, errors: [], warnings: [], width: img.width, height: img.height };
            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
            if (!this.form.primary_text) {
                this.form.service_name = this.form.service_name || img.label || 'Offer';
                this.generateCopy().then(() => {
                    if (!this.form.name) this.form.name = (this.form.service_name || 'WhatsApp') + ' — Campaign';
                    if (!this.form.adset_name) this.form.adset_name = this.form.name + ' — Ad Set';
                    if (!this.form.ad_name) this.form.ad_name = this.form.name + ' — Ad';
                    this.creativeReady = true;
                });
            } else {
                this.creativeReady = true;
            }
        },

        async onFileUpload(e) {
            const f = e.target.files[0];
            if (!f) return;
            this.form.stock_image_id = '';
            this.mediaTab = 'upload';
            this.aiAnalyzing = true;
            this.aiAnalyzeError = '';
            this.mediaValidation = { valid: false, errors: [], warnings: [], width: null, height: null };
            const fd = new FormData();
            fd.append('image', f);
            fd.append('image_format', this.form.image_format);
            try {
                const res = await fetch('{{ route('admin.marketing.create.analyze-creative') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: fd,
                });
                const data = await res.json();
                if (!data.success) {
                    this.aiAnalyzeError = data.message || 'Could not analyze creative.';
                    this.mediaValidation = { valid: false, errors: [this.aiAnalyzeError], warnings: [], width: null, height: null };
                    this.previewImage = null;
                    this.mediaSource = '';
                    this.creativeReady = false;
                    return;
                }
                this.applyAiCreative(data);
                if (data.image_format) this.previewPlacement = data.image_format === 'portrait_191' ? 'landscape_191' : (['feed_4x5','square_1x1','story_9x16'].includes(data.image_format) ? data.image_format : 'feed_4x5');
            } catch (err) {
                this.aiAnalyzeError = 'Upload or Gemini analysis failed. Check GOOGLE_AI_API_KEY and try again.';
                this.creativeReady = false;
            } finally {
                this.aiAnalyzing = false;
            }
        },

        async generateAiImage() {
            this.aiGenerating = true;
            this.aiError = '';
            try {
                const res = await fetch('{{ route('admin.marketing.create.generate-image') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.form),
                });
                const data = await res.json();
                if (!data.success) { this.aiError = data.message || 'Generation failed'; return; }
                this.form.ai_image_path = data.path;
                this.form.stock_image_id = '';
                this.previewImage = data.url;
                this.mediaSource = 'ai';
                this.mediaValidation = { valid: true, errors: [], warnings: [], width: data.width, height: data.height };
                if (data.format) this.form.image_format = data.format;
                if (this.$refs.fileInput) this.$refs.fileInput.value = '';
                if (!this.form.primary_text) await this.generateCopy();
                this.creativeReady = !!this.form.primary_text;
            } catch (err) {
                this.aiError = 'Could not generate image. Check GOOGLE_AI_API_KEY in .env.';
            } finally {
                this.aiGenerating = false;
            }
        },

        clearMedia() {
            this.previewImage = null;
            this.form.stock_image_id = '';
            this.form.ai_image_path = '';
            this.mediaSource = '';
            this.creativeReady = false;
            this.aiAnalyzeError = '';
            this.mediaValidation = { valid: false, errors: [], warnings: [], width: null, height: null };
            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },

        async runPreflight() {
            const fd = new FormData(document.getElementById('ad-studio-form'));
            const res = await fetch('{{ route('admin.marketing.create.preflight') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: fd,
            });
            const data = await res.json();
            this.preflightErrors = data.errors || [];
            this.checklist = data.checklist || [];
            this.score = data.score ?? 0;
        },

        onSubmit(e) {
            if (this.stage !== this.stages.length - 1 && e.submitter && e.submitter.name === 'activate') {
                e.preventDefault();
                this.stage = this.stages.length - 1;
                return;
            }
            if (!this.form.primary_text) {
                e.preventDefault();
                alert('Primary ad text is required — upload a creative first.');
                this.stage = 1;
                return;
            }
            const hasMedia = this.previewImage || this.form.stock_image_id || this.form.ai_image_path;
            if (!hasMedia) {
                e.preventDefault();
                alert('Please upload a creative image first.');
                this.stage = 1;
            }
        },
    };
}
</script>
@endpush
@endsection
