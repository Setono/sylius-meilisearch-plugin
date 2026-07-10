import { test, expect, Page } from '@playwright/test';

// The search form is AJAX-driven (src/Resources/public/js/search.js): interacting with a
// filter/sort/page control submits via fetch, replaces the #search-form node, and pushes the
// new URL with history.pushState. So we always wait on the URL as the completion signal before
// re-reading the (replaced) DOM.

const names = (page: Page) => page.locator('.sylius-product-name');

async function prices(page: Page): Promise<number[]> {
    const texts = await page.locator('.sylius-product-price').allTextContents();
    return texts.map((t) => parseFloat(t.replace(/[^0-9.]/g, '')));
}

test.describe('shop search page', () => {
    test('shows results for a query', async ({ page }) => {
        await page.goto('/en_US/search?q=jeans');

        await expect(page.locator('h1')).toContainText('jeans');
        await expect(page.getByText('8 results')).toBeVisible();
        await expect(names(page)).toHaveCount(3); // hits_per_page: 3
    });

    test('shows a message when there are no results', async ({ page }) => {
        await page.goto('/en_US/search?q=thisdoesnotexistxyz');

        await expect(page.locator('.ui.message .header')).toHaveText('No results found');
        await expect(names(page)).toHaveCount(0);
    });

    test('paginates through the result set', async ({ page }) => {
        await page.goto('/en_US/search?q=jeans');
        const firstOnPage1 = await names(page).first().textContent();

        // Pagination is anchor links; clicking "Next" (rel=next) fires an AJAX submit + pushState
        await page.locator('nav.ssm-pagination a[rel="next"]').click();
        await expect(page).toHaveURL(/[?&]p=2(&|$)/);
        await expect(names(page).first()).not.toHaveText(firstOnPage1 ?? '');

        // The current page link is marked aria-current
        await expect(page.locator('nav.ssm-pagination a[aria-current="page"]')).toHaveText('2');

        // Last page holds the remaining 2 of 8 hits and has no "Next" control
        await page.goto('/en_US/search?q=jeans&p=3');
        await expect(names(page)).toHaveCount(2);
        await expect(page.locator('nav.ssm-pagination a[rel="next"]')).toHaveCount(0);
    });

    test('filters by a brand facet', async ({ page }) => {
        await page.goto('/en_US/search?q=jeans');

        // Checking the box auto-submits (there is no submit button in the form)
        await page.locator('.ssm-filters input[name="f[brand][]"][value="Celsius Small"]').check();
        await expect(page).toHaveURL(/f%5Bbrand%5D%5B%5D=Celsius/);
        await expect(page.getByText('1 result', { exact: true })).toBeVisible();
        await expect(names(page)).toHaveCount(1);
    });

    test('announces the result count and restores focus after an AJAX swap', async ({ page }) => {
        await page.goto('/en_US/search?q=jeans');

        // The hits count is an aria-live region so screen readers announce updates
        await expect(page.locator('.ssm-hits-count[role="status"][aria-live="polite"]')).toBeVisible();

        const checkbox = page.locator('.ssm-filters input[name="f[brand][]"][value="Celsius Small"]');
        await checkbox.focus();
        await checkbox.check();
        await expect(page).toHaveURL(/f%5Bbrand%5D%5B%5D=Celsius/);

        // After the swap, focus is restored to the equivalent field (not lost to <body>)
        const focusedName = await page.evaluate(() => document.activeElement?.getAttribute('name'));
        expect(focusedName).toBe('f[brand][]');
    });

    test('filters by a price range', async ({ page }) => {
        await page.goto('/en_US/search?q=jeans');

        // The min/max inputs are pre-filled with the current result set's bounds.
        // Narrow to the upper half so at least one product still matches (prices are randomized).
        const min = page.locator('#f_price_min');
        const max = page.locator('#f_price_max');
        const lo = parseFloat((await min.inputValue()) || '0');
        const hi = parseFloat((await max.inputValue()) || '0');
        const threshold = Math.ceil((lo + hi) / 2);

        // Typeable inputs submit on blur; each submit replaces the form node, so serialize
        await min.fill(String(threshold));
        await min.blur();
        await expect(page).toHaveURL(/f%5Bprice%5D%5Bmin%5D/);
        // The untouched max input still equals its default, so it contributes no param to the URL
        await expect(page).not.toHaveURL(/f%5Bprice%5D%5Bmax%5D/);

        await expect(names(page).first()).toBeVisible();
        for (const price of await prices(page)) {
            expect(price).toBeGreaterThanOrEqual(threshold);
        }

        // An impossible window renders the no-results message inside the results form
        await page.goto('/en_US/search?q=jeans&f[price][min]=999999');
        await expect(page.locator('.ui.message .header')).toHaveText('No results found');
    });

    test('keeps the filter UI on the no-results page so the shopper can recover', async ({ page }) => {
        // Over-filter into an empty result set
        await page.goto('/en_US/search?q=jeans&f[brand][]=Celsius%20Small&f[price][min]=999999');
        await expect(page.locator('.ui.message .header')).toHaveText('No results found');

        // The facets are still rendered (the whole results form is kept), with the selection checked
        const brand = page.locator('.ssm-filters input[name="f[brand][]"][value="Celsius Small"]');
        await expect(brand).toBeVisible();

        // Relaxing the price filter recovers results without leaving the page
        await page.locator('#f_price_min').fill('');
        await page.locator('#f_price_min').blur();
        await expect(page).not.toHaveURL(/f%5Bprice%5D%5Bmin%5D=999999/);
        await expect(names(page).first()).toBeVisible();
    });

    test('sorts by price ascending', async ({ page }) => {
        await page.goto('/en_US/search?q=jeans');

        await page.locator('select.ssm-sort').selectOption('price:asc'); // label: "Price: low to high"
        await expect(page).toHaveURL(/s=price%3Aasc/);

        const shown = await prices(page);
        expect(shown.length).toBeGreaterThan(0);
        expect(shown).toEqual([...shown].sort((a, b) => a - b));
    });
});

