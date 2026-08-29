import { expect, test, type Page } from '@playwright/test';

function catalogueItem() {
    return {
        id: 1, type: 'book', title: 'A Playwright Catalogue Title', subtitle: 'An end-to-end record', slug: 'playwright-catalogue-title',
        summary: 'A complete catalogue summary.', description: 'A complete catalogue description.', status: 'published', featured: false,
        seo_title: '', seo_description: '', metadata_flags: [], warnings: {}, updated_at: '2026-08-28T00:00:00.000Z',
        book_details: { publisher: 'APF Press', imprint: '', original_language: 'en' },
        contributors: [{ id: 1, name: 'Example Author', role: 'author', position: 0 }],
        categories: [{ id: 1, name: 'Public Policy', slug: 'public-policy' }], cover: null,
        offerings: [{
            id: 1, active: true, position: 0, kind: 'print_book', name: 'Print edition', sku: 'PW-001', price_amount: 2499,
            currency: 'CAD', purchase_mode: 'inquiry', access_duration_days: null, available: false,
            edition: { format: 'paperback', edition_label: '', isbn_10: '', isbn_13: '9781234567897', publication_date: '2026-08-28T00:00:00.000000Z', page_count: 220, language: 'en', weight_grams: null, width_mm: null, height_mm: null, depth_mm: null },
            inventory: { on_hand: 8, reserved: 0, low_stock_threshold: 2, track_inventory: false, allow_backorder: false },
            current_digital_asset: null,
        }],
    };
}

async function installApiFixtures(page: Page): Promise<void> {
    const item = catalogueItem();
    await page.route('**/admin/api/dashboard', (route) => route.fulfill({ json: { catalog_items: 1, published_items: 1 } }));
    await page.route('**/admin/api/catalog/options', (route) => route.fulfill({ json: {
        contributors: [{ id: 1, name: 'Example Author' }], categories: [{ id: 1, name: 'Public Policy', slug: 'public-policy' }],
        contributor_roles: ['author', 'editor', 'translator', 'illustrator', 'foreword', 'contributor'],
    } }));
    await page.route('**/admin/api/catalog**', async (route) => {
        const request = route.request();
        const url = new URL(request.url());
        if (url.pathname.endsWith('/catalog/options')) return route.fulfill({ json: {
            contributors: [{ id: 1, name: 'Example Author' }], categories: [{ id: 1, name: 'Public Policy', slug: 'public-policy' }],
            contributor_roles: ['author', 'editor', 'translator', 'illustrator', 'foreword', 'contributor'],
        } });
        if (request.method() === 'GET' && /\/catalog\/1$/.test(url.pathname)) return route.fulfill({ json: { data: item } });
        if (request.method() === 'PUT') {
            const payload = request.postDataJSON();
            const offering = payload.offerings[0];
            if (offering.purchase_mode === 'online' && (!offering.sku || offering.price_amount === null)) {
                return route.fulfill({ status: 422, json: {
                    message: 'The given data was invalid.',
                    errors: {
                        'offerings.0.sku': ['A SKU is required for online sale.'],
                        'offerings.0.price_amount': ['A price is required for online sale.'],
                    },
                } });
            }
            return route.fulfill({ json: { data: { ...item, ...payload, id: 1, warnings: {}, metadata_flags: [] } } });
        }
        return route.fulfill({ json: {
            data: [item],
            links: { first: null, last: null, prev: null, next: null },
            meta: { current_page: 1, last_page: 1, per_page: 25, total: 1, from: 1, to: 1 },
        } });
    });
}

test('catalogue editor keeps save and validation feedback in reach', async ({ page }) => {
    const dateWarnings: string[] = [];
    page.on('console', (message) => {
        if (message.type() === 'warning' && /yyyy-MM-dd|required format|does not conform/i.test(message.text())) dateWarnings.push(message.text());
    });
    await installApiFixtures(page);
    await page.goto('/login');
    await page.getByLabel('Email address').fill('owner@apfpress.test');
    await page.getByLabel('Password').fill('ChangeMe!12345');
    await page.getByRole('button', { name: /Sign in/i }).click();
    await expect(page).toHaveURL(/\/admin/);

    await page.locator('[data-section="catalog"]').click();
    await expect(page.getByText('1 catalogue records')).toBeVisible();
    const editTitle = page.getByRole('button', { name: /Edit title/i });
    await expect(editTitle).toBeInViewport();
    await editTitle.click();
    await expect(page.locator('.admin-save-bar-sticky')).toBeVisible();

    await page.getByRole('button', { name: /Editions/ }).click();
    const date = page.getByLabel('Publication date');
    await expect(date).toHaveValue('2026-08-28');
    await page.getByLabel('Purchase mode').selectOption('online');
    await page.getByLabel(/SKU/).fill('');
    await page.getByLabel(/Price/).fill('');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText('A SKU is required for online sale.')).toBeVisible();
    await expect(page.getByText(/2 fields need attention/)).toBeInViewport();
    await expect(page.locator('.admin-save-bar-sticky')).toBeInViewport();

    await page.getByLabel(/SKU/).fill('PW-ONLINE-001');
    await page.getByLabel(/Price/).fill('24.99');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText('Catalogue record saved')).toBeVisible();
    await expect(page.getByText('All changes saved')).toBeVisible();

    expect(dateWarnings).toEqual([]);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(1);
});
