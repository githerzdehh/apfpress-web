<?php

namespace App\Contracts;

use App\Models\Order;
use App\Models\Payment;

interface PaymentGateway
{
    /** @return array{checkout_id: string, url: string, metadata?: array<string, mixed>} */
    public function createCheckout(Order $order, Payment $payment): array;
}
