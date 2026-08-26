import { expect, test, type Page, type TestInfo } from '@playwright/test';

function monitorBrowser(page: Page): { browserErrors: string[]; localFailures: string[] } {
    const browserErrors: string[] = [];
    const localFailures: string[] = [];

    page.on('console', (message) => {
        if (message.type() === 'error') browserErrors.push(message.text());
    });
    page.on('pageerror', (error) => browserErrors.push(error.message));
    page.on('response', (response) => {
        const url = new URL(response.url());
        if (['localhost', '127.0.0.1'].includes(url.hostname) && response.status() >= 400) {
            localFailures.push(`${response.status()} ${response.url()}`);
        }
    });
    page.on('requestfailed', (request) => {
        const url = new URL(request.url());
        if (['localhost', '127.0.0.1'].includes(url.hostname)) {
            localFailures.push(`${request.failure()?.errorText ?? 'Request failed'} ${request.url()}`);
        }
    });

    return { browserErrors, localFailures };
}

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(1);
}

async function expectImagesLoaded(page: Page, selector: string, revealLazyImages = false): Promise<void> {
    const images = page.locator(selector);
    if (await images.count() === 0) return;

    if (revealLazyImages) {
        for (const image of await images.all()) {
            await image.scrollIntoViewIfNeeded();
        }
    }

    await expect.poll(() => images.evaluateAll((elements) => elements.every((image) => {
        const element = image as HTMLImageElement;
        return element.complete && element.naturalWidth > 0;
    }))).toBe(true);
}

async function fontSize(page: Page, selector: string): Promise<number> {
    return page.locator(selector).first().evaluate((element) => Number.parseFloat(getComputedStyle(element).fontSize));
}

async function elementBox(page: Page, selector: string): Promise<{ x: number; y: number; width: number; height: number }> {
    const box = await page.locator(selector).first().boundingBox();
    expect(box, `${selector} should have a rendered box`).not.toBeNull();
    return box!;
}

