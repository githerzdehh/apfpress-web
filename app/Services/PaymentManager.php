<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Services\Payments\PaypalGateway;
use App\Services\Payments\StripeGateway;
use InvalidArgumentException;

class PaymentManager
{
    public function gateway(string $provider): PaymentGateway
    {
        return match ($provider) {
            'stripe' => app(StripeGateway::class),
            'paypal' => app(PaypalGateway::class),
            default => throw new InvalidArgumentException('Unsupported payment provider.'),
        };
    }
}
