export default function facebookPageSearch(config) {
    return {
        searchUrl: config.searchUrl,
        inputId: config.inputId,
        query: config.initialName || '',
        selectedPageId: config.initialId || '',
        selectedPageName: config.initialName || '',
        results: [],
        open: false,
        searching: false,
        searched: false,
        highlightIndex: 0,
        debounceTimer: null,

        init() {
            this.$watch('query', (value) => {
                if (this.debounceTimer) {
                    clearTimeout(this.debounceTimer);
                }

                this.debounceTimer = setTimeout(() => {
                    this.performSearch(value);
                }, 350);
            });

            if (this.query.trim().length >= 2) {
                this.performSearch(this.query);
            }
        },

        performSearch(value) {
            const term = (value || '').trim();

            if (this.selectedPageId && term !== this.selectedPageName) {
                this.selectedPageId = '';
                this.selectedPageName = '';
            }

            if (term.length < 2) {
                this.results = [];
                this.searched = false;
                this.closeResults();
                return;
            }

            this.searching = true;

            fetch(this.searchUrl + '?q=' + encodeURIComponent(term), {
                headers: { Accept: 'application/json' },
            })
                .then((response) => response.json())
                .then((data) => {
                    this.results = data.pages || [];
                    this.searched = true;
                    this.highlightIndex = 0;
                    this.open = this.results.length > 0;
                })
                .catch(() => {
                    this.results = [];
                    this.searched = true;
                    this.open = false;
                })
                .finally(() => {
                    this.searching = false;
                });
        },

        select(page) {
            this.selectedPageId = String(page.id);
            this.selectedPageName = page.name;
            this.query = page.name;
            this.searched = true;
            this.closeResults();
        },

        selectHighlighted() {
            if (!this.open || !this.results.length) {
                return;
            }

            this.select(this.results[this.highlightIndex] || this.results[0]);
        },

        highlightNext() {
            if (!this.results.length) {
                return;
            }

            this.highlightIndex = (this.highlightIndex + 1) % this.results.length;
        },

        highlightPrev() {
            if (!this.results.length) {
                return;
            }

            this.highlightIndex = (this.highlightIndex - 1 + this.results.length) % this.results.length;
        },

        openResults() {
            if (this.results.length) {
                this.open = true;
            }
        },

        closeResults() {
            this.open = false;
        },
    };
}
