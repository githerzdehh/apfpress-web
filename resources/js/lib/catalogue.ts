import type { CatalogForm, EditionFormat, OfferingForm } from '../types/catalogue';

export function centsToCad(value: number | null | undefined): string {
    return value === null || value === undefined ? '' : (value / 100).toFixed(2);
}

export function cadToCents(value: string): number | null {
    const normalized = value.trim().replace(/^\$/, '');
    if (normalized === '') return null;
    if (!/^\d+(?:\.\d{1,2})?$/.test(normalized)) throw new Error('Enter a CAD amount with no more than two decimal places.');
    const [dollars, cents = ''] = normalized.split('.');
    return Number(dollars) * 100 + Number(cents.padEnd(2, '0'));
}

export function newOffering(kind: 'print_book' | 'ebook' = 'print_book', position = 0): OfferingForm {
    const digital = kind === 'ebook';
    return {
        active: true,
        position,
        kind,
        name: digital ? 'Digital edition' : 'Print edition',
        sku: '',
        price_cad: '',
        purchase_mode: 'inquiry',
        access_duration_days: digital ? 365 : null,
        edition: {
            format: (digital ? 'pdf' : 'paperback') as EditionFormat,
            edition_label: '', isbn_10: '', isbn_13: '', publication_date: '', page_count: null,
            language: 'en', weight_grams: null, width_mm: null, height_mm: null, depth_mm: null,
        },
        inventory: { on_hand: 0, reserved: 0, low_stock_threshold: 2, track_inventory: false, allow_backorder: false },
        current_digital_asset: null,
    };
}

export function newCatalogForm(): CatalogForm {
    return {
        type: 'book', title: '', subtitle: '', slug: '', summary: '', description: '', status: 'draft', featured: false,
        seo_title: '', seo_description: '', book_details: { publisher: 'APF Press', imprint: '', original_language: 'en' },
        contributors: [], categories: [], offerings: [newOffering()], cover: null, metadata_flags: [], warnings: {},
    };
}

export function normalizeCatalogForm(item: any): CatalogForm {
    return {
        id: item.id,
        type: 'book',
        title: item.title ?? '', subtitle: item.subtitle ?? '', slug: item.slug ?? '', summary: item.summary ?? '', description: item.description ?? '',
        status: item.status ?? 'draft', featured: Boolean(item.featured), seo_title: item.seo_title ?? '', seo_description: item.seo_description ?? '',
        book_details: {
            publisher: item.book_details?.publisher ?? 'APF Press',
            imprint: item.book_details?.imprint ?? '',
            original_language: item.book_details?.original_language ?? 'en',
        },
        contributors: (item.contributors ?? []).map((entry: any, position: number) => ({ id: entry.id, name: entry.name ?? '', role: entry.role ?? 'author', position })),
        categories: (item.categories ?? []).map((entry: any) => ({ id: entry.id, name: entry.name ?? '', slug: entry.slug })),
        offerings: (item.offerings ?? []).map((entry: any, position: number) => ({
            id: entry.id, active: Boolean(entry.active), position, kind: entry.kind ?? 'print_book', name: entry.name ?? '', sku: entry.sku ?? '',
            price_cad: centsToCad(entry.price_amount), purchase_mode: entry.purchase_mode ?? 'inquiry', access_duration_days: entry.access_duration_days ?? null,
            available: Boolean(entry.available),
            edition: {
                format: entry.edition?.format ?? (entry.kind === 'ebook' ? 'pdf' : 'paperback'),
                edition_label: entry.edition?.edition_label ?? '', isbn_10: entry.edition?.isbn_10 ?? '', isbn_13: entry.edition?.isbn_13 ?? '',
                publication_date: String(entry.edition?.publication_date ?? '').slice(0, 10), page_count: entry.edition?.page_count ?? null,
                language: entry.edition?.language ?? 'en', weight_grams: entry.edition?.weight_grams ?? null, width_mm: entry.edition?.width_mm ?? null,
                height_mm: entry.edition?.height_mm ?? null, depth_mm: entry.edition?.depth_mm ?? null,
            },
            inventory: {
                on_hand: entry.inventory?.on_hand ?? 0, reserved: entry.inventory?.reserved ?? 0,
                low_stock_threshold: entry.inventory?.low_stock_threshold ?? 2, track_inventory: Boolean(entry.inventory?.track_inventory),
                allow_backorder: Boolean(entry.inventory?.allow_backorder),
            },
            current_digital_asset: entry.current_digital_asset ?? null,
        })),
        cover: item.cover ?? null,
        metadata_flags: item.metadata_flags ?? [], warnings: item.warnings ?? {}, updated_at: item.updated_at,
    };
}

export function catalogPayload(form: CatalogForm): Record<string, unknown> {
    return {
        type: 'book', title: form.title, subtitle: form.subtitle, slug: form.slug, summary: form.summary, description: form.description,
        status: form.status, featured: form.featured, seo_title: form.seo_title, seo_description: form.seo_description,
        book_details: { ...form.book_details },
        contributors: form.contributors.map((entry, position) => ({ id: entry.id, name: entry.name, role: entry.role, position })),
        categories: form.categories.map((entry) => ({ id: entry.id, name: entry.name })),
        offerings: form.offerings.map((entry, position) => ({
            id: entry.id, active: entry.active, position, kind: entry.kind, name: entry.name, sku: entry.sku,
            price_amount: cadToCents(entry.price_cad), purchase_mode: entry.purchase_mode, access_duration_days: entry.access_duration_days,
            edition: { ...entry.edition },
            inventory: {
                on_hand: entry.inventory.on_hand, low_stock_threshold: entry.inventory.low_stock_threshold,
                track_inventory: entry.inventory.track_inventory, allow_backorder: entry.inventory.allow_backorder,
            },
        })),
    };
}

export function payloadFingerprint(form: CatalogForm): string {
    try { return JSON.stringify(catalogPayload(form)); } catch { return ''; }
}
