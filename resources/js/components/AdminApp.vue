<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { money } from '../lib/http';

type Section = 'dashboard' | 'catalog' | 'import' | 'orders' | 'integrations' | 'settings';
type NavigationItem = { id: Section; index: string; label: string; hint: string; visible: boolean };

const props = defineProps<{ user: { name: string; email: string; role: string } }>();
const section = ref<Section>('dashboard');
const loading = ref(false);
const error = ref('');
const notice = ref('');
const dashboard = ref<Record<string, number>>({});
const catalog = ref<any[]>([]);
const orders = ref<any[]>([]);
const integrations = ref<any[]>([]);
const settings = ref<any>({ shipping_zones: [], tax_rules: [] });
const selected = ref<any | null>(null);
const importPreview = ref<any | null>(null);
const upload = ref<File | null>(null);
const coverUpload = ref<File | null>(null);
const digitalUpload = ref<File | null>(null);
const integrationForms = reactive<Record<string, any>>({
    stripe: { environment: 'sandbox', enabled: false, credentials: { secret: '', webhook_secret: '' } },
    paypal: { environment: 'sandbox', enabled: false, credentials: { client_id: '', client_secret: '', webhook_id: '' } },
});
const canEdit = computed(() => ['owner', 'editor'].includes(props.user.role));
const canFulfil = computed(() => ['owner', 'fulfillment'].includes(props.user.role));
const initials = computed(() => props.user.name.split(/\s+/).map((part) => part[0]).join('').slice(0, 2).toUpperCase() || 'AP');
const roleLabel = computed(() => props.user.role.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()));
const navigation = computed<NavigationItem[]>(() => {
    const items: NavigationItem[] = [
        { id: 'dashboard', index: '01', label: 'Overview', hint: 'Publishing pulse', visible: true },
        { id: 'catalog', index: '02', label: 'Catalogue', hint: 'Titles & editions', visible: canEdit.value },
        { id: 'import', index: '03', label: 'Woo import', hint: 'Migration desk', visible: canEdit.value },
        { id: 'orders', index: '04', label: 'Orders', hint: 'Sales & fulfilment', visible: canFulfil.value },
        { id: 'integrations', index: '05', label: 'Payments', hint: 'Provider access', visible: props.user.role === 'owner' },
        { id: 'settings', index: '06', label: 'Shipping & tax', hint: 'Commerce rules', visible: props.user.role === 'owner' },
    ];

    return items.filter((item) => item.visible);
});
const sectionContent: Record<Section, { eyebrow: string; title: string; description: string }> = {
    dashboard: { eyebrow: 'Publishing workspace', title: 'Editorial overview', description: 'A clear view of the catalogue, reader activity, and daily operations.' },
    catalog: { eyebrow: 'The APF library', title: 'Catalogue studio', description: 'Shape every title, edition, price, and piece of publication metadata.' },
    import: { eyebrow: 'Migration desk', title: 'WooCommerce import', description: 'Review source records before they become part of the APF catalogue.' },
    orders: { eyebrow: 'Reader services', title: 'Orders & fulfilment', description: 'Follow purchases from payment through delivery and digital access.' },
    integrations: { eyebrow: 'Commerce infrastructure', title: 'Payment providers', description: 'Manage encrypted checkout credentials and provider availability.' },
    settings: { eyebrow: 'Commerce policy', title: 'Shipping & tax', description: 'Review the rules that shape checkout across every supported region.' },
};
const currentSection = computed(() => sectionContent[section.value]);
const token = typeof document === 'undefined' ? '' : document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

function statusClass(value: string): string {
    return `status-${String(value).toLowerCase().replace(/[^a-z0-9]+/g, '-')}`;
}

async function api(url: string, options: RequestInit = {}): Promise<any> {
    loading.value = true; error.value = '';
    try {
        const response = await fetch(url, { credentials: 'same-origin', ...options, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token, ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }), ...options.headers } });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(payload.errors ?? {}).flat()[0] as string ?? payload.message ?? 'Request failed.');
        return payload;
    } finally { loading.value = false; }
}

async function navigate(next: Section): Promise<void> {
    section.value = next; selected.value = null; notice.value = '';
    try {
        if (next === 'dashboard') dashboard.value = await api('/admin/api/dashboard');
        if (next === 'catalog') catalog.value = (await api('/admin/api/catalog')).data;
        if (next === 'orders') orders.value = (await api('/admin/api/orders')).data;
        if (next === 'integrations') {
            integrations.value = await api('/admin/api/integrations');
            integrations.value.forEach((item) => Object.assign(integrationForms[item.provider], { environment: item.environment, enabled: item.enabled }));
        }
        if (next === 'settings') settings.value = await api('/admin/api/commerce-settings');
    } catch (caught) { error.value = caught instanceof Error ? caught.message : 'Unable to load this section.'; }
}

