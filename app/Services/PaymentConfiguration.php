<?php

namespace App\Services;

use App\Models\IntegrationSetting;

class PaymentConfiguration
{
    public function get(string $provider): array
    {
        $setting = IntegrationSetting::query()->where('provider', $provider)->first();
        $environment = $setting?->environment ?? config("services.$provider.environment", 'sandbox');
        $credentials = array_filter(array_merge(
            (array) config("services.$provider", []),
            (array) ($setting?->credentials ?? []),
        ), fn ($value) => $value !== null && $value !== '');

        return $credentials + [
            'enabled' => $setting ? $setting->enabled : (bool) ($credentials['secret'] ?? $credentials['client_secret'] ?? false),
            'environment' => $environment,
        ];
    }
}
