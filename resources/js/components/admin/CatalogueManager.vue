<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { ApiError, request } from '../../lib/http';
import { catalogPayload, newCatalogForm, newOffering, normalizeCatalogForm, payloadFingerprint } from '../../lib/catalogue';
import type { CatalogForm, CatalogOptions, CatalogSummary, FieldErrors, PaginatedCatalog } from '../../types/catalogue';
import FieldFeedback from './FieldFeedback.vue';
import ToastStack, { type ToastMessage } from './ToastStack.vue';

type Tab = 'editorial' | 'people' | 'editions' | 'discovery' | 'assets';
const tabs: Array<{ id: Tab; label: string; index: string }> = [
    { id: 'editorial', label: 'Editorial', index: '01' },
    { id: 'people', label: 'People & categories', index: '02' },
    { id: 'editions', label: 'Editions', index: '03' },
    { id: 'discovery', label: 'Discovery', index: '04' },
    { id: 'assets', label: 'Assets', index: '05' },
];

const catalog = ref<CatalogSummary[]>([]);
const pagination = ref<PaginatedCatalog['meta']>({ current_page: 1, last_page: 1, per_page: 25, total: 0, from: null, to: null });
const options = ref<CatalogOptions>({ contributors: [], categories: [], contributor_roles: ['author', 'editor', 'translator', 'illustrator', 'foreword', 'contributor'] });
const query = ref('');
const status = ref('');
const loading = ref(false);
const selected = ref<CatalogForm | null>(null);
const activeTab = ref<Tab>('editorial');
const errors = ref<FieldErrors>({});
const baseline = ref('');
const toasts = ref<ToastMessage[]>([]);
const toastSequence = ref(0);
const categoryDraft = ref('');
const coverUpload = ref<File | null>(null);
const coverAlt = ref('');
const digitalUploads = reactive<Record<string, File | null>>({});

const dirty = computed(() => Boolean(selected.value) && payloadFingerprint(selected.value!) !== baseline.value);
const tabErrorCounts = computed<Record<Tab, number>>(() => {
    const counts: Record<Tab, number> = { editorial: 0, people: 0, editions: 0, discovery: 0, assets: 0 };
    for (const key of Object.keys(errors.value)) counts[tabForPath(key)]++;
    return counts;
});
const tabWarningCounts = computed<Record<Tab, number>>(() => {
    const counts: Record<Tab, number> = { editorial: 0, people: 0, editions: 0, discovery: 0, assets: 0 };
    for (const key of Object.keys(selected.value?.warnings ?? {})) counts[tabForPath(key)]++;
    return counts;
});

function tabForPath(path: string): Tab {
    if (path.startsWith('contributors') || path.startsWith('categories')) return 'people';
    if (path.startsWith('offerings')) return path.includes('current_digital_asset') ? 'assets' : 'editions';
    if (path.startsWith('seo_')) return 'discovery';
    if (path.startsWith('cover')) return 'assets';
    return 'editorial';
}

function toast(type: ToastMessage['type'], title: string, message: string): void {
    const id = ++toastSequence.value;
    toasts.value.push({ id, type, title, message });
    if (type !== 'error') window.setTimeout(() => dismissToast(id), 5000);
}
function dismissToast(id: number): void { toasts.value = toasts.value.filter((entry) => entry.id !== id); }

function fieldError(path: string): string { return errors.value[path]?.[0] ?? ''; }
function fieldWarning(path: string): string { return selected.value?.warnings?.[path]?.[0] ?? ''; }
function feedbackId(path: string): string { return `catalog-feedback-${path.replace(/[^a-z0-9]+/gi, '-')}`; }
function describedBy(path: string): string | undefined { return fieldError(path) || fieldWarning(path) ? feedbackId(path) : undefined; }
function invalid(path: string): boolean { return Boolean(fieldError(path)); }
function clearField(path: string): void {
    const next = { ...errors.value };
    delete next[path];
    for (const key of Object.keys(next)) if (path.startsWith(`${key}.`)) delete next[key];
    errors.value = next;
    if (selected.value) {
        const warnings = { ...selected.value.warnings };
        for (const key of Object.keys(warnings)) if (key === path || path.startsWith(`${key}.`)) delete warnings[key];
        selected.value.warnings = warnings;
    }
}

async function loadCatalog(page = pagination.value.current_page): Promise<void> {
    loading.value = true;
    try {
        const params = new URLSearchParams({ page: String(page) });
        if (query.value.trim()) params.set('q', query.value.trim());
        if (status.value) params.set('status', status.value);
        const result = await request<PaginatedCatalog>(`/admin/api/catalog?${params}`);
        catalog.value = result.data.map((entry) => normalizeCatalogForm(entry));
        pagination.value = result.meta;
    } catch (caught) {
        toast('error', 'Catalogue unavailable', caught instanceof Error ? caught.message : 'The catalogue could not be loaded.');
    } finally { loading.value = false; }
}

async function loadOptions(): Promise<void> {
    try { options.value = await request<CatalogOptions>('/admin/api/catalog/options'); }
    catch (caught) { toast('error', 'Options unavailable', caught instanceof Error ? caught.message : 'Contributors and categories could not be loaded.'); }
}

