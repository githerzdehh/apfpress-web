<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPaidNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('APF Press receipt — '.$this->order->number)
            ->greeting('Thank you for your APF Press order.')
            ->line('Payment has been confirmed for order '.$this->order->number.'.');

        foreach ($this->order->items as $item) {
            $message->line($item->quantity.' × '.$item->name.' — '.number_format($item->total_amount / 100, 2).' '.$this->order->currency);
        }

        return $message
            ->line('Order total: '.number_format($this->order->total_amount / 100, 2).' '.$this->order->currency)
            ->action('View your order and downloads', route('account.index'))
            ->line('Questions? Reply to this email or contact '.config('apf.support_email').'.');
    }
}
