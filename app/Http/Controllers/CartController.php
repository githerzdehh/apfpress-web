<?php

namespace App\Http\Controllers;

use App\Models\Offering;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request, CartService $carts): JsonResponse
    {
        return response()->json($carts->present($carts->current($request)));
    }

    public function store(Request $request, CartService $carts): JsonResponse
    {
        $data = $request->validate(['offering_id' => ['required', 'integer', 'exists:offerings,id'], 'quantity' => ['required', 'integer', 'between:1,25']]);
        $cart = $carts->add($carts->current($request), Offering::query()->findOrFail($data['offering_id']), $data['quantity']);

        return response()->json($carts->present($cart), 201);
    }

    public function update(Request $request, int $cartItem, CartService $carts): JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'between:0,25']]);

        return response()->json($carts->present($carts->update($carts->current($request), $cartItem, $data['quantity'])));
    }
}
