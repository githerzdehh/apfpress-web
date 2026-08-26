<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\PaymentFinalizer;
use Illuminate\Console\Command;

class ReleaseExpiredCheckouts extends Command
{
    protected $signature = 'orders:release-expired {--hours=2 : Age in hours before an unpaid checkout expires}';
    protected $description = 'Cancel stale unpaid orders and release their inventory reservations.';

    public function handle(PaymentFinalizer $finalizer): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $count = 0;
        Order::query()->where('status', 'pending')->where('payment_status', 'unpaid')
            ->where('created_at', '<=', now()->subHours($hours))->with('payments')
            ->chunkById(100, function ($orders) use ($finalizer, &$count): void {
                foreach ($orders as $order) {
                    $finalizer->cancel($order, $order->payments->sortByDesc('id')->first());
                    $count++;
                }
            });

        $this->info("Released {$count} expired checkout(s).");

        return self::SUCCESS;
    }
}
