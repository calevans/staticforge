(function(window) {
    'use strict';

    const StaticForgeSearch = {
        index: null,
        options: {
            inputSelector: '#search-input',
            resultsSelector: '#search-results',
            minisearchPath: '/assets/js/minisearch.min.js',
            indexPath: '/search.json'
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

            // Load MiniSearch script if not present
            if (typeof MiniSearch === 'undefined') {
                const script = document.createElement('script');
                script.src = this.options.minisearchPath;
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
                    this.index = new MiniSearch({
                        fields: ['title', 'text', 'tags', 'category'], // fields to index for full-text search
                        storeFields: ['title', 'url', 'tags', 'category'], // fields to return with search results
                        searchOptions: {
                            boost: { title: 2, tags: 1.5 },
                            fuzzy: 0.2
                        }
                    });
                    this.index.addAll(data);
                    console.log('StaticForge Search Index Loaded');
                })
                .catch(err => console.error('Failed to load search index', err));
        },

        handleInput: function(query) {
            if (!this.index || query.length < 2) {
                this.renderResults([]);
                return;
            }

            const results = this.index.search(query);
            this.renderResults(results);
        },

        renderResults: function(results) {
            const container = document.querySelector(this.options.resultsSelector);
            if (!container) return;

            if (results.length === 0) {
                container.innerHTML = '';
                container.style.display = 'none';
                return;
            }

            const html = results.slice(0, 10).map(result => `
                <div class="search-result-item">
                    <a href="${result.url}">
                        <div class="search-result-title">${result.title}</div>
                        <div class="search-result-meta">
                            ${result.category ? `<span class="badge">${result.category}</span>` : ''}
                        </div>
                    </a>
                </div>
            `).join('');

            container.innerHTML = html;
            container.style.display = 'block';
        }
    };

    window.StaticForgeSearch = StaticForgeSearch;

})(window);
