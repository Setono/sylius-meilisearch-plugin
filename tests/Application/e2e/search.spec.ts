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

        // Pagination is Previous/Next; clicking "Next" fires an AJAX submit + pushState
        await page.locator('nav.ssm-pagination label', { hasText: 'Next' }).click();
        await expect(page).toHaveURL(/[?&]p=2(&|$)/);
        await expect(names(page).first()).not.toHaveText(firstOnPage1 ?? '');

        // Last page holds the remaining 2 of 8 hits and has no "Next" control
        await page.goto('/en_US/search?q=jeans&p=3');
        await expect(names(page)).toHaveCount(2);
        await expect(page.locator('nav.ssm-pagination label', { hasText: 'Next' })).toHaveCount(0);
    });

    test('filters by a brand facet', async ({ page }) => {
        await page.goto('/en_US/search?q=jeans');

        // Checking the box auto-submits (there is no submit button in the form)
        await page.locator('.ssm-filters input[name="f[brand][]"][value="Celsius Small"]').check();
        await expect(page).toHaveURL(/f%5Bbrand%5D%5B%5D=Celsius/);
        await expect(page.getByText('1 results')).toBeVisible();
        await expect(names(page)).toHaveCount(1);
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

        await expect(names(page).first()).toBeVisible();
        for (const price of await prices(page)) {
            expect(price).toBeGreaterThanOrEqual(threshold);
        }

        // An impossible window renders the no-results block in place (it reuses #search-form)
        await page.goto('/en_US/search?q=jeans&f[price][min]=999999');
        await expect(page.locator('.ui.message .header')).toHaveText('No results found');
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