function setSelected(value: CatalogForm, resetTab = true): void {
    selected.value = value;
    baseline.value = payloadFingerprint(value);
    errors.value = {};
    if (resetTab) activeTab.value = 'editorial';
    coverAlt.value = value.cover?.alt_text ?? `${value.title} book cover`;
    coverUpload.value = null;
}

function newItem(): void { setSelected(newCatalogForm()); }

async function editItem(id: number): Promise<void> {
    loading.value = true;
    try {
        const response = await request<any>(`/admin/api/catalog/${id}`);
        setSelected(normalizeCatalogForm(response.data ?? response));
    } catch (caught) {
        toast('error', 'Title unavailable', caught instanceof Error ? caught.message : 'The title could not be opened.');
    } finally { loading.value = false; }
}

function confirmLeave(): boolean {
    return !dirty.value || window.confirm('Discard your unsaved catalogue changes?');
}
function closeEditor(): void {
    if (!confirmLeave()) return;
    selected.value = null;
    errors.value = {};
}
defineExpose({ confirmLeave });

function validateClient(): boolean {
    const next: FieldErrors = {};
    if (!selected.value?.title.trim()) next.title = ['Enter a title.'];
    if (!selected.value?.offerings.length) next.offerings = ['Add at least one edition.'];
    selected.value?.offerings.forEach((offering, index) => {
        if (!offering.name.trim()) next[`offerings.${index}.name`] = ['Enter an edition name.'];
        try { catalogPayload({ ...selected.value!, offerings: [offering] }); }
        catch (caught) { next[`offerings.${index}.price_amount`] = [caught instanceof Error ? caught.message : 'Enter a valid price.']; }
    });
    errors.value = next;
    if (Object.keys(next).length) {
        activeTab.value = tabForPath(Object.keys(next)[0]);
        toast('error', 'Review highlighted fields', `${Object.keys(next).length} field${Object.keys(next).length === 1 ? '' : 's'} need attention before saving.`);
        return false;
    }
    return true;
}

async function saveItem(): Promise<void> {
    if (!selected.value || !validateClient()) return;
    loading.value = true;
    errors.value = {};
    const isNew = !selected.value.id;
    try {
        const response = await request<any>(isNew ? '/admin/api/catalog' : `/admin/api/catalog/${selected.value.id}`, {
            method: isNew ? 'POST' : 'PUT',
            body: JSON.stringify(catalogPayload(selected.value)),
        });
        const saved = normalizeCatalogForm(response.data ?? response);
        setSelected(saved, false);
        await loadCatalog(isNew ? 1 : pagination.value.current_page);
        const warningCount = Object.values(saved.warnings).reduce((total, messages) => total + messages.length, 0);
        toast('success', 'Catalogue record saved', warningCount ? `Saved with ${warningCount} metadata warning${warningCount === 1 ? '' : 's'} to review.` : 'The catalogue and public pages now use this record.');
    } catch (caught) {
        if (caught instanceof ApiError && caught.status === 422) {
            errors.value = caught.errors;
            const fields = Object.keys(caught.errors);
            if (fields.length) activeTab.value = tabForPath(fields[0]);
            toast('error', 'Review highlighted fields', `${fields.length || 1} field${fields.length === 1 ? '' : 's'} need attention. Your changes are still here.`);
        } else toast('error', 'Save failed', caught instanceof Error ? caught.message : 'The catalogue record could not be saved.');
    } finally { loading.value = false; }
}

function addContributor(): void {
    selected.value?.contributors.push({ name: '', role: 'author', position: selected.value.contributors.length });
}
function identifyContributor(index: number): void {
    const entry = selected.value?.contributors[index];
    if (!entry) return;
    const match = options.value.contributors.find((option) => option.name.toLocaleLowerCase() === entry.name.trim().toLocaleLowerCase());
    if (match) entry.id = match.id; else delete entry.id;
    clearField(`contributors.${index}.name`);
}
function contributorNameInput(index: number): void {
    const entry = selected.value?.contributors[index];
    if (entry) delete entry.id;
    clearField(`contributors.${index}.name`);
}
function removeContributor(index: number): void { selected.value?.contributors.splice(index, 1); }
function moveContributor(index: number, direction: -1 | 1): void {
    const target = index + direction;
    if (!selected.value || target < 0 || target >= selected.value.contributors.length) return;
    [selected.value.contributors[index], selected.value.contributors[target]] = [selected.value.contributors[target], selected.value.contributors[index]];
}

function addCategory(): void {
    if (!selected.value || !categoryDraft.value.trim()) return;
    const name = categoryDraft.value.trim();
    const match = options.value.categories.find((option) => option.name.toLocaleLowerCase() === name.toLocaleLowerCase());
    const candidate = match ? { id: match.id, name: match.name, slug: match.slug } : { name };
    if (!selected.value.categories.some((entry) => (entry.id && entry.id === candidate.id) || entry.name.toLocaleLowerCase() === candidate.name.toLocaleLowerCase())) {
        selected.value.categories.push(candidate);
    }
    categoryDraft.value = '';
    clearField('categories');
}