test.describe('shop search page — history & resilience', () => {
    test('restores a working form when navigating back and forward across a no-results state', async ({ page }) => {
        await page.goto('/en_US/search?q=jeans');
        await expect(names(page)).toHaveCount(3);

        // Force a no-results state via an impossible price floor (the typeable input submits on blur).
        // The results form (facets + sorting) is kept even with zero hits; the "no results" message
        // is shown in the items slot, so the shopper can still recover by relaxing a filter.
        await page.locator('#f_price_min').fill('999999');
        await page.locator('#f_price_min').blur();
        await expect(page).toHaveURL(/f%5Bprice%5D%5Bmin%5D=999999/);
        await expect(page.locator('.ui.message .header')).toHaveText('No results found');

        // Back: the results form (with products) must be restored, not left as the no-results div.
        await page.goBack();
        await expect(page).toHaveURL(/\/search\?q=jeans$/);
        await expect(names(page)).toHaveCount(3);

        // Forward: the no-results block must come back.
        await page.goForward();
        await expect(page).toHaveURL(/f%5Bprice%5D%5Bmin%5D=999999/);
        await expect(page.locator('.ui.message .header')).toHaveText('No results found');

        // Back once more and prove the restored form is still interactive (its listeners were re-wired).
        await page.goBack();
        await expect(names(page)).toHaveCount(3);
        await page.locator('.ssm-filters input[name="f[brand][]"][value="Celsius Small"]').check();
        await expect(page).toHaveURL(/f%5Bbrand%5D%5B%5D=Celsius/);
        await expect(page.getByText('1 result', { exact: true })).toBeVisible();
    });

    test('submits a typed range filter only once on blur', async ({ page }) => {
        await page.goto('/en_US/search?q=jeans');

        // Count only the AJAX search submits (same path, fetch/xhr) — not the document nav or
        // the browser's direct Meilisearch calls (those go to a different host).
        let searchRequests = 0;
        page.on('request', (req) => {
            if (['fetch', 'xhr'].includes(req.resourceType()) && /\/en_US\/search/.test(req.url())) {
                searchRequests++;
            }
        });

        // Type several characters (each fires an input event). The buggy version added one
        // blur listener per keystroke, so blur triggered several duplicate submits.
        const min = page.locator('#f_price_min');
        await min.click();
        await min.pressSequentially('123', { delay: 30 });
        await min.blur();

        await expect(page).toHaveURL(/f%5Bprice%5D%5Bmin%5D=/);
        await page.waitForTimeout(500); // let any duplicate submits fire before asserting
        expect(searchRequests).toBe(1);
    });

    test('falls back to a full page navigation when the AJAX request fails', async ({ page }) => {
        await page.goto('/en_US/search?q=jeans');

        // Abort only the first AJAX (fetch) submit; the fallback full-page navigation is a
        // document request and is allowed through.
        let aborted = false;
        await page.route('**/en_US/search**', async (route) => {
            if (!aborted && route.request().resourceType() === 'fetch') {
                aborted = true;
                await route.abort();
            } else {
                await route.continue();
            }
        });

        await page.locator('select.ssm-sort').selectOption('price:asc');

        // search.js catches the failed fetch and does window.location.assign(url) → full load.
        await expect(page).toHaveURL(/s=price%3Aasc/);
        await expect(names(page).first()).toBeVisible();
    });
});
