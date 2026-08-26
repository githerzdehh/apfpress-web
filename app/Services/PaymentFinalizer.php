<?php

namespace App\Services;

use App\Models\DigitalEntitlement;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Notifications\OrderPaidNotification;
use Throwable;

class PaymentFinalizer
{
    public function markPaid(Payment $payment, ?string $providerPaymentId = null, array $metadata = []): Order
    {
        $newlyPaid = false;
        $order = DB::transaction(function () use ($payment, $providerPaymentId, $metadata, &$newlyPaid): Order {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);
            if ($payment->status === 'succeeded') {
                return $order;
            }

            $payment->update([
                'status' => 'succeeded',
                'provider_payment_id' => $providerPaymentId ?: $payment->provider_payment_id,
                'provider_metadata' => array_merge($payment->provider_metadata ?? [], $metadata),
                'processed_at' => now(),
            ]);
            $newlyPaid = true;
            $order->update(['status' => 'paid', 'payment_status' => 'paid', 'paid_at' => now()]);

            foreach ($order->items()->with(['offering.inventory', 'offering.digitalAssets'])->get() as $item) {
                $inventory = $item->offering?->inventory()->lockForUpdate()->first();
                if ($inventory?->track_inventory) {
                    $inventory->update([
                        'reserved' => max(0, $inventory->reserved - $item->quantity),
                        'on_hand' => max(0, $inventory->on_hand - $item->quantity),
                    ]);
                    DB::table('inventory_movements')->insert([
                        'offering_id' => $item->offering_id, 'user_id' => $order->user_id,
                        'quantity_delta' => -$item->quantity, 'reason' => 'sale',
                        'reference_type' => Order::class, 'reference_id' => $order->id,
                        'note' => 'Completed sale for '.$order->number.'.', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                foreach ($item->offering?->digitalAssets->where('active', true) ?? [] as $asset) {
                    DigitalEntitlement::query()->firstOrCreate([
                        'user_id' => $order->user_id,
                        'order_item_id' => $item->id,
                        'digital_asset_id' => $asset->id,
                    ], [
                        'starts_at' => now(),
                        'expires_at' => $item->offering->access_duration_days ? now()->addDays($item->offering->access_duration_days) : null,
                    ]);
                }
            }

            if ($order->cart_id) {
                DB::table('carts')->where('id', $order->cart_id)->update(['status' => 'converted', 'updated_at' => now()]);
            }

            return $order->fresh('items');
        });

        if ($newlyPaid && ! $order->receipt_queued_at) {
            try {
                Notification::route('mail', $order->email)->notify(new OrderPaidNotification($order));
                $order->forceFill(['receipt_queued_at' => now()])->save();
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $order;
    }

    public function cancel(Order $order, ?Payment $payment = null): void
    {
        DB::transaction(function () use ($order, $payment): void {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->payment_status === 'paid' || $order->status === 'cancelled') {
                return;
            }

            foreach ($order->items()->with('offering.inventory')->get() as $item) {
                $inventory = $item->offering?->inventory()->lockForUpdate()->first();
                if ($inventory?->track_inventory) {
                    $inventory->update(['reserved' => max(0, $inventory->reserved - $item->quantity)]);
                    DB::table('inventory_movements')->insert([
                        'offering_id' => $item->offering_id, 'user_id' => $order->user_id,
                        'quantity_delta' => 0, 'reason' => 'release',
                        'reference_type' => Order::class, 'reference_id' => $order->id,
                        'note' => 'Released reservation for cancelled checkout.', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            $order->update(['status' => 'cancelled', 'payment_status' => 'failed', 'cancelled_at' => now()]);
            $payment?->update(['status' => 'cancelled', 'processed_at' => now()]);
        });
    }
}