function addEdition(kind: 'print_book' | 'ebook'): void {
    selected.value?.offerings.push(newOffering(kind, selected.value.offerings.length));
    activeTab.value = 'editions';
}
function moveEdition(index: number, direction: -1 | 1): void {
    const target = index + direction;
    if (!selected.value || target < 0 || target >= selected.value.offerings.length) return;
    [selected.value.offerings[index], selected.value.offerings[target]] = [selected.value.offerings[target], selected.value.offerings[index]];
}
function removeEdition(index: number): void {
    const offering = selected.value?.offerings[index];
    if (!offering || !selected.value) return;
    if (offering.id) offering.active = !offering.active;
    else selected.value.offerings.splice(index, 1);
}
function editionKindChanged(index: number): void {
    const offering = selected.value?.offerings[index];
    if (!offering) return;
    const digital = offering.kind === 'ebook';
    offering.edition.format = digital ? 'pdf' : 'paperback';
    if (['Print edition', 'Digital edition'].includes(offering.name)) offering.name = digital ? 'Digital edition' : 'Print edition';
    offering.access_duration_days = digital ? (offering.access_duration_days ?? 365) : null;
    if (digital) offering.inventory.track_inventory = false;
}

async function uploadCover(): Promise<void> {
    if (!selected.value?.id || !coverUpload.value) return;
    loading.value = true;
    const form = new FormData(); form.append('cover', coverUpload.value); form.append('alt_text', coverAlt.value);
    try {
        const asset = await request<{ id: number; url: string; alt_text: string }>(`/admin/api/catalog/${selected.value.id}/cover`, { method: 'POST', body: form });
        selected.value.cover = asset;
        coverUpload.value = null;
        toast('success', 'Cover uploaded', 'The new cover is now attached to this title.');
    } catch (caught) {
        errors.value = { ...errors.value, cover: [caught instanceof Error ? caught.message : 'The cover could not be uploaded.'] };
        toast('error', 'Cover upload failed', fieldError('cover'));
    } finally { loading.value = false; }
}

function digitalKey(index: number): string { return String(selected.value?.offerings[index]?.id ?? `new-${index}`); }
async function uploadDigital(index: number): Promise<void> {
    const offering = selected.value?.offerings[index];
    const file = digitalUploads[digitalKey(index)];
    if (!offering?.id || !file) return;
    loading.value = true;
    const form = new FormData(); form.append('file', file); form.append('access_duration_days', String(offering.access_duration_days ?? 365));
    try {
        const asset = await request<{ id: number; file_name: string; version: number }>(`/admin/api/offerings/${offering.id}/digital-asset`, { method: 'POST', body: form });
        offering.current_digital_asset = { ...asset, size_bytes: file.size };
        digitalUploads[digitalKey(index)] = null;
        toast('success', 'Digital edition uploaded', 'The protected file is current. Enable online sale from the Editions tab when ready.');
    } catch (caught) {
        const path = `offerings.${index}.current_digital_asset`;
        errors.value = { ...errors.value, [path]: [caught instanceof Error ? caught.message : 'The digital edition could not be uploaded.'] };
        toast('error', 'Digital upload failed', fieldError(path));
    } finally { loading.value = false; }
}

function beforeUnload(event: BeforeUnloadEvent): void {
    if (!dirty.value) return;
    event.preventDefault();
    event.returnValue = '';
}
function keydown(event: KeyboardEvent): void {
    if ((event.ctrlKey || event.metaKey) && event.key.toLocaleLowerCase() === 's' && selected.value) {
        event.preventDefault(); void saveItem();
    }
}

onMounted(() => {
    void Promise.all([loadCatalog(1), loadOptions()]);
    window.addEventListener('beforeunload', beforeUnload);
    window.addEventListener('keydown', keydown);
});
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', beforeUnload);
    window.removeEventListener('keydown', keydown);
});
</script>

