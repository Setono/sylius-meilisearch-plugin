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
     * @param {string|HTMLFormElement} form
     * @param {Object} options
     * @param {Object} options.loader
     * @param {string} options.loader.selector - Selector of the loader element
     * @param {Function} options.loader.show - Function to call to show the loader. The first argument is the loader selector and 'this' is bound to the search manager
     * @param {Function} options.loader.hide - Function to call to hide the loader. The first argument is the loader selector and 'this' is bound to the search manager
     * @param {Function} options.onFacetChange - Callback function to call when a facet input changes. The first argument is the search form, the second argument is the facet field that triggered the event, and 'this' is bound to the search manager. The default function will submit the form
     * @param {Function} options.onPageChange - Callback function to call when a page input changes. The first argument is the search form, the second argument is the page field that triggered the event, and 'this' is bound to the search manager. The default function will submit the form
     * @param {Function} options.onSortChange - Callback function to call when a sort input changes. The first argument is the search form, the second argument is the sort field that triggered the event, and 'this' is bound to the search manager. The default function will submit the form
     * @param {Function} options.onSubmit - Callback function to call when the form is submitted. The first argument is the search form, and 'this' is bound to the search manager. The default function will remove empty fields from the form before submitting it
     */
    constructor(form, options = {}) {
        if(typeof form === 'string') {
            form = document.querySelector(form);
        }

        if(null === form) {
            throw new Error('Form not found');
        }

        this.#form = form;

        this.#options = Object.assign({
                loader: {
                    selector: '#ssm-overlay',
                    show: function(selector) { document.querySelector(selector).style.display = 'block'; },
                    hide: function(selector) { document.querySelector(selector).style.display = 'none'; },
                },
                onFacetChange: function (form, field) {
                    if(this.#isTypeableInput(field)) {
                        field.addEventListener('blur', function () {
                            form.requestSubmit();
                        });

                        return;
                    }

                    form.requestSubmit();
                },
                onPageChange: function (form) { form.requestSubmit(); },
                onSortChange: function (form) { form.requestSubmit(); },
                onSubmit: function () { this.#options.loader.show.bind(this, this.#options.loader.selector)(); this.disableEmptyFields(); },
            },
            options
        );

        this.#form.addEventListener('input', function (event) {
            /** @type {HTMLInputElement} */
            const field = event.target;

            field.dispatchEvent(new CustomEvent('search:form-changed'));

            if(field.name.startsWith('facets')) {
                field.dispatchEvent(new CustomEvent('search:facet-changed', { bubbles: true }));
            } else if(field.name === 'p') {
                field.dispatchEvent(new CustomEvent('search:page-changed', { bubbles: true }));
            } else if(field.name.startsWith('sort')) {
                field.dispatchEvent(new CustomEvent('search:sort-changed', { bubbles: true }));
            }
        });

        this.#form.addEventListener('search:facet-changed', (event) => this.#options.onFacetChange.bind(this, this.#form, event.target)());
        this.#form.addEventListener('search:page-changed', (event) => this.#options.onSortChange.bind(this, this.#form, event.target)());
        this.#form.addEventListener('search:sort-changed', (event) => this.#options.onSortChange.bind(this, this.#form, event.target)());
        this.#form.addEventListener('submit', () => this.#options.onSubmit.bind(this, this.#form)());
    }

    disableEmptyFields() {
        for (const field of this.#form.querySelectorAll('input,select')) {
            if (!field.value) {
                field.disabled = true;
            }
        }
    }

    /**
     * @param {HTMLInputElement} field
     * @return {boolean}
     */
    #isTypeableInput(field) {
        if(field.tagName.toLowerCase() !== 'input') {
            return false;
        }

        return ['text', 'number', 'email', 'password', 'search', 'tel', 'url'].includes(field.type.toLowerCase());
    }
}
