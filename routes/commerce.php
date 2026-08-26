<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DigitalDownloadController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/cart')->middleware('throttle:120,1')->group(function (): void {
    Route::get('/', [CartController::class, 'show'])->name('cart.show');
    Route::post('/items', [CartController::class, 'store'])->name('cart.items.store');
    Route::patch('/items/{cartItem}', [CartController::class, 'update'])->name('cart.items.update');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout/quote', [CheckoutController::class, 'quote'])->name('checkout.quote');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
    Route::get('/checkout/{order}/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/{order}/cancel', [CheckoutController::class, 'cancel'])->middleware('signed')->name('checkout.cancel');
    Route::get('/payments/paypal/{payment}/return', [PaymentController::class, 'paypalReturn'])->middleware('signed')->name('paypal.return');
    Route::get('/account/downloads/{entitlement}', DigitalDownloadController::class)->middleware('signed')->name('downloads.show');
});

Route::post('/payments/webhooks/stripe', [PaymentWebhookController::class, 'stripe'])->name('webhooks.stripe');
Route::post('/payments/webhooks/paypal', [PaymentWebhookController::class, 'paypal'])->name('webhooks.paypal');
