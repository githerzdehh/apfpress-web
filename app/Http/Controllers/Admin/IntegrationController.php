<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegrationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IntegrationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(IntegrationSetting::query()->get()->map(fn ($setting) => [
            'provider' => $setting->provider, 'environment' => $setting->environment,
            'enabled' => $setting->enabled, 'health_status' => $setting->health_status,
            'health_message' => $setting->health_message, 'last_tested_at' => $setting->last_tested_at,
            'configured' => ! empty($setting->credentials),
        ]));
    }

    public function update(Request $request, string $provider): JsonResponse
    {
        abort_unless(in_array($provider, ['stripe', 'paypal'], true), 404);
        $data = $request->validate([
            'environment' => ['required', Rule::in(['sandbox', 'live'])], 'enabled' => ['required', 'boolean'],
            'credentials' => ['nullable', 'array'], 'credentials.*' => ['nullable', 'string', 'max:1000'],
        ]);
        $setting = IntegrationSetting::query()->firstOrCreate(['provider' => $provider]);
        $credentials = array_filter($data['credentials'] ?? [], fn ($value) => $value !== null && $value !== '');
        $setting->update([
            'environment' => $data['environment'], 'enabled' => $data['enabled'],
            'credentials' => array_merge($setting->credentials ?? [], $credentials),
            'health_status' => 'untested', 'health_message' => null,
        ]);

        return response()->json(['provider' => $provider, 'saved' => true, 'configured' => ! empty($setting->credentials)]);
    }
}
