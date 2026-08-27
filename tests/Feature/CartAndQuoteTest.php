<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Inventory;
use App\Models\Offering;
use App\Services\QuoteService;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CartAndQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_prices_are_derived_from_the_database(): void
    {
        $offering = $this->offering(2040);

        $this->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/cart/items', ['offering_id' => $offering->id, 'quantity' => 2])
            ->assertCreated()->assertJsonPath('subtotal_amount', 4080)->assertJsonPath('item_count', 2);

        $this->getJson('/api/cart')->assertOk()->assertJsonPath('items.0.offering.price_amount', 2040);
    }

    public function test_quote_uses_admin_shipping_rules_and_only_enabled_tax_nexus(): void
    {
        $this->seed(ContentSeeder::class);
        $offering = $this->offering(2000);
        $cart = \App\Models\Cart::query()->create(['currency' => 'CAD', 'status' => 'active']);
        $cart->items()->create(['offering_id' => $offering->id, 'quantity' => 2]);
        DB::table('tax_rules')->insert([
            'name' => 'Test HST', 'country' => 'CA', 'region' => 'ON', 'tax_class' => 'books', 'label' => 'HST',
            'rate_basis_points' => 1300, 'shipping_taxable' => false, 'nexus_enabled' => true,
            'effective_from' => now()->subDay()->toDateString(), 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $quote = app(QuoteService::class)->quote($cart, 'CA', 'ON');
        $this->assertSame(4000, $quote['subtotal_amount']);
        $this->assertSame(1200, $quote['shipping_amount']);
        $this->assertSame(520, $quote['tax_amount']);
        $this->assertSame(5720, $quote['total_amount']);
    }

    private function offering(int $price): Offering
    {
        $item = CatalogItem::query()->create(['type' => 'book', 'slug' => 'test-book-'.uniqid(), 'title' => 'Test Book', 'status' => 'published', 'published_at' => now()]);
        $offering = $item->offerings()->create(['kind' => 'print_book', 'name' => 'Print edition', 'sku' => 'SKU-'.uniqid(), 'price_amount' => $price, 'currency' => 'CAD', 'purchase_mode' => 'online', 'tax_class' => 'books']);
        Inventory::query()->create(['offering_id' => $offering->id, 'track_inventory' => false]);

        return $offering;
    }
}
