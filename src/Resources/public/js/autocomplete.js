/**
 * Wires the Algolia autocomplete-js widget to Meilisearch for the search input.
 *
 * Reads its settings from a JSON <script id="ssm-autocomplete-configuration"> that the
 * plugin renders server-side. Everything can be overridden by defining `window.ssmAutocomplete`
 * (an autocomplete-js options object) BEFORE this script runs; user options win.
 *
 * The whole file is wrapped in an IIFE (an immediately-invoked function) so its variables
 * stay private instead of leaking into the global scope.
 */
(function () {
    'use strict';

    /**
     * @typedef {Object} Source
     * @property {string} id - Source id (the index name).
     * @property {string} index - Resolved Meilisearch index uid to query.
     * @property {?string} urlAttribute - Item attribute holding the URL to open when selected.
     * @property {Object.<string, string>} templates - Map of template name to an Algolia `html` template string.
     * @property {number} limit - Maximum number of suggestions to fetch for this source.
     */

    /**
     * @typedef {Object} Configuration
     * @property {string} host - Meilisearch server URL (queried directly from the browser).
     * @property {string} searchKey - Search-only Meilisearch API key.
     * @property {string} container - CSS selector the autocomplete mounts into.
     * @property {string} placeholder - Input placeholder text.
     * @property {?string} searchPath - URL of the search results page (used by onSubmit and the see-all footer).
     * @property {?string} searchParameter - Query-string parameter carrying the search query.
     * @property {?string} seeAllLabel - Label for the "see all results" footer link.
     * @property {boolean} debug - Keeps the panel open for inspection when true.
     * @property {Source[]} sources - Sources to search.
     */

    const { autocomplete } = window['@algolia/autocomplete-js'];

    // The Meilisearch autocomplete client attaches to a global whose name changed between
    // bundle versions (0.7.x uses `autocompleteClient`, older 0.3.x used the package name),
    // so look under both and fail loudly if the library didn't load.
    const meilisearchClient = window.autocompleteClient || window['@meilisearch/autocomplete-client'];
    if (!meilisearchClient) {
        throw new Error('[SetonoSyliusMeilisearchPlugin] The Meilisearch autocomplete client did not load. Make sure meilisearch.autocomplete.js is included before autocomplete.js.');
    }
    const { meilisearchAutocompleteClient, getMeilisearchResults } = meilisearchClient;

    const configurationElement = document.getElementById('ssm-autocomplete-configuration');
    if (configurationElement === null) {
        throw new Error('[SetonoSyliusMeilisearchPlugin] Configuration element "#ssm-autocomplete-configuration" not found. Make sure {{ ssm_autocomplete_configuration() }} is rendered before autocomplete.js runs.');
    }

    /** @type {Configuration} */
    const configuration = JSON.parse(configurationElement.textContent);

    // Read the user overrides once, and never mutate the user's object.
    const userConfig = window.ssmAutocomplete || {};

    const searchClient = meilisearchAutocompleteClient({
        url: configuration.host,
        apiKey: configuration.searchKey,
    });

    /**
     * Turns a template string (from the server-rendered configuration) into a function
     * autocomplete-js can call to render an item. Compiling with `new Function` once is much
     * cheaper than the previous `eval` on every keystroke — but, like eval, it needs the CSP
     * directive `script-src 'unsafe-eval'`. Shops with a stricter CSP can avoid this entirely
     * by supplying their own `getSources` via `window.ssmAutocomplete` (see below).
     *
     * The compiled function only sees `item`, `components` and `html`.
     *
     * @param {string} template
     * @return {Function}
     */
    const compileTemplate = (template) =>
        new Function('{ item, components, html }', 'return html`' + template + '`;');

    /**
     * Debounces a promise-returning function: only the most recent call within `time` ms resolves.
     * Used so a fast typer doesn't fire one Meilisearch request per character (stale-response
     * ordering is already handled by the library, so this is purely a load win).
     */
    function debouncePromise(fn, time) {
        let timer;

        return (...args) => {
            if (timer) {
                clearTimeout(timer);
            }

            return new Promise((resolve) => {
                timer = setTimeout(() => resolve(fn(...args)), time);
            });
        };
    }

    const debounced = debouncePromise((sources) => sources, 200);

    const autocompleteConfig = {
        debug: configuration.debug,
        container: configuration.container,
        placeholder: configuration.placeholder,
    };

    // Build the default sources only when the user hasn't supplied their own `getSources`.
    // Skipping this means a shop that overrides `getSources` never triggers the `new Function`
    // path at all, so it can run under a CSP without 'unsafe-eval'.
    if (typeof userConfig.getSources !== 'function') {
        // Compile every source's templates ONCE now, not once per keystroke.
        const compiledSources = configuration.sources.map((source) => {
            const templates = {};

            for (const [key, template] of Object.entries(source.templates)) {
                try {
                    templates[key] = compileTemplate(template);
                } catch (error) {
                    throw new Error(`[SetonoSyliusMeilisearchPlugin] Failed to compile the "${key}" template for source "${source.id}": ${error.message}`);
                }
            }

            return { source, templates };
        });

        autocompleteConfig.getSources = ({ query }) => debounced(compiledSources.map(({ source, templates }) => {
            const s = {
                sourceId: source.id,
                getItems() {
                    return getMeilisearchResults({
                        searchClient,
                        queries: [
                            {
                                indexName: source.index,
                                query,
                                params: { hitsPerPage: source.limit },
                            },
                        ],
                    });
                },
            };

            const sourceTemplates = { ...templates };

            // A "see all results" footer linking to the full search page for the current query.
            if (configuration.searchPath && configuration.searchParameter && query !== '') {
                const seeAllUrl = new URL(configuration.searchPath, window.location.origin);
                seeAllUrl.searchParams.set(configuration.searchParameter, query);

                sourceTemplates.footer = ({ items, html }) => {
                    if (items.length === 0) {
                        return '';
                    }

                    return html`<div class="aa-SourceFooter">
                        <a class="aa-SeeAllLink" href="${seeAllUrl.toString()}">${configuration.seeAllLabel || 'See all results'}</a>
                    </div>`;
                };
            }

            if (Object.keys(sourceTemplates).length !== 0) {
                s.templates = sourceTemplates;
            }

            if (source.urlAttribute) {
                // Open the item's URL both on click and on keyboard selection.
                s.onSelect = ({ item }) => { location.href = item[source.urlAttribute]; };
                s.getItemUrl = ({ item }) => item[source.urlAttribute];
            }

            return s;
        }));
    }

    if (configuration.searchParameter) {
        autocompleteConfig.initialState = {
            // searchParams.get() returns null when the parameter is absent; the widget wants a string.
            query: new URL(window.location).searchParams.get(configuration.searchParameter) ?? '',
        };

        if (configuration.searchPath) {
            autocompleteConfig.onSubmit = ({ state }) => {
                // Build the URL with URLSearchParams so a query containing &, #, + etc. is encoded.
                const url = new URL(configuration.searchPath, window.location.origin);
                url.searchParams.set(configuration.searchParameter, state.query);
                window.location.assign(url.toString());
            };
        }
    }

    // Spread order = user options win over ours, without mutating either object.
    autocomplete({ ...autocompleteConfig, ...userConfig });
})();
