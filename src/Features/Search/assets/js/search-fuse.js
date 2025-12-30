(function(window) {
    'use strict';

    const StaticForgeSearch = {
        index: null,
        options: {
            inputSelector: '#search-input',
            resultsSelector: '#search-results',
            fusePath: '/assets/js/fuse.basic.min.js',
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
            // Fuse returns { item: { ... }, refIndex: 0, score: 0.1 }
            // We map it to just the item for the renderer
            const mappedResults = results.map(result => result.item);
            this.renderResults(mappedResults);
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

    // Expose to window
    window.StaticForgeSearch = StaticForgeSearch;

})(window);
