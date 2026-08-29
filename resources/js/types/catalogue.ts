export type CatalogStatus = 'draft' | 'published' | 'archived';
export type ContributorRole = 'author' | 'editor' | 'translator' | 'illustrator' | 'foreword' | 'contributor';
export type OfferingKind = 'print_book' | 'ebook';
export type PurchaseMode = 'online' | 'inquiry' | 'unavailable';
export type EditionFormat = 'paperback' | 'hardcover' | 'pdf' | 'epub' | 'other';

export interface ContributorInput { id?: number; name: string; role: ContributorRole; position: number }
export interface CategoryInput { id?: number; name: string; slug?: string }
export interface BookDetails { publisher: string; imprint: string; original_language: string }
export interface EditionInput {
    format: EditionFormat;
    edition_label: string;
    isbn_10: string;
    isbn_13: string;
    publication_date: string;
    page_count: number | null;
    language: string;
    weight_grams: number | null;
    width_mm: number | null;
    height_mm: number | null;
    depth_mm: number | null;
}
export interface InventoryInput {
    on_hand: number;
    reserved: number;
    low_stock_threshold: number;
    track_inventory: boolean;
    allow_backorder: boolean;
}
export interface DigitalAssetSummary { id: number; file_name: string; version: number; size_bytes: number | null }
export interface OfferingForm {
    id?: number;
    active: boolean;
    position: number;
    kind: OfferingKind;
    name: string;
    sku: string;
    price_cad: string;
    purchase_mode: PurchaseMode;
    access_duration_days: number | null;
    available?: boolean;
    edition: EditionInput;
    inventory: InventoryInput;
    current_digital_asset: DigitalAssetSummary | null;
}
export interface CoverSummary { id: number; url: string; alt_text: string }
export interface CatalogForm {
    id?: number;
    type: 'book';
    title: string;
    subtitle: string;
    slug: string;
    summary: string;
    description: string;
    status: CatalogStatus;
    featured: boolean;
    seo_title: string;
    seo_description: string;
    book_details: BookDetails;
    contributors: ContributorInput[];
    categories: CategoryInput[];
    offerings: OfferingForm[];
    cover: CoverSummary | null;
    metadata_flags: string[];
    warnings: Record<string, string[]>;
    updated_at?: string;
}
export interface CatalogSummary extends CatalogForm {}
export interface CatalogOptions {
    contributors: Array<{ id: number; name: string }>;
    categories: Array<{ id: number; name: string; slug: string }>;
    contributor_roles: ContributorRole[];
}
export interface PaginatedCatalog {
    data: CatalogSummary[];
    meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
}
export type FieldErrors = Record<string, string[]>;
