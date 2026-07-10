import { defineConfig, devices } from '@playwright/test';

const port = Number(process.env.E2E_PORT ?? 8080);
const baseURL = `http://127.0.0.1:${port}`;

export default defineConfig({
    testDir: './e2e',
    outputDir: './test-results',
    // A single shared app instance is served, so keep the tests serial
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    timeout: 60_000,
    // AJAX search round-trips go through PHP and Meilisearch
    expect: { timeout: 10_000 },
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL,
        trace: 'on-first-retry',
    },
    projects: [
        // A desktop viewport keeps the autocomplete widget out of its ≤680px "detached" mode
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
    webServer: {
        command: './e2e/serve.sh',
        url: `${baseURL}/en_US/`,
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
    },
});
