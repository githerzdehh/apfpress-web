<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentConfiguration;
use RuntimeException;
use Stripe\StripeClient;
use Illuminate\Support\Facades\URL;

class StripeGateway implements PaymentGateway
{
    public function __construct(private readonly PaymentConfiguration $configuration) {}

    public function createCheckout(Order $order, Payment $payment): array
    {
        $config = $this->configuration->get('stripe');
        if (! $config['enabled'] || empty($config['secret'])) {
            throw new RuntimeException('Stripe checkout is not configured.');
        }

        $lineItems = $order->items->map(fn ($item) => [
            'quantity' => $item->quantity,
            'price_data' => [
                'currency' => strtolower($order->currency),
                'unit_amount' => $item->unit_amount,
                'product_data' => ['name' => $item->name, 'metadata' => ['sku' => $item->sku ?? '']],
            ],
        ])->values()->all();
        if ($order->shipping_amount > 0) {
            $lineItems[] = ['quantity' => 1, 'price_data' => [
                'currency' => strtolower($order->currency), 'unit_amount' => $order->shipping_amount,
                'product_data' => ['name' => $order->shipping_method ?: 'Shipping'],
            ]];
        }
        if ($order->tax_amount > 0) {
            $lineItems[] = ['quantity' => 1, 'price_data' => [
                'currency' => strtolower($order->currency), 'unit_amount' => $order->tax_amount,
                'product_data' => ['name' => 'Applicable taxes'],
            ]];
        }

        $session = (new StripeClient($config['secret']))->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $order->email,
            'client_reference_id' => (string) $order->id,
            'line_items' => $lineItems,
            'success_url' => route('checkout.success', ['order' => $order]).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => URL::temporarySignedRoute('checkout.cancel', now()->addHours(2), ['order' => $order]),
            'metadata' => ['order_id' => (string) $order->id, 'payment_id' => (string) $payment->id],
        ], ['idempotency_key' => 'apf-checkout-'.$payment->id]);

        return ['checkout_id' => $session->id, 'url' => $session->url];
    }
}
