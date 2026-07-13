<div
    class="relative"
    x-data="facebookPageSearch({
        searchUrl: @json($searchUrl),
        initialId: @json($initialId ?? ''),
        initialName: @json($initialName ?? ''),
        inputId: @json($inputId ?? 'meta_page_search'),
    })"
    @click.outside="closeResults()"
>
    <label class="mb-1 block text-sm font-medium text-slate-700" :for="inputId">
        {{ $label ?? 'Your Facebook Page' }}
    </label>

    <input
        :id="inputId"
        type="text"
        x-model="query"
        @focus="openResults()"
        @keydown.escape.prevent="closeResults()"
        @keydown.arrow-down.prevent="highlightNext()"
        @keydown.arrow-up.prevent="highlightPrev()"
        @keydown.enter.prevent="selectHighlighted()"
        autocomplete="off"
        placeholder="{{ $placeholder ?? 'Type your Facebook Page name…' }}"
        class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-xander-navy focus:ring focus:ring-xander-navy/20"
        :class="{ 'border-emerald-400 ring-emerald-100': selectedPageId }"
    >

    <input type="hidden" name="meta_page_id" :value="selectedPageId" required>
    <input type="hidden" name="meta_page_name" :value="selectedPageName" required>

    <p class="mt-1 text-xs text-slate-500" x-show="searching">Searching Meta…</p>
    <p class="mt-1 text-xs text-slate-500" x-show="!searching && !selectedPageId && query.length > 0 && query.length < 2">
        Type at least 2 characters.
    </p>
    <p class="mt-1 text-xs text-slate-500" x-show="!searching && !selectedPageId && query.length >= 2 && results.length === 0 && searched">
        No matching page found under the platform Business Manager.
    </p>
    <p class="mt-1 text-xs font-medium text-emerald-700" x-show="selectedPageId">
        ✓ Mapped to <span x-text="selectedPageName"></span> (<span x-text="selectedPageId"></span>)
    </p>

    <ul
        x-show="open && results.length"
        x-cloak
        class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
        role="listbox"
    >
        <template x-for="(page, index) in results" :key="page.id">
            <li>
                <button
                    type="button"
                    class="flex w-full flex-col px-4 py-2.5 text-left text-sm hover:bg-slate-50"
                    :class="{ 'bg-xander-navy/5': highlightIndex === index }"
                    @click="select(page)"
                    @mouseenter="highlightIndex = index"
                >
                    <span class="font-medium text-slate-900" x-text="page.name"></span>
                    <span class="text-xs text-slate-500" x-text="'Page ID ' + page.id"></span>
                </button>
            </li>
        </template>
    </ul>
</div>