<template>
    <ToastStack :messages="toasts" @dismiss="dismissToast" />

    <div v-if="!selected" class="admin-section-bar catalogue-list-bar">
        <div><span>{{ pagination.total }} catalogue records</span><p>Edit books, editions, prices, contributors, and publication metadata.</p></div>
        <button class="button" type="button" @click="newItem"><span aria-hidden="true">＋</span> New catalogue item</button>
    </div>

    <div v-if="!selected" class="admin-panel">
        <form class="admin-catalog-filters" @submit.prevent="loadCatalog(1)">
            <div class="field"><label for="catalog-search">Search titles or contributors</label><input id="catalog-search" v-model="query" class="input" type="search" placeholder="Search the catalogue…"></div>
            <div class="field"><label for="catalog-status">Publication status</label><select id="catalog-status" v-model="status" class="select"><option value="">All statuses</option><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></select></div>
            <button class="button button-secondary" type="submit" :disabled="loading">Apply filters</button>
        </form>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Title</th><th>Status</th><th>Edition</th><th>Review</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    <tr v-for="item in catalog" :key="item.id">
                        <td><strong>{{ item.title }}</strong><small>{{ item.contributors.find((entry) => entry.role === 'author')?.name || 'No author credited' }}</small></td>
                        <td><span class="status-pill" :class="`status-${item.status}`">{{ item.status }}</span></td>
                        <td><span class="admin-table-code">{{ item.offerings.filter((entry) => entry.active).length }} active</span><small>{{ item.offerings[0]?.sku || 'No SKU' }}</small></td>
                        <td><span v-if="item.metadata_flags.length" class="issue-count" :title="item.metadata_flags.join(', ')">{{ item.metadata_flags.length }}</span><span v-else class="admin-complete">Complete</span></td>
                        <td><button class="text-link" type="button" @click="editItem(item.id!)">Edit title <span aria-hidden="true">→</span></button></td>
                    </tr>
                    <tr v-if="!catalog.length && !loading"><td colspan="5"><div class="admin-empty"><span>00</span><div><strong>No matching records.</strong><small>Clear the filters or create a new title.</small></div></div></td></tr>
                </tbody>
            </table>
        </div>
        <nav v-if="pagination.last_page > 1" class="admin-pagination" aria-label="Catalogue pages">
            <span>Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}</span>
            <div><button class="button button-small button-secondary" type="button" :disabled="pagination.current_page <= 1 || loading" @click="loadCatalog(pagination.current_page - 1)">← Previous</button><span>Page {{ pagination.current_page }} of {{ pagination.last_page }}</span><button class="button button-small button-secondary" type="button" :disabled="pagination.current_page >= pagination.last_page || loading" @click="loadCatalog(pagination.current_page + 1)">Next →</button></div>
        </nav>
    </div>

    <div v-else class="admin-editor admin-catalog-editor">
        <div class="admin-editor-head">
            <div><p class="eyebrow">{{ selected.id ? 'Catalogue record' : 'New publication' }}</p><h2>{{ selected.title || 'Build a new title' }}</h2><p>{{ selected.id ? 'Edit the complete editorial and commercial record.' : 'Save the draft once before adding publication assets.' }}</p></div>
            <button class="button button-secondary" type="button" @click="closeEditor">← Back to catalogue</button>
        </div>

        <nav class="admin-editor-tabs" aria-label="Catalogue editor sections">
            <button v-for="tab in tabs" :key="tab.id" type="button" :class="{ active: activeTab === tab.id }" :aria-current="activeTab === tab.id ? 'step' : undefined" @click="activeTab = tab.id">
                <span>{{ tab.index }}</span><strong>{{ tab.label }}</strong><b v-if="tabErrorCounts[tab.id]" class="tab-error-count">{{ tabErrorCounts[tab.id] }}</b><b v-else-if="tabWarningCounts[tab.id]" class="tab-warning-count">{{ tabWarningCounts[tab.id] }}</b>
            </button>
        </nav>

        <form class="catalogue-editor-form" novalidate @submit.prevent="saveItem">
            <section v-show="activeTab === 'editorial'" class="admin-form-panel" aria-labelledby="catalog-tab-editorial">
                <div class="admin-form-panel-head"><div><p class="eyebrow">Editorial record</p><h3 id="catalog-tab-editorial">Reader-facing title information</h3></div><p>Core copy, publication state, and publisher details.</p></div>
                <div class="form-grid">
                    <div class="field field-full" :class="{ 'field-invalid': invalid('title') }"><label for="catalog-title">Title <span>Required</span></label><input id="catalog-title" v-model="selected.title" class="input" required :aria-invalid="invalid('title')" :aria-describedby="describedBy('title')" @input="clearField('title')"><FieldFeedback :id="feedbackId('title')" :error="fieldError('title')" /></div>
                    <div class="field field-full"><label for="catalog-subtitle">Subtitle</label><input id="catalog-subtitle" v-model="selected.subtitle" class="input" maxlength="255"></div>
                    <div class="field" :class="{ 'field-invalid': invalid('slug') }"><label for="catalog-slug">URL slug <span>Leave blank to generate</span></label><input id="catalog-slug" v-model="selected.slug" class="input" inputmode="url" :aria-invalid="invalid('slug')" :aria-describedby="describedBy('slug')" @input="clearField('slug')"><FieldFeedback :id="feedbackId('slug')" :error="fieldError('slug')" /></div>
                    <div class="field"><label for="catalog-status-edit">Publication status</label><select id="catalog-status-edit" v-model="selected.status" class="select"><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></select></div>
                    <div class="field field-full"><label for="catalog-summary">Short summary <span>{{ selected.summary.length }}/2000</span></label><textarea id="catalog-summary" v-model="selected.summary" class="textarea textarea-small" maxlength="2000"></textarea></div>
                    <div class="field field-full"><label for="catalog-description">Full description</label><textarea id="catalog-description" v-model="selected.description" class="textarea"></textarea></div>
                    <div class="field" :class="{ 'field-invalid': invalid('book_details.publisher') }"><label for="catalog-publisher">Publisher</label><input id="catalog-publisher" v-model="selected.book_details.publisher" class="input" :aria-invalid="invalid('book_details.publisher')" @input="clearField('book_details.publisher')"><FieldFeedback :id="feedbackId('book_details.publisher')" :error="fieldError('book_details.publisher')" /></div>
                    <div class="field" :class="{ 'field-invalid': invalid('book_details.imprint') }"><label for="catalog-imprint">Imprint</label><input id="catalog-imprint" v-model="selected.book_details.imprint" class="input" @input="clearField('book_details.imprint')"><FieldFeedback :id="feedbackId('book_details.imprint')" :error="fieldError('book_details.imprint')" /></div>
                    <div class="field" :class="{ 'field-invalid': invalid('book_details.original_language') }"><label for="catalog-language">Original language</label><input id="catalog-language" v-model="selected.book_details.original_language" class="input" maxlength="10" placeholder="en" @input="clearField('book_details.original_language')"><FieldFeedback :id="feedbackId('book_details.original_language')" :error="fieldError('book_details.original_language')" /></div>
                    <label class="admin-check"><input v-model="selected.featured" type="checkbox"><span><strong>Featured title</strong><small>Give this book priority in public editorial placements.</small></span></label>
                </div>
            </section>

            <section v-show="activeTab === 'people'" class="admin-form-panel" aria-labelledby="catalog-tab-people">
                <div class="admin-form-panel-head"><div><p class="eyebrow">People & taxonomy</p><h3 id="catalog-tab-people">Contributors and categories</h3></div><p>Select existing records or type a new name to create it when saving.</p></div>
                <div class="admin-collection-block">
                    <div class="admin-collection-head"><div><h4>Contributors</h4><p>Order and role control how credits appear publicly.</p></div><button class="button button-small button-secondary" type="button" @click="addContributor">＋ Add contributor</button></div>
                    <FieldFeedback :id="feedbackId('contributors')" :error="fieldError('contributors')" :warning="fieldWarning('contributors')" />
                    <div v-for="(contributor, index) in selected.contributors" :key="`${contributor.id ?? 'new'}-${index}`" class="admin-collection-row contributor-row">
                        <span class="collection-position">{{ String(index + 1).padStart(2, '0') }}</span>
                        <div class="field" :class="{ 'field-invalid': invalid(`contributors.${index}.name`) }"><label :for="`contributor-name-${index}`">Name</label><input :id="`contributor-name-${index}`" v-model="contributor.name" class="input" list="contributor-options" :aria-invalid="invalid(`contributors.${index}.name`)" @change="identifyContributor(index)" @input="contributorNameInput(index)"><FieldFeedback :id="feedbackId(`contributors.${index}.name`)" :error="fieldError(`contributors.${index}.name`)" /></div>
                        <div class="field"><label :for="`contributor-role-${index}`">Role</label><select :id="`contributor-role-${index}`" v-model="contributor.role" class="select"><option v-for="role in options.contributor_roles" :key="role" :value="role">{{ role.replace('_', ' ') }}</option></select></div>
                        <div class="collection-actions"><button type="button" :disabled="index === 0" aria-label="Move contributor up" @click="moveContributor(index, -1)">↑</button><button type="button" :disabled="index === selected.contributors.length - 1" aria-label="Move contributor down" @click="moveContributor(index, 1)">↓</button><button type="button" aria-label="Remove contributor" @click="removeContributor(index)">×</button></div>
                    </div>
                    <div v-if="!selected.contributors.length" class="admin-inline-empty">No contributors assigned yet.</div>
                    <datalist id="contributor-options"><option v-for="entry in options.contributors" :key="entry.id" :value="entry.name"></option></datalist>
                </div>
                <div class="admin-collection-block">
                    <div class="admin-collection-head"><div><h4>Categories</h4><p>Categories support public filters and related-title discovery.</p></div></div>
                    <FieldFeedback :id="feedbackId('categories')" :error="fieldError('categories')" />
                    <div class="category-picker"><div class="field"><label for="category-picker">Select or create category</label><input id="category-picker" v-model="categoryDraft" class="input" list="category-options" @keydown.enter.prevent="addCategory"></div><button class="button button-small button-secondary" type="button" :disabled="!categoryDraft.trim()" @click="addCategory">Add category</button></div>
                    <datalist id="category-options"><option v-for="entry in options.categories" :key="entry.id" :value="entry.name"></option></datalist>
                    <div class="admin-tag-list"><span v-for="(category, index) in selected.categories" :key="category.id ?? category.name">{{ category.name }} <button type="button" :aria-label="`Remove ${category.name}`" @click="selected.categories.splice(index, 1)">×</button></span><small v-if="!selected.categories.length">No categories assigned.</small></div>
                </div>
            </section>

            <section v-show="activeTab === 'editions'" class="admin-form-panel" aria-labelledby="catalog-tab-editions">
                <div class="admin-form-panel-head"><div><p class="eyebrow">Edition & commerce</p><h3 id="catalog-tab-editions">Formats, identifiers, price, and stock</h3></div><div class="panel-head-actions"><button class="button button-small button-secondary" type="button" @click="addEdition('print_book')">＋ Print edition</button><button class="button button-small button-secondary" type="button" @click="addEdition('ebook')">＋ E-book</button></div></div>
                <FieldFeedback :id="feedbackId('offerings')" :error="fieldError('offerings')" />
                <details v-for="(offering, index) in selected.offerings" :key="offering.id ?? `new-${index}`" class="admin-edition-card" :class="{ 'edition-inactive': !offering.active }" open>
                    <summary><span>{{ String(index + 1).padStart(2, '0') }}</span><div><strong>{{ offering.name || 'Untitled edition' }}</strong><small>{{ offering.kind === 'ebook' ? 'E-book' : 'Print book' }} · {{ offering.purchase_mode }}<template v-if="!offering.active"> · inactive</template></small></div><b>{{ offering.price_cad ? `$${offering.price_cad} CAD` : 'Price pending' }}</b></summary>
                    <div class="edition-toolbar"><div><button type="button" :disabled="index === 0" @click="moveEdition(index, -1)">↑ Move up</button><button type="button" :disabled="index === selected.offerings.length - 1" @click="moveEdition(index, 1)">↓ Move down</button></div><button type="button" class="edition-remove" @click="removeEdition(index)">{{ offering.id ? (offering.active ? 'Deactivate safely' : 'Restore edition') : 'Remove edition' }}</button></div>
                    <div class="form-grid edition-fields">
                        <div class="field" :class="{ 'field-invalid': invalid(`offerings.${index}.kind`) }"><label :for="`edition-kind-${index}`">Edition type</label><select :id="`edition-kind-${index}`" v-model="offering.kind" class="select" @change="editionKindChanged(index); clearField(`offerings.${index}.kind`)"><option value="print_book">Print book</option><option value="ebook">E-book</option></select><FieldFeedback :id="feedbackId(`offerings.${index}.kind`)" :error="fieldError(`offerings.${index}.kind`)" /></div>
                        <div class="field" :class="{ 'field-invalid': invalid(`offerings.${index}.name`) }"><label :for="`edition-name-${index}`">Edition name</label><input :id="`edition-name-${index}`" v-model="offering.name" class="input" :aria-invalid="invalid(`offerings.${index}.name`)" @input="clearField(`offerings.${index}.name`)"><FieldFeedback :id="feedbackId(`offerings.${index}.name`)" :error="fieldError(`offerings.${index}.name`)" /></div>
                        <div class="field" :class="{ 'field-invalid': invalid(`offerings.${index}.edition.format`) }"><label :for="`edition-format-${index}`">Format</label><select :id="`edition-format-${index}`" v-model="offering.edition.format" class="select" @change="clearField(`offerings.${index}.edition.format`)"><template v-if="offering.kind === 'print_book'"><option value="paperback">Paperback</option><option value="hardcover">Hardcover</option></template><template v-else><option value="pdf">PDF</option><option value="epub">EPUB</option></template><option value="other">Other</option></select><FieldFeedback :id="feedbackId(`offerings.${index}.edition.format`)" :error="fieldError(`offerings.${index}.edition.format`)" /></div>
                        <div class="field"><label :for="`edition-label-${index}`">Edition label</label><input :id="`edition-label-${index}`" v-model="offering.edition.edition_label" class="input" placeholder="Second edition"></div>
                        <div class="field" :class="{ 'field-invalid': invalid(`offerings.${index}.sku`) }"><label :for="`edition-sku-${index}`">SKU <span>{{ offering.purchase_mode === 'online' ? 'Required online' : 'Optional' }}</span></label><input :id="`edition-sku-${index}`" v-model="offering.sku" class="input" :aria-invalid="invalid(`offerings.${index}.sku`)" @input="clearField(`offerings.${index}.sku`)"><FieldFeedback :id="feedbackId(`offerings.${index}.sku`)" :error="fieldError(`offerings.${index}.sku`)" /></div>
                        <div class="field" :class="{ 'field-invalid': invalid(`offerings.${index}.price_amount`) }"><label :for="`edition-price-${index}`">Price <span>CAD dollars</span></label><div class="money-input"><span>$</span><input :id="`edition-price-${index}`" v-model="offering.price_cad" class="input" inputmode="decimal" placeholder="24.99" :aria-invalid="invalid(`offerings.${index}.price_amount`)" @input="clearField(`offerings.${index}.price_amount`)"></div><FieldFeedback :id="feedbackId(`offerings.${index}.price_amount`)" :error="fieldError(`offerings.${index}.price_amount`)" :warning="fieldWarning(`offerings.${index}.price_amount`)" /></div>
                        <div class="field" :class="{ 'field-invalid': invalid(`offerings.${index}.purchase_mode`) }"><label :for="`edition-mode-${index}`">Purchase mode</label><select :id="`edition-mode-${index}`" v-model="offering.purchase_mode" class="select" :aria-invalid="invalid(`offerings.${index}.purchase_mode`)" @change="clearField(`offerings.${index}.purchase_mode`)"><option value="online">Online</option><option value="inquiry">Inquiry</option><option value="unavailable">Unavailable</option></select><FieldFeedback :id="feedbackId(`offerings.${index}.purchase_mode`)" :error="fieldError(`offerings.${index}.purchase_mode`)" /></div>
                        <label class="admin-check"><input v-model="offering.active" type="checkbox"><span><strong>Active edition</strong><small>Show this edition on public catalogue pages.</small></span></label>
                        <div class="field" :class="{ 'field-invalid': invalid(`offerings.${index}.edition.isbn_10`) }"><label :for="`edition-isbn10-${index}`">ISBN-10</label><input :id="`edition-isbn10-${index}`" v-model="offering.edition.isbn_10" class="input" maxlength="17" inputmode="text" @input="clearField(`offerings.${index}.edition.isbn_10`)"><FieldFeedback :id="feedbackId(`offerings.${index}.edition.isbn_10`)" :error="fieldError(`offerings.${index}.edition.isbn_10`)" /></div>
                        <div class="field" :class="{ 'field-invalid': invalid(`offerings.${index}.edition.isbn_13`) }"><label :for="`edition-isbn13-${index}`">ISBN-13</label><input :id="`edition-isbn13-${index}`" v-model="offering.edition.isbn_13" class="input" maxlength="17" inputmode="numeric" @input="clearField(`offerings.${index}.edition.isbn_13`)"><FieldFeedback :id="feedbackId(`offerings.${index}.edition.isbn_13`)" :error="fieldError(`offerings.${index}.edition.isbn_13`)" :warning="fieldWarning(`offerings.${index}.edition.isbn_13`)" /></div>
                        <div class="field" :class="{ 'field-invalid': invalid(`offerings.${index}.edition.publication_date`) }"><label :for="`edition-date-${index}`">Publication date</label><input :id="`edition-date-${index}`" v-model="offering.edition.publication_date" class="input" type="date" :aria-invalid="invalid(`offerings.${index}.edition.publication_date`)" @input="clearField(`offerings.${index}.edition.publication_date`)"><FieldFeedback :id="feedbackId(`offerings.${index}.edition.publication_date`)" :error="fieldError(`offerings.${index}.edition.publication_date`)" :warning="fieldWarning(`offerings.${index}.edition.publication_date`)" /></div>
                        <div class="field" :class="{ 'field-invalid': invalid(`offerings.${index}.edition.page_count`) }"><label :for="`edition-pages-${index}`">Page count</label><input :id="`edition-pages-${index}`" v-model.number="offering.edition.page_count" class="input" type="number" min="1" inputmode="numeric" @input="clearField(`offerings.${index}.edition.page_count`)"><FieldFeedback :id="feedbackId(`offerings.${index}.edition.page_count`)" :error="fieldError(`offerings.${index}.edition.page_count`)" :warning="fieldWarning(`offerings.${index}.edition.page_count`)" /></div>
                        <div class="field" :class="{ 'field-invalid': invalid(`offerings.${index}.edition.language`) }"><label :for="`edition-language-${index}`">Edition language</label><input :id="`edition-language-${index}`" v-model="offering.edition.language" class="input" maxlength="10" placeholder="en" @input="clearField(`offerings.${index}.edition.language`)"><FieldFeedback :id="feedbackId(`offerings.${index}.edition.language`)" :error="fieldError(`offerings.${index}.edition.language`)" /></div>
                        <div v-if="offering.kind === 'ebook'" class="field" :class="{ 'field-invalid': invalid(`offerings.${index}.access_duration_days`) }"><label :for="`edition-access-${index}`">Download access <span>Days</span></label><input :id="`edition-access-${index}`" v-model.number="offering.access_duration_days" class="input" type="number" min="1" max="3650" @input="clearField(`offerings.${index}.access_duration_days`)"><FieldFeedback :id="feedbackId(`offerings.${index}.access_duration_days`)" :error="fieldError(`offerings.${index}.access_duration_days`)" /></div>
                        <template v-if="offering.kind === 'print_book'">
                            <div v-for="dimension in [{ key: 'weight_grams', label: 'Weight', unit: 'grams' }, { key: 'width_mm', label: 'Width', unit: 'mm' }, { key: 'height_mm', label: 'Height', unit: 'mm' }, { key: 'depth_mm', label: 'Depth', unit: 'mm' }]" :key="dimension.key" class="field" :class="{ 'field-invalid': invalid(`offerings.${index}.edition.${dimension.key}`) }"><label :for="`edition-${dimension.key}-${index}`">{{ dimension.label }} <span>{{ dimension.unit }}</span></label><input :id="`edition-${dimension.key}-${index}`" v-model.number="offering.edition[dimension.key as keyof typeof offering.edition]" class="input" type="number" min="0" step="0.01" @input="clearField(`offerings.${index}.edition.${dimension.key}`)"><FieldFeedback :id="feedbackId(`offerings.${index}.edition.${dimension.key}`)" :error="fieldError(`offerings.${index}.edition.${dimension.key}`)" /></div>
                            <div class="field" :class="{ 'field-invalid': invalid(`offerings.${index}.inventory.on_hand`) }"><label :for="`edition-stock-${index}`">Stock on hand</label><input :id="`edition-stock-${index}`" v-model.number="offering.inventory.on_hand" class="input" type="number" min="0" @input="clearField(`offerings.${index}.inventory.on_hand`)"><FieldFeedback :id="feedbackId(`offerings.${index}.inventory.on_hand`)" :error="fieldError(`offerings.${index}.inventory.on_hand`)" :warning="fieldWarning(`offerings.${index}.inventory.on_hand`)" /></div>
                            <div class="field" :class="{ 'field-invalid': invalid(`offerings.${index}.inventory.low_stock_threshold`) }"><label :for="`edition-threshold-${index}`">Low-stock threshold</label><input :id="`edition-threshold-${index}`" v-model.number="offering.inventory.low_stock_threshold" class="input" type="number" min="0" @input="clearField(`offerings.${index}.inventory.low_stock_threshold`)"><FieldFeedback :id="feedbackId(`offerings.${index}.inventory.low_stock_threshold`)" :error="fieldError(`offerings.${index}.inventory.low_stock_threshold`)" /></div>
                            <div class="inventory-readonly"><span>Reserved</span><strong>{{ offering.inventory.reserved }}</strong><small>Reserved stock is managed by checkout.</small></div>
                            <label class="admin-check"><input v-model="offering.inventory.track_inventory" type="checkbox"><span><strong>Track exact inventory</strong><small>Enforce the stock count during checkout.</small></span></label>
                            <label class="admin-check"><input v-model="offering.inventory.allow_backorder" type="checkbox"><span><strong>Allow backorders</strong><small>Permit sales after available stock reaches zero.</small></span></label>
                        </template>
                    </div>
                </details>
            </section>

            <section v-show="activeTab === 'discovery'" class="admin-form-panel" aria-labelledby="catalog-tab-discovery">
                <div class="admin-form-panel-head"><div><p class="eyebrow">Search & discovery</p><h3 id="catalog-tab-discovery">Search result metadata</h3></div><p>Optional overrides; the title and summary remain the fallback.</p></div>
                <div class="form-grid">
                    <div class="field field-full" :class="{ 'field-invalid': invalid('seo_title') }"><label for="catalog-seo-title">SEO title <span>{{ selected.seo_title.length }}/70</span></label><input id="catalog-seo-title" v-model="selected.seo_title" class="input" maxlength="70" @input="clearField('seo_title')"><FieldFeedback :id="feedbackId('seo_title')" :error="fieldError('seo_title')" /></div>
                    <div class="field field-full" :class="{ 'field-invalid': invalid('seo_description') }"><label for="catalog-seo-description">SEO description <span>{{ selected.seo_description.length }}/320</span></label><textarea id="catalog-seo-description" v-model="selected.seo_description" class="textarea textarea-small" maxlength="320" @input="clearField('seo_description')"></textarea><FieldFeedback :id="feedbackId('seo_description')" :error="fieldError('seo_description')" /></div>
                    <div class="admin-search-preview field-full"><span>Search preview</span><strong>{{ selected.seo_title || selected.title || 'Catalogue title' }}</strong><a>{{ selected.slug ? `/books/${selected.slug}` : '/books/generated-title-slug' }}</a><p>{{ selected.seo_description || selected.summary || 'Add a short summary to preview the search description.' }}</p></div>
                </div>
            </section>

            <section v-show="activeTab === 'assets'" class="admin-form-panel" aria-labelledby="catalog-tab-assets">
                <div class="admin-form-panel-head"><div><p class="eyebrow">Publication assets</p><h3 id="catalog-tab-assets">Cover and protected files</h3></div><p>Uploads become available after the catalogue draft has been saved.</p></div>
                <div v-if="!selected.id" class="admin-policy-note"><span>i</span><p>Save this title as a draft first. The saved edition IDs are required for secure file storage.</p></div>
                <div class="admin-upload-grid admin-two-col">
                    <article class="admin-upload-card cover-upload-card">
                        <div><p class="eyebrow">Public media</p><h4>Cover image</h4><div v-if="selected.cover?.url" class="admin-cover-preview"><img :src="selected.cover.url" :alt="selected.cover.alt_text || ''"></div><p v-else>JPEG, PNG, or WebP up to 10 MB.</p></div>
                        <div class="field"><label for="cover-alt">Alternative text</label><input id="cover-alt" v-model="coverAlt" class="input" maxlength="255"></div>
                        <input class="input file-input" type="file" accept="image/jpeg,image/png,image/webp" :disabled="!selected.id" @change="coverUpload = ($event.target as HTMLInputElement).files?.[0] ?? null">
                        <FieldFeedback :id="feedbackId('cover')" :error="fieldError('cover')" />
                        <button class="button button-small" type="button" :disabled="!selected.id || !coverUpload || loading" @click="uploadCover">Upload cover</button>
                    </article>
                    <article v-for="(offering, index) in selected.offerings.filter((entry) => entry.kind === 'ebook')" :key="offering.id ?? `digital-${index}`" class="admin-upload-card">
                        <div><p class="eyebrow">Protected media</p><h4>{{ offering.name }}</h4><p v-if="offering.current_digital_asset"><strong>{{ offering.current_digital_asset.file_name }}</strong><br>Current version {{ offering.current_digital_asset.version }}</p><p v-else>PDF or EPUB up to 100 MB. Upload before enabling online sale.</p></div>
                        <input class="input file-input" type="file" accept=".pdf,.epub" :disabled="!offering.id" @change="digitalUploads[String(offering.id ?? `new-${index}`)] = ($event.target as HTMLInputElement).files?.[0] ?? null">
                        <FieldFeedback :id="feedbackId(`offerings.${selected.offerings.indexOf(offering)}.current_digital_asset`)" :error="fieldError(`offerings.${selected.offerings.indexOf(offering)}.current_digital_asset`)" />
                        <button class="button button-small" type="button" :disabled="!offering.id || !digitalUploads[String(offering.id)] || loading" @click="uploadDigital(selected.offerings.indexOf(offering))">Upload protected edition</button>
                    </article>
                </div>
            </section>

            <div class="admin-save-bar admin-save-bar-sticky">
                <div><strong>{{ selected.id ? (dirty ? 'Unsaved catalogue changes' : 'All changes saved') : 'Save this new title as a draft' }}</strong><small>{{ loading ? 'Working…' : 'Save updates the admin API and reader-facing catalogue together.' }}</small></div>
                <div class="save-bar-actions"><button class="button button-secondary" type="button" @click="closeEditor">Cancel</button><button class="button" type="submit" :disabled="loading || (Boolean(selected.id) && !dirty)">{{ loading ? 'Saving…' : selected.id ? 'Save changes' : 'Save draft' }}</button></div>
            </div>
        </form>
    </div>
</template>
