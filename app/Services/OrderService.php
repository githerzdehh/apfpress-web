<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function create(Cart $cart, User $user, array $address, array $quote, ?string $note = null): Order
    {
        return DB::transaction(function () use ($cart, $user, $address, $quote, $note): Order {
            $cart->loadMissing('items.offering.catalogItem');
            $order = Order::query()->create([
                'number' => 'APF-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
                'cart_id' => $cart->id,
                'user_id' => $user->id,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'fulfillment_status' => $quote['requires_shipping'] ? 'unfulfilled' : 'not_required',
                'email' => $user->email,
                'currency' => $cart->currency,
                'subtotal_amount' => $quote['subtotal_amount'],
                'shipping_amount' => $quote['shipping_amount'],
                'tax_amount' => $quote['tax_amount'],
                'total_amount' => $quote['total_amount'],
                'billing_address' => $address,
                'shipping_address' => $quote['requires_shipping'] ? $address : null,
                'shipping_method' => $quote['shipping_method'],
                'customer_note' => $note,
            ]);

            foreach ($cart->items as $cartItem) {
                $offering = $cartItem->offering;
                if (! $offering?->isAvailable()) {
                    throw ValidationException::withMessages(['cart' => 'An edition in your cart is no longer available. Remove it or choose another edition.']);
                }
                $inventory = $offering->inventory()->lockForUpdate()->first();
                if ($inventory?->track_inventory && ! $inventory->allow_backorder && ($inventory->on_hand - $inventory->reserved) < $cartItem->quantity) {
                    throw ValidationException::withMessages(['cart' => 'A title in your cart no longer has enough stock. Please update the quantity.']);
                }
                if ($inventory?->track_inventory) {
                    $inventory->increment('reserved', $cartItem->quantity);
                    DB::table('inventory_movements')->insert([
                        'offering_id' => $offering->id, 'user_id' => $user->id,
                        'quantity_delta' => 0, 'reason' => 'reservation',
                        'reference_type' => Order::class, 'reference_id' => $order->id,
                        'note' => 'Reserved '.$cartItem->quantity.' unit(s) for pending payment.',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $order->items()->create([
                    'offering_id' => $offering->id,
                    'sku' => $offering->sku,
                    'name' => $offering->catalogItem->title.' — '.$offering->name,
                    'kind' => $offering->kind,
                    'quantity' => $cartItem->quantity,
                    'unit_amount' => $offering->price_amount,
                    'total_amount' => $cartItem->quantity * $offering->price_amount,
                    'metadata' => ['catalog_item_id' => $offering->catalog_item_id],
                ]);
            }

            return $order->load('items');
        });
    }
}
