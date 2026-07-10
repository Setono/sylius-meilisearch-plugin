/**
 * Progressively enhances the Sylius search results page.
 *
 * Instead of a full page reload, changing a filter / page / sort submits the search
 * form in the background (fetch), swaps the results markup in place, and updates the
 * browser URL so back/forward still work. If anything goes wrong it falls back to a
 * normal full page navigation, so search keeps working even without JavaScript.
 *
 * Customise it by defining `window.ssmSearch` (an options object) BEFORE this script
 * runs. The created instance is exposed as `window.ssmSearchManager`.
 */
class SearchManager {

    // Fields prefixed with `#` are private: only code inside this class can read them.

    /**
     * The results <form>, or null when the page shows the "no results" block
     * (which is a <div>, not a form).
     *
     * @type {HTMLFormElement|null}
     */
    #form = null;

    /**
     * @type {Object}
     */
    #options;

    /**
     * Lets us cancel a search request that is still running when a newer one starts,
     * so a slow response can never overwrite a fresher one.
     *
     * @type {AbortController|null}
     */
    #abortController = null;

    /**
     * Remembers which typeable fields already have a pending "submit on blur" listener,
     * so typing several characters doesn't stack up several duplicate listeners.
     * A WeakSet doesn't keep the elements alive if they're removed from the page.
     *
     * @type {WeakSet<HTMLElement>}
     */
    #pendingBlurSubmits = new WeakSet();

