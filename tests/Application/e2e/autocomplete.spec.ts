import { test, expect } from '@playwright/test';

// The autocomplete widget (#autocomplete) is rendered in the shop header on every page and
// queries Meilisearch DIRECTLY from the browser using the searchKey embedded in the page.

test.describe('autocomplete widget', () => {
    test('embeds a usable configuration', async ({ page }) => {
        await page.goto('/en_US/');

        const raw = await page.locator('#ssm-autocomplete-configuration').textContent();
        const config = JSON.parse(raw ?? '{}');

        // Fails fast (instead of the suggestions silently never loading) when the server was
        // started without a resolved MEILISEARCH_SEARCH_KEY, i.e. not through e2e/serve.sh
        expect(config.searchKey, 'MEILISEARCH_SEARCH_KEY is empty — start the server via e2e/serve.sh').not.toBe('');
        expect(config.host).toMatch(/^https?:\/\//);
        await expect(page.locator('#autocomplete .aa-Input')).toBeVisible();
    });

    test('suggests products while typing', async ({ page }) => {
        await page.goto('/en_US/');

        const input = page.locator('#autocomplete .aa-Input');
        await input.click();
        await input.pressSequentially('jeans', { delay: 50 });

        // The panel is portalled onto the body, so it is not scoped to #autocomplete
        await expect(page.locator('.aa-Panel')).toBeVisible();
        await expect(page.locator('.aa-ItemContentTitle').filter({ hasText: /jeans/i }).first()).toBeVisible();
    });

    test('navigates to the product on selection', async ({ page }) => {
        await page.goto('/en_US/');

        const input = page.locator('#autocomplete .aa-Input');
        await input.click();
        await input.pressSequentially('jeans', { delay: 50 });
        await expect(page.locator('.aa-ItemWrapper').first()).toBeVisible();

        // onSelect sets location.href = item.url
        await page.locator('.aa-ItemWrapper').first().click();
        await expect(page).toHaveURL(/\/en_US\/products\//);
        await expect(page.locator('h1')).toBeVisible();
    });

    test('encodes special characters when submitting the search', async ({ page }) => {
        await page.goto('/en_US/');

        const input = page.locator('#autocomplete .aa-Input');
        await input.click();
        await input.fill('jeans & co #1');
        await input.press('Enter');

        // onSubmit builds the URL with URLSearchParams: space -> +, & -> %26, # -> %23
        await expect(page).toHaveURL(/\/en_US\/search\?q=jeans\+%26\+co\+%231/);
    });

    test('lets window.ssmAutocomplete override the configuration', async ({ page }) => {
        // Injected before any page script runs, so it exists when autocomplete.js reads it.
        await page.addInitScript(() => {
            (window as unknown as { ssmAutocomplete: Record<string, unknown> }).ssmAutocomplete = {
                placeholder: 'OVERRIDDEN',
            };
        });
        await page.goto('/en_US/');

        // User options must win over the plugin's config (previously the config clobbered them).
        await expect(page.locator('#autocomplete .aa-Input')).toHaveAttribute('placeholder', 'OVERRIDDEN');
    });
});