test('homepage renders the editorial design at every supported viewport', async ({ page }, testInfo: TestInfo) => {
    const monitor = monitorBrowser(page);
    const response = await page.goto('/', { waitUntil: 'domcontentloaded' });

    expect(response?.status()).toBe(200);
    await expect(page.locator('.site-header .wordmark')).toBeVisible();
    const logo = page.locator('.site-header .official-logo');
    await expect(logo).toHaveAttribute('src', /\/images\/apf-press-logo\.png$/);
    await expect.poll(() => logo.evaluate((image) => ({
        width: (image as HTMLImageElement).naturalWidth,
        height: (image as HTMLImageElement).naturalHeight,
    }))).toEqual({ width: 357, height: 65 });
    await expect(page.getByRole('heading', { level: 1, name: /Ideas that question/i })).toBeVisible();
    await expect(page.locator('.hero-grid')).toHaveCSS('display', 'grid');
    await expect(page.locator('.hero')).not.toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
    await expect(page.locator('.hero-library-field')).toHaveCount(0);
    await expect(page.locator('.hero h1 em')).toHaveCSS('color', 'rgb(0, 80, 160)');
    await expect(page.locator('.hero .button').first()).toHaveCSS('background-color', 'rgb(0, 80, 160)');
    await expect(page.locator('.section-dark').first()).toHaveCSS('background-color', 'rgb(8, 47, 73)');
    await expect(page.locator('.hero-library-caption')).toHaveCSS('writing-mode', 'horizontal-tb');

    const bodyFont = await page.locator('body').evaluate((element) => getComputedStyle(element).fontFamily);
    expect(bodyFont.toLowerCase()).toContain('inter');
    await expect(page.locator('body')).toHaveClass(/public-site/);
    expect(await fontSize(page, 'body')).toBe(15);

    const heroMaximums = { desktop: 80, 'tablet-landscape': 50, tablet: 50, mobile: 48, 'mobile-small': 48 } as const;
    const sectionMaximums = { desktop: 52, 'tablet-landscape': 33, tablet: 33, mobile: 36, 'mobile-small': 36 } as const;
    const manifestoMaximums = { desktop: 51, 'tablet-landscape': 45, tablet: 34, mobile: 31, 'mobile-small': 31 } as const;
    const footerMaximums = { desktop: 64, 'tablet-landscape': 38, tablet: 36, mobile: 36, 'mobile-small': 36 } as const;
    const heroHeightMaximums = { desktop: 700, 'tablet-landscape': 700, tablet: 1050, mobile: 1050, 'mobile-small': 1150 } as const;
    const project = testInfo.project.name as keyof typeof heroMaximums;

    expect(await fontSize(page, '.hero h1')).toBeLessThanOrEqual(heroMaximums[project]);
    expect(await fontSize(page, '.section-heading h2')).toBeLessThanOrEqual(sectionMaximums[project]);
    expect(await fontSize(page, '.manifesto')).toBeLessThanOrEqual(manifestoMaximums[project]);
    expect(await fontSize(page, '.footer-statement > p:last-child')).toBeLessThanOrEqual(footerMaximums[project]);
    expect((await elementBox(page, '.hero')).height).toBeLessThanOrEqual(heroHeightMaximums[project]);

    const captionBox = await elementBox(page, '.hero-library-caption');
    const firstBookTop = await page.locator('.hero-book').evaluateAll((books) => Math.min(...books.map((book) => book.getBoundingClientRect().top)));
    expect(captionBox.y + captionBox.height).toBeLessThanOrEqual(firstBookTop + 1);
    await expectImagesLoaded(page, '.hero-book img');
    await expectNoHorizontalOverflow(page);

    if (['desktop', 'tablet-landscape'].includes(testInfo.project.name)) {
        await expect(page.locator('#main-navigation')).toBeVisible();
        await expect(page.locator('[data-mobile-nav] button')).toBeHidden();
    } else {
        const toggle = page.locator('[data-mobile-nav] button').first();
        const containerBox = await elementBox(page, '.site-header .container');
        const logoBox = await elementBox(page, '.site-header .official-logo');
        const actionBox = await elementBox(page, '.nav-actions');
        expect(Math.abs((actionBox.x + actionBox.width) - (containerBox.x + containerBox.width))).toBeLessThanOrEqual(1);
        expect(actionBox.x - (logoBox.x + logoBox.width)).toBeGreaterThan(24);
        await expect(toggle).toBeVisible();
        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(page.locator('#main-navigation')).toHaveClass(/is-open/);
        await expect(page.locator('#main-navigation')).toBeVisible();
        await expect(page.locator('.mobile-nav-backdrop')).toBeVisible();
        await expect(page.locator('#main-navigation a').first()).toBeFocused();
        const drawerBox = await elementBox(page, '#main-navigation');
        expect(drawerBox.width).toBeLessThan(page.viewportSize()!.width);
        expect(Math.abs(drawerBox.x + drawerBox.width - page.viewportSize()!.width)).toBeLessThanOrEqual(1);
        await page.keyboard.press('Escape');
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        await expect(toggle).toBeFocused();
    }

    if (testInfo.project.name === 'desktop') {
        const containerWidth = await page.locator('.site-header .container').evaluate((element) => element.getBoundingClientRect().width);
        expect(containerWidth).toBeGreaterThanOrEqual(1519);
        expect(containerWidth).toBeLessThanOrEqual(1521);
    }

    await page.screenshot({ path: testInfo.outputPath('home.png'), fullPage: true });
    expect(monitor.browserErrors, monitor.browserErrors.join('\n')).toEqual([]);
    expect(monitor.localFailures, monitor.localFailures.join('\n')).toEqual([]);
});

