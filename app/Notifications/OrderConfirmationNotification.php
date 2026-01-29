<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Order $order
    )
    {
        //
        $this->order->load('user', 'orderItems.product');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * confirmation email.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Order Confirmation - Order #' . $this->order->order_number)
            ->greeting("Hello {$notifiable->name},")
            ->line('Thank you for your order! We are pleased to confirm that we have received your order with the following details:')
            ->line("Order Total: $" . number_format($this->order->total, 2));

            foreach ($this->order->orderItems as $item) {
                $productName = $item->product->name ?? 'Product Deleted';
                $mail->line(" - {$productName} x {$item->quantity} @ $" . number_format($item->price, 2) . " each");
            }

        return $mail->line('We will notify you once your order has been shipped. If you have any questions, feel free to contact our support team.')
            ->salutation('Thank you for shopping with us!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
