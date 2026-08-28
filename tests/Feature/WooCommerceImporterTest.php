<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\DigitalAsset;
use App\Models\Offering;
use App\Models\Contributor;
use App\Services\WooCommerceImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WooCommerceImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_structured_catalog_records_and_flags_missing_metadata(): void
    {
        $summary = app(WooCommerceImporter::class)->import([[
            'source_id' => '401', 'slug' => 'resistance-and-empowerment',
            'title' => 'Resistance and Empowerment', 'summary' => 'A timely collection.',
            'description' => '', 'author' => '', 'kind' => 'print_book',
            'price_amount' => 2499, 'currency' => 'CAD', 'purchasable' => true,
            'stock_quantity' => null, 'categories' => [['name' => 'Race', 'slug' => 'race']],
            'image_url' => null, 'source_url' => 'https://apfpress.com/product/resistance-and-empowerment/',
        ]]);

        $item = CatalogItem::query()->with(['categories', 'offerings.bookEdition', 'offerings.inventory'])->firstOrFail();
        $this->assertSame(1, $summary['created']);
        $this->assertSame('APF-WOO-00401', $item->offerings->first()->sku);
        $this->assertSame('online', $item->offerings->first()->purchase_mode);
        $this->assertContains('missing_author', $item->metadata_flags);
        $this->assertContains('missing_isbn', $item->metadata_flags);
        $this->assertContains('missing_stock_count', $item->metadata_flags);
        $this->assertSame('Race', $item->categories->first()->name);
    }

    public function test_imported_ebooks_require_a_private_asset_before_online_sale(): void
    {
        $source = [[
            'source_id' => '100', 'slug' => 'digital-book', 'title' => 'Digital Book',
            'author' => 'A. Scholar', 'kind' => 'ebook', 'price_amount' => 999,
            'currency' => 'CAD', 'purchasable' => true, 'in_stock' => true, 'categories' => [],
        ]];
        app(WooCommerceImporter::class)->import($source);

        $offering = Offering::query()->firstOrFail();
        $this->assertSame('inquiry', $offering->purchase_mode);

        $asset = DigitalAsset::query()->create([
            'offering_id' => $offering->id,
            'disk' => 'local',
            'path' => 'digital/test/edition.pdf',
            'file_name' => 'edition.pdf',
            'mime_type' => 'application/pdf',
            'version' => 1,
            'active' => true,
            'is_current' => false,
        ]);
        $offering->update(['purchase_mode' => 'online']);

        app(WooCommerceImporter::class)->import($source);
        $this->assertSame('inquiry', $offering->fresh()->purchase_mode);

        $asset->update(['is_current' => true]);
        $offering->update(['purchase_mode' => 'online']);
        app(WooCommerceImporter::class)->import($source);

        $this->assertSame('online', $offering->fresh()->purchase_mode);
    }

    public function test_it_removes_legacy_placeholder_copy_during_import(): void
    {
        app(WooCommerceImporter::class)->import([[
            'source_id' => '402', 'slug' => 'clean-copy', 'title' => 'Clean Copy',
            'summary' => "Author: Test Scholar\nISBN: 978-1-894490-25-2\n\nLorem ipsum dolor sit amet.",
            'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
            'author' => 'Test Scholar', 'kind' => 'print_book', 'price_amount' => null,
            'currency' => 'CAD', 'purchasable' => false, 'categories' => [],
        ]]);

        $item = CatalogItem::query()->firstOrFail();

        $this->assertSame("Author: Test Scholar\nISBN: 978-1-894490-25-2", $item->summary);
        $this->assertNull($item->description);
        $this->assertSame('9781894490252', $item->offerings()->firstOrFail()->bookEdition()->firstOrFail()->isbn_13);
        $this->assertStringNotContainsString('Lorem ipsum', $item->seo_description ?? '');
    }

    public function test_reimport_does_not_erase_editorially_curated_metadata(): void
    {
        $source = [
            'source_id' => '55', 'slug' => 'legacy-title', 'title' => 'Legacy Title',
            'summary' => '', 'description' => '', 'author' => '', 'kind' => 'print_book',
            'price_amount' => null, 'currency' => 'CAD', 'purchasable' => false, 'categories' => [],
        ];
        app(WooCommerceImporter::class)->import([$source]);
        $item = CatalogItem::query()->firstOrFail();
        $author = Contributor::query()->create(['name' => 'Curated Author', 'slug' => 'curated-author']);
        $item->contributors()->attach($author->id, ['role' => 'author']);
        $offering = $item->offerings()->firstOrFail();
        $offering->update(['price_amount' => 3200, 'purchase_mode' => 'online']);
        $offering->bookEdition()->update(['isbn_13' => '9781234567897', 'publication_date' => '2025-03-01']);
        $offering->inventory()->update(['on_hand' => 14, 'track_inventory' => true]);

        app(WooCommerceImporter::class)->import([$source]);
        $item->refresh()->load(['contributors', 'offerings.bookEdition', 'offerings.inventory']);
        $offering = $item->offerings->first();

        $this->assertSame('Curated Author', $item->contributors->first()->name);
        $this->assertSame(3200, $offering->price_amount);
        $this->assertSame('9781234567897', $offering->bookEdition->isbn_13);
        $this->assertSame(14, $offering->inventory->on_hand);
        $this->assertNotContains('missing_author', $item->metadata_flags);
        $this->assertNotContains('missing_isbn', $item->metadata_flags);
        $this->assertNotContains('missing_stock_count', $item->metadata_flags);
    }
}
