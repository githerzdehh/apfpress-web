<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { money } from '../lib/http';

type Section = 'dashboard' | 'catalog' | 'import' | 'orders' | 'integrations' | 'settings';
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
const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

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
            <a class="brand admin-brand" href="/"><span class="brand-mark">APF</span><span><strong>APF Press</strong><small>Administration</small></span></a>
            <nav class="admin-nav">
                <button :class="{ active: section === 'dashboard' }" @click="navigate('dashboard')">Overview</button>
                <button v-if="canEdit" :class="{ active: section === 'catalog' }" @click="navigate('catalog')">Catalogue</button>
                <button v-if="canEdit" :class="{ active: section === 'import' }" @click="navigate('import')">Woo import</button>
                <button v-if="canFulfil" :class="{ active: section === 'orders' }" @click="navigate('orders')">Orders</button>
                <button v-if="user.role === 'owner'" :class="{ active: section === 'integrations' }" @click="navigate('integrations')">Payments</button>
                <button v-if="user.role === 'owner'" :class="{ active: section === 'settings' }" @click="navigate('settings')">Shipping & tax</button>
            </nav>
            <div class="admin-user"><strong>{{ user.name }}</strong><small>{{ user.role }}</small><form action="/logout" method="post"><input type="hidden" name="_token" :value="token"><button type="submit">Sign out</button></form></div>
        </aside>
        <main class="admin-main">
            <header class="admin-top"><div><p class="eyebrow">APF Press workspace</p><h1>{{ section === 'import' ? 'WooCommerce import' : section.charAt(0).toUpperCase() + section.slice(1) }}</h1></div><span v-if="loading" class="muted">Working…</span></header>
            <div v-if="error" class="alert alert-error" role="alert">{{ error }}</div><div v-if="notice" class="alert" role="status">{{ notice }}</div>

            <section v-if="section === 'dashboard'" class="admin-cards"><article><span>Catalogue titles</span><strong>{{ dashboard.catalog_items ?? 0 }}</strong><small>{{ dashboard.published_items ?? 0 }} published</small></article><article><span>Metadata issues</span><strong>{{ dashboard.metadata_issues ?? 0 }}</strong><small>Need editorial review</small></article><article><span>Open orders</span><strong>{{ dashboard.open_orders ?? 0 }}</strong><small>Paid or processing</small></article><article><span>Paid revenue</span><strong>{{ money(dashboard.revenue_cad ?? 0) }}</strong><small>All-time CAD</small></article><article><span>New inquiries</span><strong>{{ dashboard.new_inquiries ?? 0 }}</strong><small>Awaiting response</small></article><article><span>New submissions</span><strong>{{ dashboard.new_submissions ?? 0 }}</strong><small>Awaiting review</small></article></section>

            <section v-if="section === 'catalog'">
                <div class="admin-actions"><p class="muted">Edit books, prices, ISBNs, dates, formats, and inventory.</p><button class="button" @click="newItem">New catalogue item</button></div>
                <div v-if="!selected" class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Title</th><th>Status</th><th>SKU / ISBN</th><th>Issues</th><th></th></tr></thead><tbody><tr v-for="item in catalog" :key="item.id"><td><strong>{{ item.title }}</strong><small>{{ item.contributors?.[0]?.name || 'No author' }}</small></td><td><span class="status-pill">{{ item.status }}</span></td><td>{{ item.offerings?.[0]?.sku || '—' }}<small>{{ item.offerings?.[0]?.book_edition?.isbn_13 || 'No ISBN' }}</small></td><td><span v-if="item.metadata_flags?.length" class="issue-count">{{ item.metadata_flags.length }}</span><span v-else>Complete</span></td><td><button class="text-link" @click="editItem(item.id)">Edit</button></td></tr></tbody></table></div>
                <div v-else class="admin-editor"><div class="admin-actions"><h2>{{ selected.id ? 'Edit title' : 'New title' }}</h2><button class="button button-secondary" @click="selected = null">Back to list</button></div><div class="form-grid"><div class="field field-full"><label>Title</label><input v-model="selected.title" class="input"></div><div class="field"><label>Author</label><input v-model="selected.author" class="input"></div><div class="field"><label>Slug</label><input v-model="selected.slug" class="input"></div><div class="field field-full"><label>Summary</label><textarea v-model="selected.summary" class="textarea"></textarea></div><div class="field field-full"><label>Description</label><textarea v-model="selected.description" class="textarea"></textarea></div><div class="field"><label>Status</label><select v-model="selected.status" class="select"><option>draft</option><option>published</option><option>archived</option></select></div><div class="field"><label>Edition type</label><select v-model="selected.offering.kind" class="select"><option value="print_book">Print book</option><option value="ebook">E-book</option></select></div><div class="field"><label>SKU</label><input v-model="selected.offering.sku" class="input"></div><div class="field"><label>Price (cents CAD)</label><input v-model.number="selected.offering.price_amount" class="input" type="number" min="0"></div><div class="field"><label>ISBN-13</label><input v-model="selected.offering.isbn_13" class="input" maxlength="13"></div><div class="field"><label>Publication date</label><input v-model="selected.offering.publication_date" class="input" type="date"></div><div class="field"><label>Page count</label><input v-model.number="selected.offering.page_count" class="input" type="number"></div><div class="field"><label>Stock count</label><input v-model.number="selected.offering.on_hand" class="input" type="number"></div><div class="field"><label>Purchase mode</label><select v-model="selected.offering.purchase_mode" class="select"><option value="online">Online</option><option value="inquiry">Inquiry</option><option value="unavailable">Unavailable</option></select></div><label><input v-model="selected.offering.track_inventory" type="checkbox"> Track exact inventory</label><label><input v-model="selected.featured" type="checkbox"> Feature this title</label><div class="field field-full"><label>SEO title</label><input v-model="selected.seo_title" class="input"></div><div class="field field-full"><label>SEO description</label><textarea v-model="selected.seo_description" class="textarea"></textarea></div></div><button class="button" style="margin-top:1.5rem" @click="saveItem">Save title</button><div v-if="selected.id" class="admin-two-col" style="margin-top:2rem"><div class="field"><label>Replace cover image</label><input class="input" type="file" accept="image/jpeg,image/png,image/webp" @change="coverUpload = ($event.target as HTMLInputElement).files?.[0] ?? null"><button class="button button-small" :disabled="!coverUpload" @click="uploadCover">Upload cover</button></div><div v-if="selected.offering.kind === 'ebook'" class="field"><label>Private e-book file</label><input class="input" type="file" accept=".pdf,.epub" @change="digitalUpload = ($event.target as HTMLInputElement).files?.[0] ?? null"><button class="button button-small" :disabled="!digitalUpload" @click="uploadDigital">Upload private edition</button><p class="help">The current asset record is versioned so existing entitlements remain valid.</p></div></div></div>
            </section>

            <section v-if="section === 'import'" class="admin-editor"><h2>Import a WooCommerce export</h2><p class="lead">Preview every conversion before it touches the APF catalogue.</p><div class="field"><label>Woo Store API JSON or WooCommerce product CSV</label><input class="input" type="file" accept=".json,.csv" @change="upload = ($event.target as HTMLInputElement).files?.[0] ?? null"></div><button class="button" style="margin-top:1rem" :disabled="!upload" @click="previewImport">Generate preview</button><div v-if="importPreview" style="margin-top:2rem"><h3>{{ importPreview.summary.rows }} rows ready</h3><div class="chips"><span v-for="(count, name) in importPreview.summary.warnings" :key="name" class="chip">{{ String(name).replaceAll('_', ' ') }}: {{ count }}</span></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Title</th><th>SKU</th><th>Warnings</th></tr></thead><tbody><tr v-for="row in importPreview.rows" :key="row.source_id"><td>{{ row.title }}</td><td>{{ row.sku || `APF-WOO-${row.source_id}` }}</td><td>{{ row.warnings.join(', ') || 'None' }}</td></tr></tbody></table></div><button class="button" style="margin-top:1rem" @click="commitImport">Import these records</button></div></section>

            <section v-if="section === 'orders'" class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody><tr v-for="order in orders" :key="order.id"><td><strong>{{ order.number }}</strong><small>{{ new Date(order.created_at).toLocaleDateString('en-CA') }}</small></td><td>{{ order.email }}</td><td>{{ money(order.total_amount, order.currency) }}</td><td><span class="status-pill">{{ order.status }}</span></td><td><button v-if="order.payment_status === 'paid' && order.fulfillment_status !== 'fulfilled'" class="button button-small" @click="fulfil(order)">Mark fulfilled</button></td></tr></tbody></table></section>

            <section v-if="section === 'integrations'" class="admin-two-col"><article v-for="provider in ['stripe', 'paypal']" :key="provider" class="admin-editor"><p class="eyebrow">Payment provider</p><h2>{{ provider === 'stripe' ? 'Stripe' : 'PayPal' }}</h2><div class="field"><label>Environment</label><select v-model="integrationForms[provider].environment" class="select"><option value="sandbox">Sandbox</option><option value="live">Live</option></select></div><label style="display:block;margin:1rem 0"><input v-model="integrationForms[provider].enabled" type="checkbox"> Enable checkout</label><template v-if="provider === 'stripe'"><div class="field"><label>Secret key</label><input v-model="integrationForms.stripe.credentials.secret" class="input" type="password" placeholder="Leave blank to preserve saved value"></div><div class="field"><label>Webhook signing secret</label><input v-model="integrationForms.stripe.credentials.webhook_secret" class="input" type="password"></div></template><template v-else><div class="field"><label>Client ID</label><input v-model="integrationForms.paypal.credentials.client_id" class="input" type="password"></div><div class="field"><label>Client secret</label><input v-model="integrationForms.paypal.credentials.client_secret" class="input" type="password"></div><div class="field"><label>Webhook ID</label><input v-model="integrationForms.paypal.credentials.webhook_id" class="input" type="password"></div></template><button class="button" style="margin-top:1rem" @click="saveIntegration(provider)">Save encrypted settings</button></article></section>

            <section v-if="section === 'settings'"><div class="admin-two-col"><article class="admin-editor"><h2>Shipping zones</h2><div v-for="zone in settings.shipping_zones" :key="zone.id" class="setting-row"><strong>{{ zone.name }}</strong><span>{{ zone.country }}</span><small v-for="rule in zone.rules" :key="rule.id">{{ rule.name }} · {{ money(rule.rate_amount) }} · Free over {{ money(rule.free_above_amount) }}</small></div><p class="help">Launch defaults ship to Canada and the United States. Rates can be changed through the settings API.</p></article><article class="admin-editor"><h2>Tax rules</h2><p v-if="!settings.tax_rules.length" class="alert">No tax nexus rules are enabled. This is intentionally safe: have APF Press’s tax adviser approve jurisdictions and rates before collecting tax.</p><div v-for="rule in settings.tax_rules" :key="rule.id" class="setting-row"><strong>{{ rule.label }} — {{ rule.region || rule.country }}</strong><span>{{ (rule.rate_basis_points / 100).toFixed(2) }}%</span><small>{{ rule.nexus_enabled ? 'Nexus enabled' : 'Nexus disabled' }}</small></div></article></div></section>
        </main>
    </div>
</template>
