<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Order::query()->with(['items', 'payments'])->latest()->paginate(25));
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json($order->load(['items', 'payments']));
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['paid', 'processing', 'fulfilled', 'cancelled', 'refunded', 'partially_refunded'])],
            'fulfillment_status' => ['sometimes', Rule::in(['unfulfilled', 'processing', 'fulfilled', 'not_required'])],
        ]);
        $order->update($data);

        return response()->json($order->fresh(['items', 'payments']));
    }
}
