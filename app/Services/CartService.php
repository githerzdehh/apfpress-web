<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Offering;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function current(Request $request): Cart
    {
        $cart = Cart::query()->whereKey($request->session()->get('cart_id'))->where('status', 'active')->first();

        if (! $cart) {
            $cart = Cart::query()->create([
                'user_id' => $request->user()?->id,
                'status' => 'active',
                'currency' => config('apf.currency'),
                'expires_at' => now()->addDays(30),
            ]);
            $request->session()->put('cart_id', $cart->id);
        } elseif ($request->user() && $cart->user_id !== $request->user()->id) {
            $cart->update(['user_id' => $request->user()->id]);
        }

        return $cart;
    }

    public function add(Cart $cart, Offering $offering, int $quantity): Cart
    {
        $offering->loadMissing('inventory');
        if (! $offering->isAvailable()) {
            throw ValidationException::withMessages(['offering_id' => 'This edition is not currently available for online purchase.']);
        }

        $item = $cart->items()->firstOrNew(['offering_id' => $offering->id]);
        $item->quantity = min(25, ($item->exists ? $item->quantity : 0) + $quantity);
        $item->save();

        return $cart->fresh($this->relations());
    }

    public function update(Cart $cart, int $cartItemId, int $quantity): Cart
    {
        $item = $cart->items()->whereKey($cartItemId)->firstOrFail();
        $quantity > 0 ? $item->update(['quantity' => min(25, $quantity)]) : $item->delete();

        return $cart->fresh($this->relations());
    }

    public function present(Cart $cart): array
    {
        $cart->loadMissing($this->relations());
        $items = $cart->items->map(fn ($item) => [
            'id' => $item->id,
            'quantity' => $item->quantity,
            'line_amount' => $item->quantity * $item->offering->price_amount,
            'offering' => [
                'id' => $item->offering->id,
                'name' => $item->offering->name,
                'kind' => $item->offering->kind,
                'sku' => $item->offering->sku,
                'price_amount' => $item->offering->price_amount,
                'title' => $item->offering->catalogItem->title,
                'slug' => $item->offering->catalogItem->slug,
                'cover' => $item->offering->catalogItem->cover?->url,
            ],
        ]);

        return [
            'id' => $cart->id,
            'currency' => $cart->currency,
            'items' => $items,
            'item_count' => $items->sum('quantity'),
            'subtotal_amount' => $items->sum('line_amount'),
        ];
    }

    private function relations(): array
    {
        return ['items.offering.inventory', 'items.offering.catalogItem.media'];
    }
}
