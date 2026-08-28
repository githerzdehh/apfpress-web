<?php

namespace Tests\Feature;

use App\Models\BookEdition;
use App\Models\Cart;
use App\Models\CatalogItem;
use App\Models\Category;
use App\Models\Contributor;
use App\Models\Inventory;
use App\Models\Offering;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'owner', 'status' => 'active']);
    }

    public function test_admin_resource_returns_date_only_values_and_paginated_metadata(): void
    {
        [$item] = $this->catalogueRecord(['publication_date' => '2026-08-28']);

        $this->actingAs($this->owner)->getJson("/admin/api/catalog/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data.offerings.0.edition.publication_date', '2026-08-28');

        $this->actingAs($this->owner)->getJson('/admin/api/catalog')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $item->id);
    }

    public function test_catalogue_endpoints_remain_limited_to_editorial_roles(): void
    {
        $fulfillment = User::factory()->create(['role' => 'fulfillment', 'status' => 'active']);
        $editor = User::factory()->create(['role' => 'editor', 'status' => 'active']);

        $this->actingAs($fulfillment)->getJson('/admin/api/catalog/options')->assertForbidden();
        $this->actingAs($editor)->getJson('/admin/api/catalog/options')->assertOk();
    }

    public function test_update_synchronizes_normalized_relationships_without_duplicating_an_edition(): void
    {
        [$item, $offering] = $this->catalogueRecord();
        $oldAuthor = Contributor::query()->create(['name' => 'Old Author', 'slug' => 'old-author']);
        $item->contributors()->attach($oldAuthor->id, ['role' => 'author', 'position' => 0]);

        $payload = $this->payload($offering->id);
        $payload['contributors'] = [
            ['name' => 'New Author', 'role' => 'author', 'position' => 0],
            ['name' => 'Careful Editor', 'role' => 'editor', 'position' => 1],
        ];
        $payload['categories'] = [['name' => 'Public Policy']];
        $payload['offerings'][0]['kind'] = 'ebook';
        $payload['offerings'][0]['name'] = 'Accessible PDF';
        $payload['offerings'][0]['edition']['format'] = 'pdf';

        $this->actingAs($this->owner)->putJson("/admin/api/catalog/{$item->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.offerings.0.id', $offering->id)
            ->assertJsonPath('data.offerings.0.kind', 'ebook')
            ->assertJsonPath('data.contributors.1.role', 'editor')
            ->assertJsonPath('data.categories.0.name', 'Public Policy');

        $this->assertSame(1, $item->offerings()->count());
        $this->assertFalse($item->contributors()->whereKey($oldAuthor->id)->exists());
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $item->id, 'action' => 'catalog.updated']);
    }

    public function test_validation_returns_every_nested_field_error_and_preserves_warnings_as_non_blocking(): void
    {
        $payload = $this->payload();
        $payload['title'] = '';
        $payload['offerings'][0]['purchase_mode'] = 'online';
        $payload['offerings'][0]['sku'] = '';
        $payload['offerings'][0]['price_amount'] = null;
        $payload['offerings'][0]['edition']['isbn_13'] = '9781234567890';

        $this->actingAs($this->owner)->postJson('/admin/api/catalog', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title', 'offerings.0.sku', 'offerings.0.price_amount', 'offerings.0.edition.isbn_13',
            ]);

        $payload = $this->payload();
        $payload['contributors'] = [];
        $payload['offerings'][0]['edition']['isbn_13'] = null;
        $payload['offerings'][0]['edition']['publication_date'] = null;
        $payload['offerings'][0]['edition']['page_count'] = null;
        $payload['offerings'][0]['price_amount'] = null;

        $response = $this->actingAs($this->owner)->postJson('/admin/api/catalog', $payload)->assertCreated();
        $response->assertJsonPath('data.metadata_flags.0', 'missing_author');
        $this->assertNotEmpty($response->json('data.warnings'));
    }

    public function test_existing_editions_are_deactivated_instead_of_deleted_and_hidden_publicly(): void
    {
        $this->withoutVite();
        [$item, $first] = $this->catalogueRecord();
        $second = $item->offerings()->create([
            'position' => 1, 'kind' => 'ebook', 'name' => 'Hidden EPUB', 'sku' => 'HIDDEN-EPUB',
            'price_amount' => 1200, 'currency' => 'CAD', 'purchase_mode' => 'inquiry', 'tax_class' => 'books', 'active' => true,
        ]);
        BookEdition::query()->create(['offering_id' => $second->id, 'format' => 'epub', 'language' => 'en']);
        Inventory::query()->create(['offering_id' => $second->id]);

        $payload = $this->payload($first->id);
        $payload['offerings'][] = $this->editionPayload($second->id, false, 'ebook', 'Hidden EPUB', 'epub');
        $this->actingAs($this->owner)->putJson("/admin/api/catalog/{$item->id}", $payload)->assertOk();

        $this->assertDatabaseHas('offerings', ['id' => $second->id, 'active' => false]);
        $this->get("/books/{$item->slug}")->assertOk()->assertDontSee('Hidden EPUB');

        $cart = Cart::query()->create(['currency' => 'CAD', 'status' => 'active']);
        $cart->items()->create(['offering_id' => $second->id, 'quantity' => 1]);
        $this->expectException(ValidationException::class);
        app(QuoteService::class)->quote($cart, 'CA', 'ON');
    }

    public function test_digital_uploads_create_versions_without_overwriting_prior_assets(): void
    {
        Storage::fake('local');
        [$item, $offering] = $this->catalogueRecord();
        $offering->update(['kind' => 'ebook']);
        $offering->bookEdition()->update(['format' => 'pdf']);

        $this->actingAs($this->owner)->post("/admin/api/offerings/{$offering->id}/digital-asset", [
            'file' => UploadedFile::fake()->create('edition-one.pdf', 50, 'application/pdf'),
            'access_duration_days' => 365,
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('version', 1);
        $first = $offering->digitalAssets()->firstOrFail();

        $this->actingAs($this->owner)->post("/admin/api/offerings/{$offering->id}/digital-asset", [
            'file' => UploadedFile::fake()->create('edition-two.pdf', 55, 'application/pdf'),
            'access_duration_days' => 365,
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('version', 2);

        $this->assertDatabaseCount('digital_assets', 2);
        $this->assertDatabaseHas('digital_assets', ['id' => $first->id, 'active' => true, 'is_current' => false]);
        $this->assertDatabaseHas('digital_assets', ['offering_id' => $offering->id, 'version' => 2, 'active' => true, 'is_current' => true]);
        $this->assertSame('inquiry', $offering->fresh()->purchase_mode);
    }

    /** @return array{CatalogItem, Offering} */
    private function catalogueRecord(array $editionOverrides = []): array
    {
        $item = CatalogItem::query()->create([
            'type' => 'book', 'slug' => 'catalogue-test-'.uniqid(), 'title' => 'Catalogue Test',
            'status' => 'published', 'published_at' => now(), 'featured' => false,
        ]);
        $item->bookDetails()->create(['publisher' => 'APF Press', 'original_language' => 'en']);
        $offering = $item->offerings()->create([
            'position' => 0, 'kind' => 'print_book', 'name' => 'Print edition', 'sku' => 'SKU-'.uniqid(),
            'price_amount' => 2499, 'currency' => 'CAD', 'purchase_mode' => 'inquiry', 'tax_class' => 'books', 'active' => true,
        ]);
        BookEdition::query()->create(array_merge([
            'offering_id' => $offering->id, 'format' => 'paperback', 'isbn_13' => '9781234567897',
            'publication_date' => '2025-03-01', 'page_count' => 220, 'language' => 'en',
        ], $editionOverrides));
        Inventory::query()->create(['offering_id' => $offering->id, 'on_hand' => 8, 'low_stock_threshold' => 2]);

        return [$item, $offering];
    }

    private function payload(?int $offeringId = null): array
    {
        return [
            'type' => 'book', 'title' => 'A Complete Catalogue Title', 'subtitle' => '', 'slug' => '',
            'summary' => 'A useful summary.', 'description' => 'A complete description.', 'status' => 'published', 'featured' => false,
            'seo_title' => '', 'seo_description' => '',
            'book_details' => ['publisher' => 'APF Press', 'imprint' => '', 'original_language' => 'en'],
            'contributors' => [['name' => 'Example Author', 'role' => 'author', 'position' => 0]],
            'categories' => [],
            'offerings' => [$this->editionPayload($offeringId)],
        ];
    }

    private function editionPayload(?int $id = null, bool $active = true, string $kind = 'print_book', string $name = 'Print edition', string $format = 'paperback'): array
    {
        return [
            'id' => $id, 'active' => $active, 'position' => 0, 'kind' => $kind, 'name' => $name,
            'sku' => $id ? Offering::query()->find($id)?->sku : 'NEW-'.uniqid(), 'price_amount' => 2499,
            'purchase_mode' => 'inquiry', 'access_duration_days' => $kind === 'ebook' ? 365 : null,
            'edition' => [
                'format' => $format, 'edition_label' => '', 'isbn_10' => null,
                'isbn_13' => $id ? Offering::query()->find($id)?->bookEdition?->isbn_13 : '9781234567897',
                'publication_date' => '2025-03-01', 'page_count' => 220, 'language' => 'en',
                'weight_grams' => null, 'width_mm' => null, 'height_mm' => null, 'depth_mm' => null,
            ],
            'inventory' => ['on_hand' => 8, 'low_stock_threshold' => 2, 'track_inventory' => false, 'allow_backorder' => false],
        ];
    }
}