function newItem(): void {
    selected.value = { type: 'book', title: '', slug: '', summary: '', description: '', status: 'draft', featured: false, author: '', seo_title: '', seo_description: '', offering: { kind: 'print_book', name: 'Print edition', sku: '', price_amount: null, purchase_mode: 'inquiry', format: 'paperback', isbn_10: '', isbn_13: '', publication_date: '', page_count: null, on_hand: 0, track_inventory: false } };
}

async function editItem(id: number): Promise<void> {
    const item = await api(`/admin/api/catalog/${id}`);
    const offering = item.offerings?.[0] ?? {};
    selected.value = { ...item, author: item.contributors?.[0]?.name ?? '', offering: { ...offering, ...(offering.book_edition ?? {}), on_hand: offering.inventory?.on_hand ?? 0, track_inventory: offering.inventory?.track_inventory ?? false } };
}

async function saveItem(): Promise<void> {
    try {
        const isNew = !selected.value.id;
        const saved = await api(isNew ? '/admin/api/catalog' : `/admin/api/catalog/${selected.value.id}`, { method: isNew ? 'POST' : 'PUT', body: JSON.stringify(selected.value) });
        const offering = saved.offerings?.[0] ?? {};
        selected.value = { ...saved, author: saved.contributors?.[0]?.name ?? '', offering: { ...offering, ...(offering.book_edition ?? {}), on_hand: offering.inventory?.on_hand ?? 0, track_inventory: offering.inventory?.track_inventory ?? false } };
        notice.value = 'Catalogue record saved.';
        catalog.value = (await api('/admin/api/catalog')).data;
    } catch (caught) { error.value = caught instanceof Error ? caught.message : 'Unable to save.'; }
}

async function uploadCover(): Promise<void> {
    if (!selected.value?.id || !coverUpload.value) return;
    const form = new FormData(); form.append('cover', coverUpload.value); form.append('alt_text', `${selected.value.title} book cover`);
    try { await api(`/admin/api/catalog/${selected.value.id}/cover`, { method: 'POST', body: form }); notice.value = 'Cover image uploaded.'; }
    catch (caught) { error.value = caught instanceof Error ? caught.message : 'Cover upload failed.'; }
}

async function uploadDigital(): Promise<void> {
    const offeringId = selected.value?.offerings?.[0]?.id ?? selected.value?.offering?.id;
    if (!offeringId || !digitalUpload.value) return;
    const form = new FormData(); form.append('file', digitalUpload.value); form.append('access_duration_days', '365');
    try { await api(`/admin/api/offerings/${offeringId}/digital-asset`, { method: 'POST', body: form }); notice.value = 'Private digital edition uploaded and enabled for sale.'; }
    catch (caught) { error.value = caught instanceof Error ? caught.message : 'Digital upload failed.'; }
}

async function previewImport(): Promise<void> {
    if (!upload.value) return;
    const form = new FormData(); form.append('file', upload.value);
    try { importPreview.value = await api('/admin/api/imports/preview', { method: 'POST', body: form }); }
    catch (caught) { error.value = caught instanceof Error ? caught.message : 'Import preview failed.'; }
}

async function commitImport(): Promise<void> {
    try { const result = await api(`/admin/api/imports/${importPreview.value.batch_id}/commit`, { method: 'POST', body: JSON.stringify({ download_images: false }) }); notice.value = `Import complete: ${result.summary.created} created and ${result.summary.updated} updated.`; importPreview.value = null; }
    catch (caught) { error.value = caught instanceof Error ? caught.message : 'Import failed.'; }
}

async function saveIntegration(provider: string): Promise<void> {
    try { await api(`/admin/api/integrations/${provider}`, { method: 'PUT', body: JSON.stringify(integrationForms[provider]) }); notice.value = `${provider} settings saved. Secrets are encrypted in the database.`; }
    catch (caught) { error.value = caught instanceof Error ? caught.message : 'Unable to save integration.'; }
}

async function fulfil(order: any): Promise<void> {
    try { await api(`/admin/api/orders/${order.id}`, { method: 'PATCH', body: JSON.stringify({ status: 'fulfilled', fulfillment_status: 'fulfilled' }) }); await navigate('orders'); notice.value = `Order ${order.number} marked fulfilled.`; }
    catch (caught) { error.value = caught instanceof Error ? caught.message : 'Unable to update order.'; }
}

