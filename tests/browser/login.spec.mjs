import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';

async function open(page, fixture) {
    const errors = [];
    const external = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', async route => {
        const url = new URL(route.request().url());
        if (url.hostname !== 'localhost') {
            external.push(url.href);
            return route.abort();
        }
        if (url.pathname.startsWith('/vendor/totp-login/')) {
            const file = url.pathname.split('/').pop();
            return route.fulfill({ body: await readFile(`resources/dist/${file}`), contentType: file.endsWith('.js') ? 'application/javascript' : 'text/css' });
        }
        return route.fulfill({ body: await readFile(`build/browser/${fixture}.html`), contentType: 'text/html' });
    });
    await page.goto('http://localhost/login');
    return { errors, external };
}

test('identifier form loads local CSS and preserves its POST fields', async ({ page }) => {
    const { errors, external } = await open(page, 'identifier');
    await expect(page.getByRole('heading')).toHaveText('Enter your login');
    await page.locator('input[name="email"]').fill('test@example.com');
    await expect(page.locator('form')).toHaveAttribute('method', 'POST');
    await expect(page.locator('input[name="_token"]')).toHaveCount(1);
    expect(external).toEqual([]);
    expect(errors).toEqual([]);
    await page.screenshot({ path: 'build/browser/identifier.png', fullPage: true });
});

for (const length of [6, 8]) {
    test(`code form supports ${length} digits, focus, paste, remember and resend`, async ({ page }) => {
        const { errors, external } = await open(page, `code-${length}`);
        const fields = page.locator('input[name="code[]"]');
        await expect(fields).toHaveCount(length);
        await fields.nth(0).fill('1');
        await expect(fields.nth(1)).toBeFocused();
        await fields.nth(1).press('Backspace');
        await expect(fields.nth(0)).toBeFocused();
        const toggle = page.getByRole('switch');
        await expect(toggle).toHaveAttribute('aria-checked', 'true');
        await expect(toggle).toHaveCSS('background-color', 'rgb(34, 197, 94)');
        await toggle.click();
        await expect(page.locator('input[name="remember"]')).toHaveValue('false');
        await page.locator('#remember').click();
        await expect(toggle).toBeFocused();
        await expect(page.locator('input[name="remember"]')).toHaveValue('true');
        await expect(page.locator('input[value="Resend the code"]')).toHaveCount(1);
        await expect(page.locator('input[name="email"]')).toHaveValue('test@example.com');
        await page.locator('form').first().evaluate(form => form.addEventListener('submit', event => {
            event.preventDefault();
            window.submitted = [...new FormData(form).getAll('code[]')].join('');
        }));
        const digits = '01234567'.slice(0, length);
        await fields.nth(0).evaluate((field, text) => {
            const data = new DataTransfer(); data.setData('text', text);
            field.dispatchEvent(new ClipboardEvent('paste', { clipboardData: data, bubbles: true }));
        }, digits);
        await expect.poll(() => page.evaluate(() => window.submitted)).toBe(digits);
        await fields.nth(length - 1).focus();
        await fields.nth(length - 1).press('Backspace');
        expect(errors).toEqual([]);
        expect(external).toEqual([]);
        await page.screenshot({ path: `build/browser/code-${length}.png`, fullPage: true });
    });
}


test('eight digit form fits a mobile screen', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    const { errors, external } = await open(page, 'code-8');
    await expect(page.locator('input[name="code[]"]')).toHaveCount(8);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBe(375);
    await expect(page.getByRole('switch')).toHaveCSS('background-color', 'rgb(34, 197, 94)');
    expect(errors).toEqual([]);
    expect(external).toEqual([]);
    await page.screenshot({ path: 'build/browser/mobile.png', fullPage: true });
});
