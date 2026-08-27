import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/browser',
    outputDir: './test-results',
    fullyParallel: true,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    reporter: process.env.CI ? [['html', { open: 'never' }], ['line']] : 'line',
    use: {
        baseURL: process.env.APFPRESS_BASE_URL ?? 'http://localhost:8080',
        colorScheme: 'light',
        locale: 'en-CA',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'desktop', use: { browserName: 'chromium', viewport: { width: 1920, height: 1080 } } },
        { name: 'tablet-landscape', use: { browserName: 'chromium', viewport: { width: 1024, height: 768 }, hasTouch: true } },
        { name: 'tablet', use: { browserName: 'chromium', viewport: { width: 768, height: 1024 }, hasTouch: true } },
        { name: 'mobile', use: { browserName: 'chromium', viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } },
        { name: 'mobile-small', use: { browserName: 'chromium', viewport: { width: 320, height: 568 }, isMobile: true, hasTouch: true } },
    ],
});
