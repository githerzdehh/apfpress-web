<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\DigitalAsset;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentFinalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentFinalizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_payment_updates_inventory_and_grants_expiring_download_access(): void
    {
        $user = User::factory()->create();
        $item = CatalogItem::query()->create(['type' => 'book', 'slug' => 'ebook', 'title' => 'An E-book', 'status' => 'published']);
        $offering = $item->offerings()->create(['kind' => 'ebook', 'name' => 'PDF', 'sku' => 'EB-001', 'price_amount' => 999, 'currency' => 'CAD', 'purchase_mode' => 'online', 'access_duration_days' => 30]);
        Inventory::query()->create(['offering_id' => $offering->id, 'on_hand' => 10, 'reserved' => 1, 'track_inventory' => true]);
        $asset = DigitalAsset::query()->create(['offering_id' => $offering->id, 'path' => 'digital/book.pdf', 'file_name' => 'book.pdf', 'mime_type' => 'application/pdf']);
        $order = Order::query()->create(['number' => 'APF-TEST-1', 'user_id' => $user->id, 'email' => $user->email, 'currency' => 'CAD', 'subtotal_amount' => 999, 'total_amount' => 999]);
        $orderItem = $order->items()->create(['offering_id' => $offering->id, 'sku' => 'EB-001', 'name' => 'An E-book — PDF', 'kind' => 'ebook', 'quantity' => 1, 'unit_amount' => 999, 'total_amount' => 999]);
        $payment = Payment::query()->create(['order_id' => $order->id, 'provider' => 'stripe', 'status' => 'pending', 'amount' => 999, 'currency' => 'CAD']);

        app(PaymentFinalizer::class)->markPaid($payment, 'pi_test');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid']);
        $this->assertDatabaseHas('inventories', ['offering_id' => $offering->id, 'on_hand' => 9, 'reserved' => 0]);
        $this->assertDatabaseHas('digital_entitlements', ['user_id' => $user->id, 'order_item_id' => $orderItem->id, 'digital_asset_id' => $asset->id]);
        $this->assertTrue($user->fresh()->digitalEntitlements()->first()->expires_at->isFuture());
    }
}
