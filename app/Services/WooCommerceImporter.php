<?php

namespace App\Services;

use App\Models\BookDetail;
use App\Models\BookEdition;
use App\Models\CatalogItem;
use App\Models\Category;
use App\Models\Contributor;
use App\Models\Inventory;
use App\Models\MediaAsset;
use App\Models\Offering;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class WooCommerceImporter
{
    /**
     * Convert WooCommerce Store API records to the stable import contract.
     *
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    public function normalizeStoreApi(array $products): array
    {
        return array_map(function (array $product): array {
            $price = Arr::get($product, 'prices.price');
            $minorUnit = (int) Arr::get($product, 'prices.currency_minor_unit', 2);
            $categories = array_map(fn (array $category) => [
                'name' => $this->plainText((string) ($category['name'] ?? '')),
                'slug' => Str::slug((string) ($category['slug'] ?? $category['name'] ?? 'uncategorized')),
            ], $product['categories'] ?? []);
            $categorySlugs = array_column($categories, 'slug');
            $kind = count(array_intersect($categorySlugs, ['ebooks', 'e-books', 'ebook'])) > 0
                ? 'ebook'
                : 'print_book';

            $summary = $this->cleanImportedText($this->plainText((string) ($product['short_description'] ?? '')));
            $description = $this->cleanImportedText($this->plainText((string) ($product['description'] ?? '')));

            return [
                'source_id' => (string) ($product['id'] ?? ''),
                'slug' => Str::slug((string) ($product['slug'] ?? $product['name'] ?? Str::uuid())),
                'title' => $this->plainText((string) ($product['name'] ?? 'Untitled')),
                'summary' => $summary,
                'description' => $description,
                'author' => $this->extractAuthor($summary."\n".$description),
                'kind' => $kind,
                'price_amount' => is_numeric($price) ? (int) $price : null,
                'currency' => strtoupper((string) Arr::get($product, 'prices.currency_code', 'CAD')),
                'purchasable' => (bool) ($product['is_purchasable'] ?? false),
                'in_stock' => (bool) ($product['is_in_stock'] ?? true),
                'stock_quantity' => is_numeric($product['stock_quantity'] ?? null) ? (int) $product['stock_quantity'] : null,
                'sku' => trim((string) ($product['sku'] ?? '')) ?: null,
                'categories' => $categories,
                'image_url' => Arr::get($product, 'images.0.src'),
                'source_url' => $product['permalink'] ?? null,
            ];
        }, $products);
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array{created: int, updated: int, warnings: array<string, int>}
     */
    public function import(array $products, bool $downloadImages = false): array
    {
        $summary = [
            'created' => 0,
            'updated' => 0,
            'warnings' => [
                'missing_author' => 0,
                'missing_isbn' => 0,
                'missing_publication_date' => 0,
                'missing_price' => 0,
                'missing_stock_count' => 0,
            ],
        ];

        foreach ($products as $source) {
            DB::transaction(function () use ($source, $downloadImages, &$summary): void {
                $sourceId = (string) ($source['source_id'] ?? '');
                if ($sourceId === '') {
                    throw new RuntimeException('Every import row must include a source_id.');
                }

                $sourceSummary = $this->cleanImportedText((string) ($source['summary'] ?? ''));
                $sourceDescription = $this->cleanImportedText((string) ($source['description'] ?? ''));

                $existing = CatalogItem::query()
                    ->where('source_system', 'woocommerce')
                    ->where('source_id', $sourceId)
                    ->with(['contributors', 'offerings.bookEdition', 'offerings.inventory'])
                    ->first();
                $author = $this->cleanAuthor((string) ($source['author'] ?? ''));
                $author = $author !== '' ? $author : $this->extractAuthor((string) ($source['summary'] ?? ''));
                $publicationDate = $this->inferPublicationDate($source);
                $kind = ($source['kind'] ?? 'print_book') === 'ebook' ? 'ebook' : 'print_book';
                $existingOffering = $existing?->offerings->firstWhere('kind', $kind);
                $existingEdition = $existingOffering?->bookEdition;
                $effectiveAuthor = $author !== '' ? $author : ($existing?->contributors->first()?->name ?? '');
                $effectivePublicationDate = $publicationDate ?? $existingEdition?->publication_date?->toDateString();
                $effectivePrice = ($source['price_amount'] ?? null) ?? $existingOffering?->price_amount;
                $effectiveIsbn10 = $this->inferIsbn($source, 10) ?: $existingEdition?->isbn_10;
                $effectiveIsbn13 = $this->inferIsbn($source, 13) ?: $existingEdition?->isbn_13;
                $flags = array_values(array_filter([
                    $effectiveAuthor === '' ? 'missing_author' : null,
                    empty($effectiveIsbn10) && empty($effectiveIsbn13) ? 'missing_isbn' : null,
                    $effectivePublicationDate === null ? 'missing_publication_date' : null,
                    $effectivePrice === null ? 'missing_price' : null,
                    ($source['stock_quantity'] ?? null) === null && ! $existingOffering?->inventory?->track_inventory ? 'missing_stock_count' : null,
                ]));

                foreach ($flags as $flag) {
                    $summary['warnings'][$flag]++;
                }

                $item = CatalogItem::query()->updateOrCreate(
                    ['source_system' => 'woocommerce', 'source_id' => $sourceId],
                    [
                        'type' => 'book',
                        'slug' => $this->uniqueSlug((string) ($source['slug'] ?? $source['title'] ?? 'book'), $existing?->id),
                        'title' => trim((string) ($source['title'] ?? 'Untitled')),
                        'summary' => $sourceSummary ?: ($existing ? $this->cleanImportedText((string) $existing->summary) ?: null : null),
                        'description' => $sourceDescription ?: ($existing ? $this->cleanImportedText((string) $existing->description) ?: null : null),
                        'status' => 'published',
                        'published_at' => now(),
                        'seo_title' => Str::limit(trim((string) ($source['title'] ?? 'Untitled')).' | APF Press', 60, ''),
                        'seo_description' => Str::limit($sourceSummary, 160, '') ?: $existing?->seo_description,
                        'source_url' => $source['source_url'] ?? null,
                        'metadata_flags' => $flags,
                    ],
                );

                $summary[$existing ? 'updated' : 'created']++;
                BookDetail::query()->updateOrCreate(['catalog_item_id' => $item->id], ['publisher' => 'APF Press']);

                $sku = trim((string) ($source['sku'] ?? '')) ?: ($existingOffering?->sku ?: 'APF-WOO-'.str_pad($sourceId, 5, '0', STR_PAD_LEFT));
                $hasSourcePrice = ($source['price_amount'] ?? null) !== null;
                $isPurchasable = (bool) ($source['purchasable'] ?? false) && $effectivePrice !== null;
                $hasCurrentDigitalAsset = $existingOffering?->digitalAssets()
                    ->where('active', true)->where('is_current', true)->exists() ?? false;
                $purchaseMode = $hasSourcePrice
                    ? ($isPurchasable && $kind !== 'ebook' ? 'online' : 'inquiry')
                    : ($existingOffering?->purchase_mode ?: 'inquiry');
                if ($kind === 'ebook') {
                    $purchaseMode = $hasCurrentDigitalAsset && $existingOffering?->purchase_mode === 'online'
                        ? 'online'
                        : 'inquiry';
                }
                $offering = Offering::query()->updateOrCreate(
                    ['catalog_item_id' => $item->id, 'kind' => $kind],
                    [
                        'position' => $existingOffering?->position ?? ((int) $item->offerings()->max('position') + ($item->offerings()->exists() ? 1 : 0)),
                        'name' => $kind === 'ebook' ? 'Digital edition' : 'Print edition',
                        'sku' => $sku,
                        'price_amount' => $effectivePrice,
                        'currency' => strtoupper((string) ($source['currency'] ?? 'CAD')),
                        // Imported ebooks stay inquiry-only until staff attach a current private asset and enable online sale.
                        'purchase_mode' => $purchaseMode,
                        'tax_class' => 'books',
                        'active' => true,
                        'access_duration_days' => $kind === 'ebook' ? (int) config('apf.digital_access_days', 365) : null,
                    ],
                );

                BookEdition::query()->updateOrCreate(['offering_id' => $offering->id], [
                    'format' => $kind === 'ebook' ? 'pdf' : 'paperback',
                    'isbn_10' => $effectiveIsbn10,
                    'isbn_13' => $effectiveIsbn13,
                    'publication_date' => $effectivePublicationDate,
                    'language' => 'en',
                ]);

                $inventory = Inventory::query()->firstOrCreate(['offering_id' => $offering->id], [
                    'on_hand' => 0, 'reserved' => 0, 'track_inventory' => false, 'allow_backorder' => false,
                ]);
                if (($source['stock_quantity'] ?? null) !== null) {
                    $inventory->update(['on_hand' => max(0, (int) $source['stock_quantity']), 'track_inventory' => true]);
                }

                $this->syncCategories($item, $source['categories'] ?? []);
                $this->syncAuthor($item, $author);
                $this->syncCover($item, $source['image_url'] ?? null, $downloadImages);
            });
        }

        return $summary;
    }

    private function syncCategories(CatalogItem $item, array $categories): void
    {
        $ids = collect($categories)->filter(fn ($category) => is_array($category) && ! empty($category['name']))
            ->map(function (array $category): int {
                $slug = Str::slug((string) ($category['slug'] ?? $category['name']));

                return Category::query()->updateOrCreate(
                    ['slug' => $slug],
                    ['name' => trim((string) $category['name'])],
                )->id;
            })->all();

        $item->categories()->sync($ids);
    }

    private function cleanImportedText(string $value): string
    {
        $value = trim($value);
        $lowercase = Str::lower($value);

        foreach (['lorem ipsum', 'integer ut facilisis'] as $marker) {
            $position = strpos($lowercase, $marker);
            if ($position !== false) {
                $value = trim(substr($value, 0, $position));
                $lowercase = Str::lower($value);
            }
        }

        return $value;
    }

    private function syncAuthor(CatalogItem $item, string $author): void
    {
        if ($author === '') {
            return;
        }

        if ($item->contributors()->wherePivot('role', 'author')->exists()) {
            return;
        }

        $contributor = Contributor::query()->firstOrCreate(
            ['slug' => Str::slug($author)],
            ['name' => $author],
        );
        $item->contributors()->attach($contributor->id, ['role' => 'author', 'position' => 0]);
    }

    private function syncCover(CatalogItem $item, ?string $url, bool $download): void
    {
        $current = $item->media()->wherePivot('role', 'cover')->first();
        if (! $url || ($current && (! $download || $current->disk !== 'remote'))) {
            return;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host !== 'apfpress.com' && ! str_ends_with($host, '.apfpress.com')) {
            return;
        }

        $asset = null;
        if ($download) {
            try {
                $response = Http::timeout(15)->maxRedirects(3)->get($url);
                $mime = strtolower((string) $response->header('Content-Type'));
                if ($response->successful() && str_starts_with($mime, 'image/') && strlen($response->body()) <= 10_000_000) {
                    $extension = match (true) {
                        str_contains($mime, 'png') => 'png',
                        str_contains($mime, 'webp') => 'webp',
                        default => 'jpg',
                    };
                    $path = 'catalog/covers/'.hash('sha256', $url).'.'.$extension;
                    Storage::disk('public')->put($path, $response->body());
                    $asset = MediaAsset::query()->create([
                        'disk' => 'public', 'path' => $path, 'mime_type' => Str::before($mime, ';'),
                        'size_bytes' => strlen($response->body()), 'alt_text' => $item->title.' book cover',
                        'checksum' => hash('sha256', $response->body()), 'source_url' => $url,
                    ]);
                }
            } catch (ConnectionException) {
                // Preserve the remote source below; the admin can retry media import later.
            }
        }

        if (! $asset && $current) {
            return;
        }

        $asset ??= MediaAsset::query()->create([
            'disk' => 'remote', 'path' => $url, 'mime_type' => 'image/jpeg',
            'alt_text' => $item->title.' book cover', 'source_url' => $url,
        ]);
        DB::table('catalog_item_media')->where('catalog_item_id', $item->id)->where('role', 'cover')->delete();
        $item->media()->attach($asset->id, ['role' => 'cover', 'position' => 0]);
    }

    private function uniqueSlug(string $value, ?int $exceptId = null): string
    {
        $base = Str::slug($value) ?: 'book';
        $slug = $base;
        $suffix = 2;

        while (CatalogItem::query()->where('slug', $slug)->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function inferPublicationDate(array $source): ?string
    {
        if (! empty($source['publication_date'])) {
            return (string) $source['publication_date'];
        }

        $haystack = implode(' ', array_filter([$source['author'] ?? null, $source['summary'] ?? null]));
        if (preg_match('/\b((?:19|20)\d{2})\b/', $haystack, $matches)) {
            return $matches[1].'-01-01';
        }

        return null;
    }

    private function extractAuthor(string $content): string
    {
        $lines = preg_split('/\R+/', $content) ?: [];
        foreach (array_reverse($lines) as $line) {
            $line = trim($line);
            if (preg_match('/^(?:by|edited by)\s+(.+)$/i', $line, $matches)) {
                return trim($matches[1]);
            }
        }

        if (count($lines) >= 2) {
            $candidate = trim((string) end($lines));
            if ($candidate !== '' && mb_strlen($candidate) <= 100 && ! str_ends_with($candidate, '.')) {
                return trim((string) preg_replace('/\s*\((?:19|20)\d{2}\)\s*$/', '', $candidate));
            }
        }

        return '';
    }

    private function cleanAuthor(string $author): string
    {
        $author = preg_replace('/\s*\((?:19|20)\d{2}\)\s*$/', '', trim($author));
        $author = preg_replace('/^(?:by|edited by)\s+/i', '', (string) $author);

        return trim((string) $author);
    }

    private function isbn(string $value, int $length): ?string
    {
        $value = strtoupper((string) preg_replace('/[^0-9X]/i', '', $value));

        return strlen($value) === $length ? $value : null;
    }

    private function inferIsbn(array $source, int $length): ?string
    {
        $structured = $this->isbn((string) ($source['isbn_'.$length] ?? ''), $length);
        if ($structured) {
            return $structured;
        }

        $content = implode("\n", array_filter([
            $source['summary'] ?? null,
            $source['description'] ?? null,
        ]));
        preg_match_all('/\bISBN(?:-1[03])?\s*:?\s*([0-9X][0-9X\s-]{8,20}[0-9X])/i', $content, $matches);

        foreach ($matches[1] ?? [] as $candidate) {
            if ($isbn = $this->isbn((string) $candidate, $length)) {
                return $isbn;
            }
        }

        return null;
    }

    private function plainText(string $html): string
    {
        $html = preg_replace('/<(?:br|\/p|\/div)>/i', "\n", $html);
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = array_map(fn ($line) => trim((string) preg_replace('/[\t ]+/', ' ', $line)), preg_split('/\R/', $text) ?: []);

        return trim(implode("\n", array_filter($lines, fn ($line) => $line !== '')));
    }
}
