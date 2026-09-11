import { defineConfig } from '@playwright/test';
export default defineConfig({
    testDir: './tests/browser',
    testMatch: '*.spec.mjs',
    use: { browserName: 'chromium', viewport: { width: 1100, height: 850 } },
});
