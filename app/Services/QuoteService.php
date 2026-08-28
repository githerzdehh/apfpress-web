<?php

namespace App\Services;

use App\Models\Cart;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteService
{
    public function quote(Cart $cart, string $country, ?string $region = null): array
    {
        $cart->loadMissing(['items.offering.inventory', 'items.offering.digitalAssets']);
        foreach ($cart->items as $item) {
            if (! $item->offering?->isAvailable()) {
                throw ValidationException::withMessages(['cart' => 'An edition in your cart is no longer available. Remove it or choose another edition.']);
            }
        }
        $subtotal = $cart->items->sum(fn ($item) => $item->quantity * $item->offering->price_amount);
        $hasPhysical = $cart->items->contains(fn ($item) => in_array($item->offering->kind, ['print_book', 'physical_product'], true));
        $shipping = 0;
        $shippingMethod = null;

        if ($hasPhysical) {
            $zone = DB::table('shipping_zones')->where('active', true)->where('country', $country)
                ->where(function ($query) use ($region): void {
                    $query->whereNull('regions');
                    if ($region) {
                        $query->orWhereJsonContains('regions', $region);
                    }
                })->orderBy('priority')->first();
            if (! $zone) {
                throw ValidationException::withMessages(['country' => 'Shipping is not available for this destination.']);
            }

            $rule = DB::table('shipping_rules')->where('shipping_zone_id', $zone->id)->where('active', true)
                ->where(fn ($query) => $query->whereNull('minimum_order_amount')->orWhere('minimum_order_amount', '<=', $subtotal))
                ->where(fn ($query) => $query->whereNull('maximum_order_amount')->orWhere('maximum_order_amount', '>=', $subtotal))
                ->orderBy('rate_amount')->first();
            if (! $rule) {
                throw ValidationException::withMessages(['country' => 'No shipping rate is configured for this order.']);
            }
            $shipping = $rule->free_above_amount !== null && $subtotal >= $rule->free_above_amount ? 0 : $rule->rate_amount;
            $shippingMethod = $rule->name;
        }

        $tax = 0;
        $taxLines = [];
        foreach ($cart->items as $item) {
            $rule = $this->taxRule($country, $region, $item->offering->tax_class);
            if (! $rule) {
                continue;
            }
            $amount = (int) round(($item->quantity * $item->offering->price_amount) * $rule->rate_basis_points / 10_000);
            $tax += $amount;
            $taxLines[$rule->label] = ($taxLines[$rule->label] ?? 0) + $amount;
        }

        $shippingTaxRule = $this->taxRule($country, $region, 'shipping');
        if ($shippingTaxRule?->shipping_taxable && $shipping > 0) {
            $amount = (int) round($shipping * $shippingTaxRule->rate_basis_points / 10_000);
            $tax += $amount;
            $taxLines[$shippingTaxRule->label] = ($taxLines[$shippingTaxRule->label] ?? 0) + $amount;
        }

        return [
            'currency' => $cart->currency,
            'subtotal_amount' => $subtotal,
            'shipping_amount' => $shipping,
            'shipping_method' => $shippingMethod,
            'tax_amount' => $tax,
            'tax_lines' => $taxLines,
            'total_amount' => $subtotal + $shipping + $tax,
            'requires_shipping' => $hasPhysical,
        ];
    }

    private function taxRule(string $country, ?string $region, string $taxClass): ?object
    {
        return DB::table('tax_rules')->where('active', true)->where('nexus_enabled', true)
            ->where('country', $country)->where('tax_class', $taxClass)
            ->where('effective_from', '<=', now()->toDateString())
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', now()->toDateString()))
            ->where(fn ($query) => $query->whereNull('region')->orWhere('region', $region))
            ->orderByRaw('region IS NULL')->first();
    }
}