onMounted(() => navigate('dashboard'));
</script>

<template>
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <div class="admin-sidebar-head">
                <a class="admin-brand" href="/" aria-label="APF Press public website">
                    <span class="admin-logo-plaque"><img :src="'/images/apf-press-logo.png'" alt="APF Press"></span>
                    <span class="admin-brand-copy"><small>Administration</small><strong>Publishing workspace</strong></span>
                </a>
                <p>Independent scholarship, managed with editorial care.</p>
            </div>

            <nav class="admin-nav" aria-label="Administration sections">
                <p class="admin-nav-label">Workspace</p>
                <button
                    v-for="item in navigation"
                    :key="item.id"
                    :class="{ active: section === item.id }"
                    :aria-current="section === item.id ? 'page' : undefined"
                    :data-section="item.id"
                    @click="navigate(item.id)"
                >
                    <span class="admin-nav-index">{{ item.index }}</span>
                    <span class="admin-nav-copy"><strong>{{ item.label }}</strong><small>{{ item.hint }}</small></span>
                </button>
            </nav>

            <div class="admin-user">
                <span class="admin-avatar" aria-hidden="true">{{ initials }}</span>
                <span class="admin-user-copy"><strong>{{ user.name }}</strong><small>{{ roleLabel }} · {{ user.email }}</small></span>
                <form action="/logout" method="post">
                    <input type="hidden" name="_token" :value="token">
                    <button type="submit">Sign out <span aria-hidden="true">↗</span></button>
                </form>
            </div>
        </aside>

        <main class="admin-main" :aria-busy="loading">
            <header class="admin-top">
                <div class="admin-top-copy">
                    <p class="eyebrow">{{ currentSection.eyebrow }}</p>
                    <h1>{{ currentSection.title }}</h1>
                    <p>{{ currentSection.description }}</p>
                </div>
                <div class="admin-top-meta">
                    <span v-if="loading" class="admin-working"><i></i> Working</span>
                    <span v-else class="admin-ready"><i></i> Production workspace</span>
                    <a href="/">Visit public site <span aria-hidden="true">↗</span></a>
                </div>
            </header>

            <div class="admin-content">
                <div v-if="error" class="alert alert-error admin-alert" role="alert"><strong>Unable to complete that request.</strong><span>{{ error }}</span></div>
                <div v-if="notice" class="alert admin-alert" role="status"><strong>Update complete.</strong><span>{{ notice }}</span></div>

                <section v-if="section === 'dashboard'" class="admin-dashboard" data-admin-view="dashboard">
                    <div class="admin-dashboard-intro">
                        <div><p class="eyebrow">Current position</p><h2>Publication operations at a glance.</h2></div>
                        <p>Live catalogue and commerce signals for the APF Press team. Use the workspace navigation to move from overview to action.</p>
                    </div>
                    <div class="admin-cards">
                        <article class="admin-stat-card admin-stat-primary"><div class="admin-stat-head"><span>Catalogue titles</span><b>01</b></div><strong>{{ dashboard.catalog_items ?? 0 }}</strong><small>{{ dashboard.published_items ?? 0 }} published and available to readers</small></article>
                        <article class="admin-stat-card"><div class="admin-stat-head"><span>Metadata issues</span><b>02</b></div><strong>{{ dashboard.metadata_issues ?? 0 }}</strong><small>Records awaiting editorial review</small></article>
                        <article class="admin-stat-card"><div class="admin-stat-head"><span>Open orders</span><b>03</b></div><strong>{{ dashboard.open_orders ?? 0 }}</strong><small>Paid or currently processing</small></article>
                        <article class="admin-stat-card"><div class="admin-stat-head"><span>Paid revenue</span><b>04</b></div><strong>{{ money(dashboard.revenue_cad ?? 0) }}</strong><small>All-time recorded revenue in CAD</small></article>
                        <article class="admin-stat-card"><div class="admin-stat-head"><span>New inquiries</span><b>05</b></div><strong>{{ dashboard.new_inquiries ?? 0 }}</strong><small>Reader messages awaiting response</small></article>
                        <article class="admin-stat-card"><div class="admin-stat-head"><span>New submissions</span><b>06</b></div><strong>{{ dashboard.new_submissions ?? 0 }}</strong><small>Manuscripts awaiting initial review</small></article>
                    </div>
                </section>

                <section v-if="section === 'catalog'" data-admin-view="catalog">
                    <div v-if="!selected" class="admin-section-bar">
                        <div><span>{{ catalog.length }} catalogue records</span><p>Edit books, editions, prices, ISBNs, dates, and inventory.</p></div>
                        <button class="button" @click="newItem"><span aria-hidden="true">＋</span> New catalogue item</button>
                    </div>

                    <div v-if="!selected" class="admin-panel">
                        <div class="admin-panel-heading"><div><p class="eyebrow">Editorial library</p><h2>Published and working titles</h2></div><span>Metadata & commerce</span></div>
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead><tr><th>Title</th><th>Status</th><th>SKU / ISBN</th><th>Review</th><th><span class="sr-only">Actions</span></th></tr></thead>
                                <tbody>
                                    <tr v-for="item in catalog" :key="item.id">
                                        <td><strong>{{ item.title }}</strong><small>{{ item.contributors?.[0]?.name || 'No author credited' }}</small></td>
                                        <td><span class="status-pill" :class="statusClass(item.status)">{{ item.status }}</span></td>
                                        <td><span class="admin-table-code">{{ item.offerings?.[0]?.sku || 'No SKU' }}</span><small>{{ item.offerings?.[0]?.book_edition?.isbn_13 || 'No ISBN' }}</small></td>
                                        <td><span v-if="item.metadata_flags?.length" class="issue-count">{{ item.metadata_flags.length }}</span><span v-else class="admin-complete">Complete</span></td>
                                        <td><button class="text-link" @click="editItem(item.id)">Edit title <span aria-hidden="true">→</span></button></td>
                                    </tr>
                                    <tr v-if="!catalog.length"><td colspan="5"><div class="admin-empty"><span>00</span><div><strong>No catalogue records yet.</strong><small>Create a title or import an existing WooCommerce catalogue.</small></div></div></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-else class="admin-editor">
                        <div class="admin-editor-head"><div><p class="eyebrow">{{ selected.id ? 'Catalogue record' : 'New publication' }}</p><h2>{{ selected.id ? selected.title : 'Build a new title' }}</h2><p>{{ selected.id ? 'Edit the editorial and commercial record below.' : 'Begin with the core publication details. You can add assets after saving.' }}</p></div><button class="button button-secondary" @click="selected = null">← Back to catalogue</button></div>

                        <div class="admin-form-section">
                            <div class="admin-form-section-head"><span>01</span><div><h3>Editorial record</h3><p>The title, authorship, copy, and publication state readers will see.</p></div></div>
                            <div class="form-grid">
                                <div class="field field-full"><label>Title</label><input v-model="selected.title" class="input"></div>
                                <div class="field"><label>Author</label><input v-model="selected.author" class="input"></div>
                                <div class="field"><label>URL slug</label><input v-model="selected.slug" class="input"></div>
                                <div class="field field-full"><label>Short summary</label><textarea v-model="selected.summary" class="textarea textarea-small"></textarea></div>
                                <div class="field field-full"><label>Full description</label><textarea v-model="selected.description" class="textarea"></textarea></div>
                                <div class="field"><label>Publication status</label><select v-model="selected.status" class="select"><option>draft</option><option>published</option><option>archived</option></select></div>
                                <label class="admin-check"><input v-model="selected.featured" type="checkbox"><span><strong>Featured title</strong><small>Give this book priority in public editorial placements.</small></span></label>
                            </div>
                        </div>

                        <div class="admin-form-section">
                            <div class="admin-form-section-head"><span>02</span><div><h3>Edition & commerce</h3><p>Format, identifiers, pricing, availability, and stock control.</p></div></div>
                            <div class="form-grid">
                                <div class="field"><label>Edition type</label><select v-model="selected.offering.kind" class="select"><option value="print_book">Print book</option><option value="ebook">E-book</option></select></div>
                                <div class="field"><label>SKU</label><input v-model="selected.offering.sku" class="input"></div>
                                <div class="field"><label>Price <span>cents CAD</span></label><input v-model.number="selected.offering.price_amount" class="input" type="number" min="0"></div>
                                <div class="field"><label>ISBN-13</label><input v-model="selected.offering.isbn_13" class="input" maxlength="13"></div>
                                <div class="field"><label>Publication date</label><input v-model="selected.offering.publication_date" class="input" type="date"></div>
                                <div class="field"><label>Page count</label><input v-model.number="selected.offering.page_count" class="input" type="number"></div>
                                <div class="field"><label>Stock count</label><input v-model.number="selected.offering.on_hand" class="input" type="number"></div>
                                <div class="field"><label>Purchase mode</label><select v-model="selected.offering.purchase_mode" class="select"><option value="online">Online</option><option value="inquiry">Inquiry</option><option value="unavailable">Unavailable</option></select></div>
                                <label class="admin-check field-full"><input v-model="selected.offering.track_inventory" type="checkbox"><span><strong>Track exact inventory</strong><small>Reduce available stock as physical orders are fulfilled.</small></span></label>
                            </div>
                        </div>

                        <div class="admin-form-section">
                            <div class="admin-form-section-head"><span>03</span><div><h3>Search & discovery</h3><p>Optional metadata for search engines and editorial sharing.</p></div></div>
                            <div class="form-grid"><div class="field field-full"><label>SEO title</label><input v-model="selected.seo_title" class="input"></div><div class="field field-full"><label>SEO description</label><textarea v-model="selected.seo_description" class="textarea textarea-small"></textarea></div></div>
                        </div>

                        <div v-if="selected.id" class="admin-form-section">
                            <div class="admin-form-section-head"><span>04</span><div><h3>Publication assets</h3><p>Replace the public cover or attach a protected digital edition.</p></div></div>
                            <div class="admin-two-col admin-upload-grid">
                                <div class="admin-upload-card"><div><p class="eyebrow">Public media</p><h4>Cover image</h4><p>JPEG, PNG, or WebP. Use the final production cover whenever possible.</p></div><input class="input" type="file" accept="image/jpeg,image/png,image/webp" @change="coverUpload = ($event.target as HTMLInputElement).files?.[0] ?? null"><button class="button button-small" :disabled="!coverUpload" @click="uploadCover">Upload cover</button></div>
                                <div v-if="selected.offering.kind === 'ebook'" class="admin-upload-card"><div><p class="eyebrow">Private media</p><h4>Digital edition</h4><p>PDF or EPUB. Existing reader entitlements remain tied to their asset version.</p></div><input class="input" type="file" accept=".pdf,.epub" @change="digitalUpload = ($event.target as HTMLInputElement).files?.[0] ?? null"><button class="button button-small" :disabled="!digitalUpload" @click="uploadDigital">Upload private edition</button></div>
                            </div>
                        </div>

                        <div class="admin-save-bar"><div><strong>Ready to publish your changes?</strong><small>The catalogue API and public pages update from this record.</small></div><button class="button" @click="saveItem">Save title</button></div>
                    </div>
                </section>

                <section v-if="section === 'import'" class="admin-panel admin-import" data-admin-view="import">
                    <div class="admin-panel-heading"><div><p class="eyebrow">Source catalogue</p><h2>Preview before you publish.</h2><p>Upload a Woo Store API JSON file or WooCommerce product CSV. No records change until you approve the preview.</p></div><span>Two-step import</span></div>
                    <div class="admin-import-stage">
                        <div class="admin-import-number">01</div>
                        <div><h3>Select an export</h3><p>Choose the most recent product export from the previous store.</p><input class="input" type="file" accept=".json,.csv" @change="upload = ($event.target as HTMLInputElement).files?.[0] ?? null"></div>
                        <button class="button" :disabled="!upload" @click="previewImport">Generate preview</button>
                    </div>
                    <div v-if="importPreview" class="admin-import-preview">
                        <div class="admin-section-bar"><div><span>{{ importPreview.summary.rows }} records ready</span><p>Review warnings and generated identifiers before importing.</p></div><button class="button" @click="commitImport">Import these records</button></div>
                        <div class="chips"><span v-for="(count, name) in importPreview.summary.warnings" :key="name" class="chip">{{ String(name).replaceAll('_', ' ') }}: {{ count }}</span></div>
                        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Title</th><th>SKU</th><th>Warnings</th></tr></thead><tbody><tr v-for="row in importPreview.rows" :key="row.source_id"><td><strong>{{ row.title }}</strong></td><td><span class="admin-table-code">{{ row.sku || `APF-WOO-${row.source_id}` }}</span></td><td>{{ row.warnings.join(', ') || 'None' }}</td></tr></tbody></table></div>
                    </div>
                </section>

                <section v-if="section === 'orders'" class="admin-panel" data-admin-view="orders">
                    <div class="admin-panel-heading"><div><p class="eyebrow">Reader purchases</p><h2>Order register</h2><p>Review payment and fulfilment status across physical and digital orders.</p></div><span>{{ orders.length }} records</span></div>
                    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody><tr v-for="order in orders" :key="order.id"><td><strong>{{ order.number }}</strong><small>{{ new Date(order.created_at).toLocaleDateString('en-CA') }}</small></td><td>{{ order.email }}</td><td><strong>{{ money(order.total_amount, order.currency) }}</strong></td><td><span class="status-pill" :class="statusClass(order.status)">{{ order.status }}</span></td><td><button v-if="order.payment_status === 'paid' && order.fulfillment_status !== 'fulfilled'" class="button button-small" @click="fulfil(order)">Mark fulfilled</button><span v-else class="admin-complete">No action needed</span></td></tr><tr v-if="!orders.length"><td colspan="5"><div class="admin-empty"><span>00</span><div><strong>No orders to show.</strong><small>New reader purchases will appear here.</small></div></div></td></tr></tbody></table></div>
                </section>

                <section v-if="section === 'integrations'" class="admin-two-col admin-integrations" data-admin-view="integrations">
                    <article v-for="provider in ['stripe', 'paypal']" :key="provider" class="admin-editor admin-provider-card">
                        <div class="admin-provider-head"><span>{{ provider === 'stripe' ? 'ST' : 'PP' }}</span><div><p class="eyebrow">Payment provider</p><h2>{{ provider === 'stripe' ? 'Stripe' : 'PayPal' }}</h2></div><b :class="{ enabled: integrationForms[provider].enabled }">{{ integrationForms[provider].enabled ? 'Enabled' : 'Disabled' }}</b></div>
                        <p class="admin-provider-intro">Credentials are encrypted before storage. Leave a saved secret blank to preserve its current value.</p>
                        <div class="field"><label>Environment</label><select v-model="integrationForms[provider].environment" class="select"><option value="sandbox">Sandbox</option><option value="live">Live</option></select></div>
                        <label class="admin-check"><input v-model="integrationForms[provider].enabled" type="checkbox"><span><strong>Enable checkout</strong><small>Make this provider available to readers.</small></span></label>
                        <template v-if="provider === 'stripe'"><div class="field"><label>Secret key</label><input v-model="integrationForms.stripe.credentials.secret" class="input" type="password" placeholder="Leave blank to preserve saved value"></div><div class="field"><label>Webhook signing secret</label><input v-model="integrationForms.stripe.credentials.webhook_secret" class="input" type="password"></div></template>
                        <template v-else><div class="field"><label>Client ID</label><input v-model="integrationForms.paypal.credentials.client_id" class="input" type="password"></div><div class="field"><label>Client secret</label><input v-model="integrationForms.paypal.credentials.client_secret" class="input" type="password"></div><div class="field"><label>Webhook ID</label><input v-model="integrationForms.paypal.credentials.webhook_id" class="input" type="password"></div></template>
                        <button class="button" @click="saveIntegration(provider)">Save encrypted settings</button>
                    </article>
                </section>

                <section v-if="section === 'settings'" class="admin-two-col admin-settings" data-admin-view="settings">
                    <article class="admin-editor"><div class="admin-panel-heading"><div><p class="eyebrow">Delivery policy</p><h2>Shipping zones</h2></div><span>{{ settings.shipping_zones.length }} zones</span></div><div v-for="zone in settings.shipping_zones" :key="zone.id" class="setting-row"><strong>{{ zone.name }}</strong><span>{{ zone.country }}</span><small v-for="rule in zone.rules" :key="rule.id">{{ rule.name }} · {{ money(rule.rate_amount) }} · Free over {{ money(rule.free_above_amount) }}</small></div><p class="help">Launch defaults ship to Canada and the United States. Rates can be changed through the settings API.</p></article>
                    <article class="admin-editor"><div class="admin-panel-heading"><div><p class="eyebrow">Collection policy</p><h2>Tax rules</h2></div><span>{{ settings.tax_rules.length }} rules</span></div><div v-if="!settings.tax_rules.length" class="admin-policy-note"><span>!</span><p>No tax nexus rules are enabled. Have APF Press’s tax adviser approve jurisdictions and rates before collecting tax.</p></div><div v-for="rule in settings.tax_rules" :key="rule.id" class="setting-row"><strong>{{ rule.label }} — {{ rule.region || rule.country }}</strong><span>{{ (rule.rate_basis_points / 100).toFixed(2) }}%</span><small>{{ rule.nexus_enabled ? 'Nexus enabled' : 'Nexus disabled' }}</small></div></article>
                </section>
            </div>
        </main>
    </div>
</template>