    /**
     * @param {Object} options
     * @param {string} options.form - CSS selector of the search form. Must be a selector (not an element): the form node is replaced on every search, so a stored element reference would go stale.
     * @param {string} options.contentSelector - CSS selector of the markup replaced on each search (defaults to the form itself).
     * @param {Object} options.loader
     * @param {string} options.loader.selector - Selector of the loader element.
     * @param {Function} options.loader.show - Shows the loader. Receives the loader selector; `this` is the search manager.
     * @param {Function} options.loader.hide - Hides the loader. Receives the loader selector; `this` is the search manager.
     * @param {Function} options.onFilterChange - Called when a filter field changes. Receives the field that changed; `this` is the search manager. Default: reset to page 1 and submit (typeable inputs submit on blur).
     * @param {Function} options.onPageChange - Called when the page field changes. Receives the field that changed; `this` is the search manager. Default: submit, then scroll the new results into view.
     * @param {Function} options.onSortChange - Called when the sort field changes. Receives the field that changed; `this` is the search manager. Default: submit.
     * @param {Function} options.onSubmit - Called when the form is submitted. Receives no arguments; `this` is the search manager. Default: run the background search.
     */
    constructor(options = {}) {
        const defaults = {
            form: '#search-form',
            contentSelector: '#search-form', // Selector for the main content to replace
            loader: {
                selector: '#ssm-overlay',
                show: function (selector) { document.querySelector(selector).style.display = 'block'; },
                hide: function (selector) { document.querySelector(selector).style.display = 'none'; },
            },
            onFilterChange: function (field) {
                // Reset to the 1st page when the filters change to avoid empty results.
                // The `p` field only exists when pagination is rendered, so guard it.
                const page = this.form.elements.namedItem('p');
                if (page) {
                    page.value = 1;
                }

                if (this.#isTypeableInput(field)) {
                    // Wait for the user to finish typing (blur) before submitting.
                    // Only add the listener once per field, otherwise every keystroke
                    // would add another one and the blur would fire many duplicate searches.
                    if (!this.#pendingBlurSubmits.has(field)) {
                        this.#pendingBlurSubmits.add(field);
                        field.addEventListener('blur', () => {
                            this.#pendingBlurSubmits.delete(field);
                            this.form.requestSubmit();
                        }, { once: true });
                    }

                    return;
                }

                this.form.requestSubmit();
            },
            onPageChange: function () {
                // Once the new results are swapped in, scroll them into view so the
                // change is visible (on mobile the new content is often below the fold).
                document.addEventListener('search:content-updated', (event) => {
                    event.detail.content.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, { once: true });

                this.form.requestSubmit();
            },
            onSortChange: function () { this.form.requestSubmit(); },
            onSubmit: function () { this.submit(); },
        };

        // `Object.assign(target, ...sources)` copies later sources over earlier ones,
        // so user `options` win over `defaults`.
        this.#options = Object.assign({}, defaults, options);

        // Merge the loader one level deeper so a user can override just `show` (or just
        // `hide`) without losing the other default loader properties.
        this.#options.loader = Object.assign({}, defaults.loader, options.loader);

        // Bind every callback to this instance so `this` inside a callback is the
        // search manager (both top-level callbacks and the loader functions).
        for (const key of Object.keys(this.#options)) {
            if (typeof this.#options[key] === 'function') {
                this.#options[key] = this.#options[key].bind(this);
            }
        }
        for (const key of Object.keys(this.#options.loader)) {
            if (typeof this.#options.loader[key] === 'function') {
                this.#options.loader[key] = this.#options.loader[key].bind(this);
            }
        }

        this.#initializeForm();

        // Remember the initial markup so pressing "back" to this page can restore it.
        // We store outerHTML (the whole element) rather than innerHTML so a form and a
        // "no results" div can replace each other cleanly.
        const initialContent = document.querySelector(this.#options.contentSelector);
        if (initialContent) {
            history.replaceState(
                { searchContent: initialContent.outerHTML },
                '',
                window.location.href
            );
        }

        window.addEventListener('popstate', (event) => {
            // Only react to states we created; ignore unrelated history entries.
            if (event.state?.searchContent) {
                const restored = this.#replaceContent(event.state.searchContent);

                if (restored) {
                    this.#notifyContentUpdated(restored);
                } else {
                    // Couldn't restore (e.g. a stale state from an older script version) —
                    // fall back to a normal reload so the page stays consistent.
                    window.location.reload();
                }
            }
        });
    }

    /**
     * The results form, or null when the page currently shows the "no results" block.
     *
     * @return {HTMLFormElement|null}
     */
    get form() {
        return this.#form;
    }

    /**
     * Runs the background search immediately (same as changing a filter/page/sort).
     *
     * @return {Promise<void>}
     */
    submit() {
        return this.#submitForm();
    }

    #initializeForm() {
        let form = this.#options.form;

        if (typeof form === 'string') {
            form = document.querySelector(form);

            if (null === form) {
                throw new Error('Form not found');
            }
        }

        // The "no results" response reuses the same id on a <div>. There's no form to
        // enhance in that case, so remember there's no form and stop.
        if (!(form instanceof HTMLFormElement)) {
            this.#form = null;

            return;
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

        // Pagination is a set of anchor links (accessible + crawlable). Intercept plain
        // left-clicks and navigate via the background search instead of a full page load;
        // modified clicks (new tab, etc.) fall through to normal navigation.
        this.#form.addEventListener('click', (event) => {
            const link = event.target instanceof Element ? event.target.closest('.ssm-pagination a') : null;
            if (link === null || !this.#form.contains(link)) {
                return;
            }
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            event.preventDefault();

            // Scroll the new results into view once swapped, as with the old page control.
            document.addEventListener('search:content-updated', (updated) => {
                updated.detail.content.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, { once: true });

            this.#submitForm(link.href);
        });
        this.#form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.#options.onSubmit();
        });
    }

    async #submitForm(targetUrl = null) {
        // Nothing to submit on the "no results" page.
        if (this.#form === null) {
            return;
        }

        // Cancel a still-running search so its (older) response can't overwrite this one,
        // then start a fresh controller for the new request.
        this.#abortController?.abort();
        const controller = new AbortController();
        this.#abortController = controller;

        // Pagination links pass their own URL; the form controls build the URL from form state.
        const url = targetUrl ?? this.#buildUrl();
        let navigating = false;

        this.#options.loader.show(this.#options.loader.selector);

        try {
            const response = await fetch(url, {
                method: this.#form.method.toUpperCase(),
                signal: controller.signal,
            });

            if (!response.ok) {
                throw new Error(`Request failed with status: ${response.status}`);
            }

            const newContent = this.#replaceContent(await response.text());
            if (newContent === null) {
                throw new Error(`Response did not contain an element matching "${this.#options.contentSelector}"`);
            }

            // Save the new markup in the history entry so back/forward can restore it.
            history.pushState({ searchContent: newContent.outerHTML }, '', url);
            this.#notifyContentUpdated(newContent);
        } catch (error) {
            // An aborted request was superseded on purpose — the newer request owns the UI.
            if (error.name === 'AbortError') {
                return;
            }

            // Any real failure: fall back to a full page load so the user still gets results.
            console.error('Search request failed, falling back to full page load:', error);
            navigating = true;
            window.location.assign(url);
        } finally {
            // Only tidy up if we're still the current request (a superseding request
            // returned early above and must keep its own loader visible).
            if (this.#abortController === controller) {
                this.#abortController = null;

                // Keep the loader up while the browser navigates away in the fallback case.
                if (!navigating) {
                    this.#options.loader.hide(this.#options.loader.selector);
                }
            }
        }
    }

    /**
     * Replaces the current content element with the matching element parsed from `html`,
     * then re-wires the form. Shared by the fetch flow and back/forward restoration.
     *
     * @param {string} html - Full page HTML or the element's outerHTML.
     * @return {Element|null} The new element, or null if it couldn't be found/replaced.
     */
    #replaceContent(html) {
        const newContent = this.parseResponseContent(html, this.#options.contentSelector);
        const existingContent = document.querySelector(this.#options.contentSelector);

        if (!newContent || !existingContent) {
            return null;
        }

        existingContent.replaceWith(newContent);
        this.#initializeForm();

        return newContent;
    }

    /**
     * Emits a bubbling `search:content-updated` event so other scripts can re-initialise
     * widgets after the results markup is swapped.
     *
     * @param {Element} content
     */
    #notifyContentUpdated(content) {
        content.dispatchEvent(new CustomEvent('search:content-updated', {
            bubbles: true,
            detail: { content },
        }));
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

// Auto-start using optional user options, and expose the instance for further use.
window.ssmSearchManager = new SearchManager(window.ssmSearch || {});
