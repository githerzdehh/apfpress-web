<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentFinalizer;
use App\Services\Payments\PaypalGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function paypalReturn(Request $request, Payment $payment, PaypalGateway $paypal, PaymentFinalizer $finalizer): RedirectResponse
    {
        abort_unless($payment->order->user_id === $request->user()->id && $payment->provider === 'paypal', 403);
        if ($payment->status !== 'succeeded') {
            $capture = $paypal->capture($payment->provider_checkout_id);
            abort_unless(($capture['status'] ?? null) === 'COMPLETED', 422, 'PayPal has not completed this payment.');
            $captureRecord = data_get($capture, 'purchase_units.0.payments.captures.0');
            abort_unless(
                (int) round(((float) data_get($captureRecord, 'amount.value')) * 100) === $payment->amount
                    && data_get($captureRecord, 'amount.currency_code') === $payment->currency,
                422,
                'The captured payment amount did not match the order.',
            );
            $finalizer->markPaid($payment, data_get($captureRecord, 'id'), ['capture' => $capture]);
        }

        return redirect()->route('checkout.success', $payment->order);
    }
}
