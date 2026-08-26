<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentConfiguration;
use App\Services\PaymentFinalizer;
use App\Services\Payments\PaypalGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PaymentWebhookController extends Controller
{
    public function stripe(Request $request, PaymentConfiguration $configuration, PaymentFinalizer $finalizer): Response
    {
        $config = $configuration->get('stripe');
        abort_if(empty($config['webhook_secret']), 503, 'Stripe webhook is not configured.');
        $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $config['webhook_secret']);
        $record = $this->startEvent('stripe', $event->id, $event->type, $event->toArray());
        if (! $record) {
            return response('Already processed', 200);
        }

        try {
            if ($event->type === 'checkout.session.completed') {
                $session = $event->data->object;
                $payment = Payment::query()->findOrFail((int) $session->metadata->payment_id);
                abort_unless($payment->provider_checkout_id === $session->id && $payment->amount === $session->amount_total, 422, 'Payment mismatch.');
                $finalizer->markPaid($payment, $session->payment_intent, ['stripe_session' => $session->toArray()]);
            }
            $this->finishEvent($record, 'processed');
        } catch (Throwable $exception) {
            $this->finishEvent($record, 'failed', $exception->getMessage());
            throw $exception;
        }

        return response('Accepted', 200);
    }

    public function paypal(Request $request, PaypalGateway $paypal, PaymentFinalizer $finalizer): Response
    {
        $event = $request->json()->all();
        abort_unless($paypal->verifyWebhook($event, $request->headers->all()), 401, 'Invalid PayPal signature.');
        $record = $this->startEvent('paypal', (string) ($event['id'] ?? ''), (string) ($event['event_type'] ?? ''), $event);
        if (! $record) {
            return response('Already processed', 200);
        }

        try {
            if (($event['event_type'] ?? null) === 'PAYMENT.CAPTURE.COMPLETED') {
                $checkoutId = data_get($event, 'resource.supplementary_data.related_ids.order_id');
                $payment = Payment::query()->where('provider', 'paypal')->where('provider_checkout_id', $checkoutId)->firstOrFail();
                $amount = (int) round(((float) data_get($event, 'resource.amount.value')) * 100);
                abort_unless($amount === $payment->amount && data_get($event, 'resource.amount.currency_code') === $payment->currency, 422, 'Payment mismatch.');
                $finalizer->markPaid($payment, data_get($event, 'resource.id'), ['paypal_event' => $event]);
            }
            $this->finishEvent($record, 'processed');
        } catch (Throwable $exception) {
            $this->finishEvent($record, 'failed', $exception->getMessage());
            throw $exception;
        }

        return response('Accepted', 200);
    }

    private function startEvent(string $provider, string $id, string $type, array $payload): ?int
    {
        if (DB::table('payment_events')->where(['provider' => $provider, 'provider_event_id' => $id])->exists()) {
            return null;
        }

        return DB::table('payment_events')->insertGetId([
            'provider' => $provider, 'provider_event_id' => $id, 'event_type' => $type,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'status' => 'received',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function finishEvent(int $id, string $status, ?string $error = null): void
    {
        DB::table('payment_events')->where('id', $id)->update([
            'status' => $status, 'error' => $error, 'processed_at' => now(), 'updated_at' => now(),
        ]);
    }
}
