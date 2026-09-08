(function(window) {
    'use strict';

    const StaticForgeSearch = {
        index: null,
        options: {
            inputSelector: '#search-input',
            resultsSelector: '#search-results',
            fusePath: '/assets/js/fuse.basic.min.js',
            indexPath: '/search.json',
            dedupePages: true
        },

        init: function(options) {
            this.options = { ...this.options, ...options };
            
            const input = document.querySelector(this.options.inputSelector);
            if (!input) return;

            // Lazy load on focus or hover
            const loadHandler = () => {
                this.loadDependencies();
                input.removeEventListener('focus', loadHandler);
                input.removeEventListener('mouseover', loadHandler);
            };

            input.addEventListener('focus', loadHandler);
            input.addEventListener('mouseover', loadHandler);
            
            // Bind search event
            input.addEventListener('input', (e) => this.handleInput(e.target.value));
        },

        loadDependencies: function() {
            if (this.index) return; // Already loaded

            // Load Fuse script if not present
            if (typeof Fuse === 'undefined') {
                const script = document.createElement('script');
                script.src = this.options.fusePath;
                script.onload = () => this.loadIndex();
                document.head.appendChild(script);
            } else {
                this.loadIndex();
            }
        },

        loadIndex: function() {
            fetch(this.options.indexPath)
                .then(response => response.json())
                .then(data => {
                    this.index = new Fuse(data, {
                        keys: [
                            { name: 'title', weight: 2 },
                            { name: 'tags', weight: 1.5 },
                            { name: 'text', weight: 1 },
                            { name: 'category', weight: 1 }
                        ],
                        threshold: 0.3, // 0.0 = perfect match, 1.0 = match anything
                        ignoreLocation: true, // Search anywhere in the string
                        minMatchCharLength: 2
                    });
                    console.log('StaticForge Search Index Loaded (Fuse.js)');
                })
                .catch(err => console.error('Failed to load search index', err));
        },

        handleInput: function(query) {
            if (!this.index || query.length < 2) {
                this.renderResults([]);
                return;
            }

            const results = this.index.search(query);
            // Fuse returns { item: { ... }, refIndex: 0, score: 0.1 } sorted best (lowest score) first
            const deduped = this.options.dedupePages ? this.dedupeByPage(results) : results;
            this.renderResults(deduped.map(result => result.item));
        },

        // Collapse multiple section hits from the same page, keeping the lowest (best) score,
        // displayed under the page's own title rather than the winning section's heading.
        dedupeByPage: function(results) {
            const bestByPage = new Map();

            results.forEach((result, index) => {
                const baseUrl = result.item.url.split('#')[0];
                const existing = bestByPage.get(baseUrl);
                if (!existing) {
                    bestByPage.set(baseUrl, { result, index });
                }
            });

            return Array.from(bestByPage.values())
                .sort((a, b) => a.index - b.index)
                .map(entry => ({
                    ...entry.result,
                    item: {
                        ...entry.result.item,
                        title: entry.result.item.pageTitle || entry.result.item.title
                    }
                }));
        },

        renderResults: function(results) {
            const container = document.querySelector(this.options.resultsSelector);
            if (!container) return;

            if (results.length === 0) {
                container.replaceChildren();
                container.style.display = 'none';
                return;
            }

            const items = results.slice(0, 10).map(result => {
                const item = document.createElement('div');
                item.className = 'search-result-item';

                const link = document.createElement('a');
                link.href = result.url;

                const title = document.createElement('div');
                title.className = 'search-result-title';
                title.textContent = result.title;
                link.appendChild(title);

                const meta = document.createElement('div');
                meta.className = 'search-result-meta';
                if (result.category) {
                    const badge = document.createElement('span');
                    badge.className = 'badge';
                    badge.textContent = result.category;
                    meta.appendChild(badge);
                }
                link.appendChild(meta);

                item.appendChild(link);
                return item;
            });

            container.replaceChildren(...items);
            container.style.display = 'block';
        }
    };

    // Expose to window
    window.StaticForgeSearch = StaticForgeSearch;

})(window);
