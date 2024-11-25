const { autocomplete } = window['@algolia/autocomplete-js'];
const { meilisearchAutocompleteClient, getMeilisearchResults } = window['@meilisearch/autocomplete-client'];

/**
 * @typedef {object} Source
 * @property {string} id
 * @property {string} index
 * @property {?string} urlAttribute
 * @property {object} templates
 *
 * @type {object} configuration
 * @property {string} host
 * @property {string} searchKey
 * @property {string} container
 * @property {string} placeholder
 * @property {?string} searchPath
 * @property {?string} searchParameter
 * @property {boolean} debug
 * @property {Source[]} sources
 */
const configuration = JSON.parse(document.getElementById('ssm-autocomplete-configuration').textContent);

const searchClient = meilisearchAutocompleteClient({
    url: configuration.host,
    apiKey: configuration.searchKey,
});

const autocompleteConfig = {
    debug: configuration.debug,
    container: configuration.container,
    placeholder: configuration.placeholder,
    getSources({ query }) {
        const sources = [];

        configuration.sources.forEach(source => {
            const s = {
                sourceId: source.id,
                getItems() {
                    return getMeilisearchResults({
                        searchClient,
                        queries: [
                            {
                                indexName: source.index,
                                query,
                            },
                        ],
                    })
                },
            };

            if(Object.keys(source.templates).length !== 0) {
                s.templates = {};

                for (const [key, template] of Object.entries(source.templates)) {
                    s.templates[key] = ({item, components, html}) => eval('html`' + template + '`');
                }
            }

            if(source.urlAttribute) {
                s.onSelect = ({item}) => {
                    location.href = item[source.urlAttribute];
                }

                s.getItemUrl = ({item}) => {
                    return item[source.urlAttribute];
                }
            }

            sources.push(s);
        });

        return sources;
    }
}

if(configuration.searchParameter) {
    autocompleteConfig.initialState = {
        query: new URL(window.location).searchParams.get(configuration.searchParameter),
    };

    if (configuration.searchPath) {
        autocompleteConfig.onSubmit = ({event, state}) => {
            location.href = `${configuration.searchPath}?${configuration.searchParameter}=${state.query}`;
        }
    }
}

autocomplete(Object.assign(window.ssmAutocomplete || {}, autocompleteConfig));
