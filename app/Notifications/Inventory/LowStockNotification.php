<?php

namespace App\Notifications\Inventory;

use App\Events\Inventory\LowStockAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when inventory crosses threshold boundaries.
 *
 * Supports multiple channels:
 * - database: Stored for in-app notification center
 * - broadcast: Real-time push via WebSocket
 * - mail: Email notifications for critical alerts
 */
class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The low stock alert event.
     */
    protected LowStockAlert $event;

    /**
     * Create a new notification instance.
     */
    public function __construct(LowStockAlert $event)
    {
        $this->event = $event;

        // Queue on different queues based on urgency
        $this->onQueue($event->alertType === 'out_of_stock' ? 'emails' : 'notifications');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];

        // Add email for critical alerts or based on user preference
        if ($this->shouldSendEmail($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Determine if email should be sent.
     */
    protected function shouldSendEmail(object $notifiable): bool
    {
        // Always email for out of stock
        if ($this->event->alertType === 'out_of_stock') {
            return true;
        }

        // Check user preferences for other alert types
        $preferences = $notifiable->preferences ?? [];
        $emailPrefs = $preferences['inventory_email_alerts'] ?? [];

        return $emailPrefs[$this->event->alertType] ?? false;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $product = $this->event->product;
        $level = $this->event->inventoryLevel;
        $alertType = $this->event->alertType;

        $subject = match ($alertType) {
            'out_of_stock' => "⚠️ Out of Stock: {$product->name}",
            'low_stock' => "📉 Low Stock Alert: {$product->name}",
            'overstocked' => "📈 Overstocked: {$product->name}",
            default => "Inventory Alert: {$product->name}",
        };

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting($this->getGreeting($alertType))
            ->line($this->getMainMessage())
            ->line('')
            ->line("**Product:** {$product->name}")
            ->line("**SKU:** {$product->sku}");

        // Add location info if available
        if ($level->location) {
            $message->line("**Location:** {$level->location->name}");
        }

        $message->line("**Current Stock:** {$level->quantity_on_hand}")
            ->line("**Available:** {$level->quantity_available}")
            ->line("**Reserved:** {$level->quantity_reserved}");

        // Add threshold info based on alert type
        if ($alertType === 'low_stock') {
            $message->line("**Reorder Point:** {$level->reorder_point}")
                ->line("**Suggested Reorder Qty:** {$level->reorder_quantity}");
        } elseif ($alertType === 'overstocked') {
            $message->line("**Max Stock Level:** {$level->max_stock_level}");
        }

        // Add action button
        $message->action(
            'View Inventory',
            url("/inventory/products/{$product->id}")
        );

        // Add appropriate closing based on severity
        if ($alertType === 'out_of_stock') {
            $message->line('')
                ->line('**Action Required:** Please reorder this item as soon as possible to avoid stockouts.');
        }

        return $message;
    }

    /**
     * Get the broadcast representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'inventory_alert',
            'alert_type' => $this->event->alertType,
            'severity' => $this->event->getSeverity(),
            'title' => $this->getTitle(),
            'message' => $this->getMainMessage(),
            'product' => [
                'id' => $this->event->product->id,
                'name' => $this->event->product->name,
                'sku' => $this->event->product->sku,
            ],
            'inventory' => [
                'location_id' => $this->event->inventoryLevel->location_id,
                'quantity_on_hand' => $this->event->inventoryLevel->quantity_on_hand,
                'quantity_available' => $this->event->inventoryLevel->quantity_available,
                'quantity_reserved' => $this->event->inventoryLevel->quantity_reserved,
                'reorder_point' => $this->event->inventoryLevel->reorder_point,
            ],
            'action_url' => url("/inventory/products/{$this->event->product->id}"),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        $level = $this->event->inventoryLevel;

        return [
            'type' => 'inventory_alert',
            'alert_type' => $this->event->alertType,
            'severity' => $this->event->getSeverity(),
            'title' => $this->getTitle(),
            'message' => $this->getMainMessage(),
            'product_id' => $this->event->product->id,
            'product_name' => $this->event->product->name,
            'sku' => $this->event->product->sku,
            'location_id' => $level->location_id,
            'inventory_level_id' => $level->id,
            'quantity_on_hand' => $level->quantity_on_hand,
            'quantity_available' => $level->quantity_available,
            'quantity_reserved' => $level->quantity_reserved,
            'reorder_point' => $level->reorder_point,
            'reorder_quantity' => $level->reorder_quantity,
            'max_stock_level' => $level->max_stock_level,
            'previous_quantity' => $this->event->previousQuantity,
            'action_url' => "/inventory/products/{$this->event->product->id}",
        ];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    /**
     * Get the greeting based on alert type.
     */
    protected function getGreeting(string $alertType): string
    {
        return match ($alertType) {
            'out_of_stock' => '⚠️ Urgent: Out of Stock Alert',
            'low_stock' => '📉 Low Stock Warning',
            'overstocked' => '📈 Inventory Notice',
            default => 'Inventory Alert',
        };
    }

    /**
     * Get the notification title.
     */
    protected function getTitle(): string
    {
        $product = $this->event->product;

        return match ($this->event->alertType) {
            'out_of_stock' => "Out of Stock: {$product->name}",
            'low_stock' => "Low Stock: {$product->name}",
            'overstocked' => "Overstocked: {$product->name}",
            default => "Stock Alert: {$product->name}",
        };
    }

    /**
     * Get the main notification message.
     */
    protected function getMainMessage(): string
    {
        $product = $this->event->product;
        $level = $this->event->inventoryLevel;

        return match ($this->event->alertType) {
            'out_of_stock' => "{$product->name} (SKU: {$product->sku}) is now out of stock with {$level->quantity_on_hand} units on hand.",
            'low_stock' => "{$product->name} (SKU: {$product->sku}) has dropped below the reorder point. Current available: {$level->quantity_available}, Reorder point: {$level->reorder_point}.",
            'overstocked' => "{$product->name} (SKU: {$product->sku}) has exceeded maximum stock level. Current: {$level->quantity_on_hand}, Maximum: {$level->max_stock_level}.",
            default => "Stock level changed for {$product->name}.",
        };
    }

    /**
     * Get the queue name for the broadcast channel.
     */
    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
