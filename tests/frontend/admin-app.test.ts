import { renderToString } from '@vue/server-renderer';
import { createSSRApp } from 'vue';
import { describe, expect, it } from 'vitest';
import AdminApp from '../../resources/js/components/AdminApp.vue';

async function renderAdmin(role: string): Promise<string> {
    return renderToString(createSSRApp(AdminApp, {
        user: {
            name: 'APF Press Owner',
            email: 'orders@apfpress.com',
            role,
        },
    }));
}

describe('AdminApp', () => {
    it('renders the complete owner workspace in the blue editorial shell', async () => {
        const html = await renderAdmin('owner');

        expect(html).toContain('class="admin-shell"');
        expect(html).toContain('/images/apf-press-logo.png');
        expect(html).toContain('Editorial overview');
        expect(html).toContain('Publication operations at a glance.');
        expect(html).toContain('Catalogue titles');
        expect(html).toContain('Paid revenue');

        for (const section of ['dashboard', 'catalog', 'import', 'orders', 'integrations', 'settings']) {
            expect(html).toContain(`data-section="${section}"`);
        }
    });

    it('limits editor navigation to editorial tools', async () => {
        const html = await renderAdmin('editor');

        expect(html).toContain('data-section="dashboard"');
        expect(html).toContain('data-section="catalog"');
        expect(html).toContain('data-section="import"');
        expect(html).not.toContain('data-section="orders"');
        expect(html).not.toContain('data-section="integrations"');
        expect(html).not.toContain('data-section="settings"');
    });

    it('gives fulfilment staff access to overview and orders only', async () => {
        const html = await renderAdmin('fulfillment');

        expect(html).toContain('data-section="dashboard"');
        expect(html).toContain('data-section="orders"');
        expect(html).not.toContain('data-section="catalog"');
        expect(html).not.toContain('data-section="import"');
        expect(html).not.toContain('data-section="integrations"');
        expect(html).not.toContain('data-section="settings"');
    });
});
