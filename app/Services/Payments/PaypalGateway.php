<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentConfiguration;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Illuminate\Support\Facades\URL;

class PaypalGateway implements PaymentGateway
{
    public function __construct(private readonly PaymentConfiguration $configuration) {}

    public function createCheckout(Order $order, Payment $payment): array
    {
        $config = $this->configuration->get('paypal');
        $token = $this->accessToken($config);
        $response = Http::withToken($token)->acceptJson()->withHeaders([
            'PayPal-Request-Id' => 'apf-checkout-'.$payment->id,
        ])->post($this->baseUrl($config).'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $order->id,
                'custom_id' => (string) $payment->id,
                'description' => 'APF Press order '.$order->number,
                'amount' => ['currency_code' => $order->currency, 'value' => number_format($order->total_amount / 100, 2, '.', '')],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'brand_name' => 'APF Press', 'shipping_preference' => 'NO_SHIPPING',
                'return_url' => URL::temporarySignedRoute('paypal.return', now()->addHours(2), ['payment' => $payment]),
                'cancel_url' => URL::temporarySignedRoute('checkout.cancel', now()->addHours(2), ['order' => $order]),
            ]]],
        ])->throw()->json();

        $approval = collect($response['links'] ?? [])->firstWhere('rel', 'payer-action');
        $approval ??= collect($response['links'] ?? [])->firstWhere('rel', 'approve');
        if (empty($response['id']) || empty($approval['href'])) {
            throw new RuntimeException('PayPal did not return a checkout link.');
        }

        return ['checkout_id' => $response['id'], 'url' => $approval['href'], 'metadata' => ['paypal_order' => $response]];
    }

    public function capture(string $checkoutId): array
    {
        $config = $this->configuration->get('paypal');

        return Http::withToken($this->accessToken($config))->acceptJson()
            ->withHeaders(['PayPal-Request-Id' => 'apf-capture-'.$checkoutId])
            ->withBody('{}', 'application/json')
            ->post($this->baseUrl($config).'/v2/checkout/orders/'.$checkoutId.'/capture')->throw()->json();
    }

    public function verifyWebhook(array $event, array $headers): bool
    {
        $config = $this->configuration->get('paypal');
        if (empty($config['webhook_id'])) {
            return false;
        }

        $response = Http::withToken($this->accessToken($config))->acceptJson()
            ->post($this->baseUrl($config).'/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $headers['paypal-auth-algo'][0] ?? '',
                'cert_url' => $headers['paypal-cert-url'][0] ?? '',
                'transmission_id' => $headers['paypal-transmission-id'][0] ?? '',
                'transmission_sig' => $headers['paypal-transmission-sig'][0] ?? '',
                'transmission_time' => $headers['paypal-transmission-time'][0] ?? '',
                'webhook_id' => $config['webhook_id'],
                'webhook_event' => $event,
            ])->throw()->json();

        return ($response['verification_status'] ?? null) === 'SUCCESS';
    }

    private function accessToken(array $config): string
    {
        if (! $config['enabled'] || empty($config['client_id']) || empty($config['client_secret'])) {
            throw new RuntimeException('PayPal checkout is not configured.');
        }

        return (string) Http::asForm()->withBasicAuth($config['client_id'], $config['client_secret'])
            ->post($this->baseUrl($config).'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
            ->throw()->json('access_token');
    }

    private function baseUrl(array $config): string
    {
        return ($config['environment'] ?? 'sandbox') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }
}
