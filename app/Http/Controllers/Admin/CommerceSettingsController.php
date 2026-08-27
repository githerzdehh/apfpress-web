<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommerceSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'shipping_zones' => DB::table('shipping_zones')->orderBy('priority')->get()->map(function ($zone) {
                $zone->rules = DB::table('shipping_rules')->where('shipping_zone_id', $zone->id)->get();

                return $zone;
            }),
            'tax_rules' => DB::table('tax_rules')->orderBy('country')->orderBy('region')->get(),
        ]);
    }

    public function shipping(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:shipping_rules,id'], 'shipping_zone_id' => ['required', 'integer', 'exists:shipping_zones,id'],
            'name' => ['required', 'string', 'max:120'], 'rate_amount' => ['required', 'integer', 'min:0'],
            'free_above_amount' => ['nullable', 'integer', 'min:0'], 'active' => ['required', 'boolean'],
        ]);
        $id = $data['id'] ?? null;
        unset($data['id']);
        $id ? DB::table('shipping_rules')->where('id', $id)->update($data + ['updated_at' => now()])
            : $id = DB::table('shipping_rules')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);

        return response()->json(DB::table('shipping_rules')->find($id), 201);
    }

    public function tax(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:tax_rules,id'], 'name' => ['required', 'string', 'max:120'],
            'country' => ['required', Rule::in(config('apf.countries'))], 'region' => ['nullable', 'string', 'max:100'],
            'tax_class' => ['required', 'string', 'max:80'], 'label' => ['required', 'string', 'max:40'],
            'rate_basis_points' => ['required', 'integer', 'between:0,10000'], 'shipping_taxable' => ['required', 'boolean'],
            'nexus_enabled' => ['required', 'boolean'], 'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'active' => ['required', 'boolean'],
        ]);
        $id = $data['id'] ?? null;
        unset($data['id']);
        $id ? DB::table('tax_rules')->where('id', $id)->update($data + ['updated_at' => now()])
            : $id = DB::table('tax_rules')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);

        return response()->json(DB::table('tax_rules')->find($id), 201);
    }
}
