class SearchManager {

    /**
     * @type {HTMLFormElement}
     */
    #form;

    /**
     * @type {Object}
     */
    #options;

    /**
     * @param {Object} options
     * @param {string|HTMLFormElement} options.form
     * @param {string} options.contentSelector
     * @param {Object} options.loader
     * @param {string} options.loader.selector - Selector of the loader element
     * @param {Function} options.loader.show - Function to call to show the loader. The first argument is the loader selector and 'this' is bound to the search manager
     * @param {Function} options.loader.hide - Function to call to hide the loader. The first argument is the loader selector and 'this' is bound to the search manager
     * @param {Function} options.onFilterChange - Callback function to call when a filter input changes. The first argument is the search form, the second argument is the filter field that triggered the event, and 'this' is bound to the search manager. The default function will submit the form
     * @param {Function} options.onPageChange - Callback function to call when a page input changes. The first argument is the search form, the second argument is the page field that triggered the event, and 'this' is bound to the search manager. The default function will submit the form
     * @param {Function} options.onSortChange - Callback function to call when a sort input changes. The first argument is the search form, the second argument is the sort field that triggered the event, and 'this' is bound to the search manager. The default function will submit the form
     * @param {Function} options.onSubmit - Callback function to call when the form is submitted. The first argument is the search form, and 'this' is bound to the search manager. The default function will remove empty fields from the form before submitting it
     */
    constructor(options = {}) {
        this.#options = Object.assign({
                form: '#search-form',
                contentSelector: '#search-form', // Selector for the main content to replace
                loader: {
                    selector: '#ssm-overlay',
                    show: function(selector) { document.querySelector(selector).style.display = 'block'; },
                    hide: function(selector) { document.querySelector(selector).style.display = 'none'; },
                },
                onFilterChange: function (field) {
                    // Reset to the 1st page when the filters change to avoid empty results
                    this.#form.p.value = 1;

                    if (this.#isTypeableInput(field)) {
                        field.addEventListener('blur', () => this.#form.requestSubmit());

                        return;
                    }

                    this.#form.requestSubmit();
                },
                onPageChange: function () { this.#form.requestSubmit(); },
                onSortChange: function () { this.#form.requestSubmit(); },
                onSubmit: function () { this.#submitForm(); },
            },
            options
        );

        // Dynamically bind all functions in options to `this`
        Object.entries(this.#options).forEach(([key, value]) => {
            if (typeof value === 'function') {
                this.#options[key] = value.bind(this);
            }
        });

        this.#initializeForm();

        const initialContent = document.querySelector(this.#options.contentSelector);
        if (initialContent) {
            history.replaceState(
                { searchContent: initialContent.innerHTML },
                '',
                window.location.href
            );
        }

        window.addEventListener('popstate', (event) => {
            // Handle the navigation event (back/forward button)
            if (event.state?.searchContent) {
                const content = event.state.searchContent;
                const existingContent = document.querySelector(this.#options.contentSelector);

                if (content && existingContent) {
                    existingContent.innerHTML = content;
                }
            }
        });
    }

    #initializeForm() {
        let form = this.#options.form;

        if (typeof form === 'string') {
            form = document.querySelector(form);

            if (null === form) {
                throw new Error('Form not found');
            }
        }

        this.#form = form;

        this.#form.addEventListener('input', (event) => {
            const field = event.target;

            if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement)) {
                return;
            }

            field.dispatchEvent(new CustomEvent('search:form-changed', { bubbles: true }));

            // todo would be better to have a way to check the type of the field directly on the field, e.g. with a data attribute
            if (field.closest('.ssm-filter') !== null) {
                field.dispatchEvent(new CustomEvent('search:filter-changed', { bubbles: true }));
            } else if (field.name === 'p') {
                field.dispatchEvent(new CustomEvent('search:page-changed', { bubbles: true }));
            } else if (field.classList.contains('ssm-sort')) {
                field.dispatchEvent(new CustomEvent('search:sort-changed', { bubbles: true }));
            }
        });

        this.#form.addEventListener('search:filter-changed', (event) => this.#options.onFilterChange(event.target));
        this.#form.addEventListener('search:page-changed', (event) => this.#options.onPageChange(event.target));
        this.#form.addEventListener('search:sort-changed', (event) => this.#options.onSortChange(event.target));
        this.#form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.#options.onSubmit();
        });
    }

    async #submitForm() {
        this.#options.loader.show(this.#options.loader.selector);
        this.disableEmptyFields();

        try {
            const url = this.#buildUrl();
            const response = await fetch(url, {
                method: this.#form.method.toUpperCase(),
            });

            if (!response.ok) {
                throw new Error(`Request failed with status: ${response.status}`);
            }

            const text = await response.text();
            const newContent = this.parseResponseContent(text, this.#options.contentSelector);
            const existingContent = document.querySelector(this.#options.contentSelector);

            if (newContent && existingContent) {
                existingContent.replaceWith(newContent);
                this.#initializeForm();
            }

            history.pushState({ searchContent: newContent.innerHTML}, '', url);
        } catch (error) {
            console.error('Error fetching search results:', error);
        } finally {
            this.#options.loader.hide(this.#options.loader.selector);
        }
    }

    #buildUrl() {
        const formData = new FormData(this.#form);
        const url = new URL(this.#form.action);

        url.search = '';

        formData.forEach((value, key) => {
            if (value !== '') {
                url.searchParams.append(key, value);
            }
        });

        return url.toString();
    }

    disableEmptyFields() {
        for (const field of this.#form.querySelectorAll('input,select')) {
            if (field.value === '') {
                field.disabled = true;
            }
        }
    }

    parseResponseContent(html, selector) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        return doc.querySelector(selector);
    }

    /**
     * @param {HTMLInputElement} field
     * @return {boolean}
     */
    #isTypeableInput(field) {
        if (field.tagName.toLowerCase() !== 'input') {
            return false;
        }

        return ['text', 'number', 'email', 'password', 'search', 'tel', 'url'].includes(field.type.toLowerCase());
    }
}

new SearchManager(window.ssmSearch || {});
