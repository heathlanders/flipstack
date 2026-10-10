<?php

namespace App\Notifications\Orders;

use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

class NewOrderNotification extends BaseNotification
{
    public function __construct(
        public object $order
    ) {}

    public function getPriority(): string
    {
        return self::PRIORITY_HIGH;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_ORDER;
    }

    public function getTitle(): string
    {
        return 'New Order Received';
    }

    public function getMessage(): string
    {
        return sprintf(
            'Order #%s from %s - $%.2f',
            $this->order->order_number,
            $this->order->customer_name,
            $this->order->total
        );
    }

    public function getActionUrl(): ?string
    {
        return route('dashboard');
    }

    public function getActionText(): string
    {
        return 'View Order';
    }

    protected function getData(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'customer_name' => $this->order->customer_name,
            'total' => $this->order->total,
            'items_count' => $this->order->items->count(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("New Order #{$this->order->order_number}")
            ->greeting('New Order Received!')
            ->line('A new order has been placed:')
            ->line("**Order Number:** {$this->order->order_number}")
            ->line("**Customer:** {$this->order->customer_name}")
            ->line("**Total:** \${$this->order->total}");

        if ($this->order->items->isNotEmpty()) {
            $message->line('**Items:**');
            foreach ($this->order->items->take(5) as $item) {
                $message->line("• {$item->name} (x{$item->quantity})");
            }
        }

        return $message
            ->action('View Order', route('dashboard'))
            ->line('Thank you for using our application!');
    }
}
