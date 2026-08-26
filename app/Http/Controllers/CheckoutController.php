<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PaymentFinalizer;
use App\Services\PaymentManager;
use App\Services\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class CheckoutController extends Controller
{
    public function index(Request $request, CartService $carts, QuoteService $quotes): View|RedirectResponse
    {
        $cart = $carts->current($request);
        if ($cart->items()->doesntExist()) {
            return redirect()->route('catalog.index')->withErrors(['cart' => 'Your cart is empty.']);
        }

        return view('checkout.index', ['cart' => $carts->present($cart), 'quote' => $quotes->quote($cart, 'CA', 'ON')]);
    }

    public function quote(Request $request, CartService $carts, QuoteService $quotes): JsonResponse
    {
        $data = $request->validate(['country' => ['required', Rule::in(config('apf.countries'))], 'region' => ['nullable', 'string', 'max:100']]);

        return response()->json($quotes->quote($carts->current($request), $data['country'], $data['region'] ?? null));
    }

    public function store(Request $request, CartService $carts, QuoteService $quotes, OrderService $orders, PaymentManager $payments, PaymentFinalizer $finalizer): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(['stripe', 'paypal'])],
            'name' => ['required', 'string', 'max:120'], 'company' => ['nullable', 'string', 'max:120'],
            'line_1' => ['required', 'string', 'max:160'], 'line_2' => ['nullable', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:120'], 'region' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'], 'country' => ['required', Rule::in(config('apf.countries'))],
            'phone' => ['nullable', 'string', 'max:40'], 'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $cart = $carts->current($request);
        abort_if($cart->items()->doesntExist(), 422, 'Your cart is empty.');
        $quote = $quotes->quote($cart, $data['country'], $data['region']);
        $address = collect($data)->only(['name', 'company', 'line_1', 'line_2', 'city', 'region', 'postal_code', 'country', 'phone'])->all();
        $order = $orders->create($cart, $request->user(), $address, $quote, $data['note'] ?? null);
        $payment = $order->payments()->create(['provider' => $data['provider'], 'status' => 'created', 'amount' => $order->total_amount, 'currency' => $order->currency]);

        try {
            $checkout = $payments->gateway($data['provider'])->createCheckout($order, $payment);
            $payment->update(['provider_checkout_id' => $checkout['checkout_id'], 'status' => 'pending', 'provider_metadata' => $checkout['metadata'] ?? null]);
        } catch (Throwable $exception) {
            report($exception);
            $finalizer->cancel($order, $payment);

            return back()->withInput()->withErrors(['payment' => 'Checkout could not be started. Please try another payment method or contact APF Press.']);
        }

        return redirect()->away($checkout['url']);
    }

    public function success(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        $request->session()->forget('cart_id');

        return view('checkout.success', ['order' => $order->load('items')]);
    }

    public function cancel(Request $request, Order $order, PaymentFinalizer $finalizer): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        $finalizer->cancel($order, $order->payments()->latest()->first());

        return redirect()->route('checkout.index')->withErrors(['payment' => 'Checkout was cancelled. Your cart is still available.']);
    }
}