test('catalogue, book, content, and contact pages form a coherent public journey', async ({ page }, testInfo) => {
    const monitor = monitorBrowser(page);

    await page.goto('/books', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { level: 1, name: /Books for curious/i })).toBeVisible();
    await expect(page.locator('.book-card').first()).toBeVisible();
    await expectImagesLoaded(page, '.book-card img', true);
    const catalogueColumns = await page.locator('.catalogue-grid').evaluate((element) => getComputedStyle(element).gridTemplateColumns.split(' ').length);
    const expectedColumns = ({ desktop: 4, 'tablet-landscape': 3, tablet: 2, mobile: 1, 'mobile-small': 1 } as Record<string, number>)[testInfo.project.name];
    expect(catalogueColumns).toBe(expectedColumns);
    const pageTitleMaximums = { desktop: 71, 'tablet-landscape': 46, tablet: 46, mobile: 47, 'mobile-small': 47 } as const;
    expect(await fontSize(page, '.page-hero h1')).toBeLessThanOrEqual(pageTitleMaximums[testInfo.project.name as keyof typeof pageTitleMaximums]);
    await page.evaluate(() => window.scrollTo(0, 0));
    const pageHeroHeightMaximums = { desktop: 450, 'tablet-landscape': 460, tablet: 550, mobile: 510, 'mobile-small': 560 } as const;
    const headerBox = await elementBox(page, '.site-header');
    const breadcrumbBox = await elementBox(page, '.breadcrumbs');
    const pageHeroBox = await elementBox(page, '.page-hero');
    const catalogueBox = await elementBox(page, '.catalogue-section');
    expect(Math.abs(breadcrumbBox.y - (headerBox.y + headerBox.height))).toBeLessThanOrEqual(1);
    expect(pageHeroBox.height).toBeLessThanOrEqual(pageHeroHeightMaximums[testInfo.project.name as keyof typeof pageHeroHeightMaximums]);
    expect(Math.abs(catalogueBox.y - (pageHeroBox.y + pageHeroBox.height))).toBeLessThanOrEqual(1);
    await expectNoHorizontalOverflow(page);
    if (testInfo.project.name === 'desktop') {
        await page.screenshot({ path: testInfo.outputPath('catalogue.png'), fullPage: true });
    }

    await page.locator('.book-card-link').first().click();
    await expect(page.locator('.book-detail-section')).toBeVisible();
    await expect(page.locator('h1')).toBeVisible();
    const detailTitleMaximums = { desktop: 65, 'tablet-landscape': 46, tablet: 46, mobile: 46, 'mobile-small': 46 } as const;
    expect(await fontSize(page, '.detail-copy h1')).toBeLessThanOrEqual(detailTitleMaximums[testInfo.project.name as keyof typeof detailTitleMaximums]);
    if (await page.locator('.related-section').count()) {
        await page.locator('.related-section').scrollIntoViewIfNeeded();
    }
    await expectImagesLoaded(page, '.book-detail-section img, .related-section img');
    await expectNoHorizontalOverflow(page);
    if (testInfo.project.name === 'desktop') {
        await page.screenshot({ path: testInfo.outputPath('book.png'), fullPage: true });
    }

    for (const path of ['/about', '/publish-with-us', '/editorial-board', '/contact']) {
        const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
        expect(response?.status(), path).toBe(200);
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        await expectNoHorizontalOverflow(page);
    }

    expect(monitor.browserErrors, monitor.browserErrors.join('\n')).toEqual([]);
    expect(monitor.localFailures, monitor.localFailures.join('\n')).toEqual([]);
});

test('reader account entry pages remain usable at every viewport', async ({ page }) => {
    const monitor = monitorBrowser(page);

    for (const path of ['/login', '/register']) {
        const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
        expect(response?.status(), path).toBe(200);
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        await expect(page.locator('form')).toBeVisible();
        await expectNoHorizontalOverflow(page);
    }

    expect(monitor.browserErrors, monitor.browserErrors.join('\n')).toEqual([]);
    expect(monitor.localFailures, monitor.localFailures.join('\n')).toEqual([]);
});

test('cart drawer behaves as an accessible modal', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'Interaction is viewport independent.');
    const monitor = monitorBrowser(page);

    await page.goto('/', { waitUntil: 'domcontentloaded' });
    const trigger = page.getByRole('button', { name: 'Open shopping cart' });
    await trigger.click();

    const drawer = page.getByRole('dialog', { name: 'Your cart' });
    await expect(drawer).toBeVisible();
    await expect(page.locator('[data-cart-close]')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(drawer).toBeHidden();
    await expect(trigger).toBeFocused();

    expect(monitor.browserErrors, monitor.browserErrors.join('\n')).toEqual([]);
    expect(monitor.localFailures, monitor.localFailures.join('\n')).toEqual([]);
});
