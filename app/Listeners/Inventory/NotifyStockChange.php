<?php

namespace App\Listeners\Inventory;

use App\Events\Inventory\LowStockAlert;
use App\Models\User\User;
use App\Notifications\Inventory\LowStockNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Listener that sends notifications to users when stock alerts are triggered.
 *
 * This listener:
 * - Finds users in the organization with inventory.view permission
 * - Filters based on user notification preferences
 * - Sends multi-channel notifications (database, broadcast, email)
 */
class NotifyStockChange implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'notifications';

    /**
     * Handle the event.
     */
    public function handle(LowStockAlert $event): void
    {
        $product = $event->product;
        $level = $event->inventoryLevel;
        $organizationId = $product->organization_id;

        Log::info('Sending stock alert notifications', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'location_id' => $level->location_id,
            'alert_type' => $event->alertType,
            'organization_id' => $organizationId,
        ]);

        // Find users who should receive notifications
        $users = $this->getNotificationRecipients($organizationId, $event->alertType);

        if ($users->isEmpty()) {
            Log::info('No users to notify for stock alert', [
                'organization_id' => $organizationId,
                'alert_type' => $event->alertType,
            ]);

            return;
        }

        // Send notification to all eligible users
        Notification::send($users, new LowStockNotification($event));

        Log::info('Stock alert notifications sent', [
            'product_id' => $product->id,
            'alert_type' => $event->alertType,
            'recipient_count' => $users->count(),
        ]);
    }

    /**
     * Get users who should receive notifications.
     */
    protected function getNotificationRecipients(int $organizationId, string $alertType)
    {
        // Base query for users in the organization
        $query = User::query();

        // Filter by organization (supports both patterns)
        $query->where(function ($q) use ($organizationId) {
            $q->where('organization_id', $organizationId)
                ->orWhere('current_organization_id', $organizationId);
        });

        // Filter by permission if the method exists
        if (method_exists(User::class, 'scopeWithPermission')) {
            $query->withPermission('inventory.view');
        }

        // Get users
        $users = $query->get();

        // Filter by notification preferences
        return $users->filter(function ($user) use ($alertType) {
            return $this->userWantsNotification($user, $alertType);
        });
    }

    /**
     * Check if user wants notifications for this alert type.
     */
    protected function userWantsNotification(User $user, string $alertType): bool
    {
        $preferences = $user->preferences ?? [];
        $notificationPrefs = $preferences['inventory_notifications'] ?? [];

        // Default preferences based on severity
        $defaults = [
            'out_of_stock' => true,   // Always notify for out of stock
            'low_stock' => true,      // Notify for low stock
            'overstocked' => false,   // Don't notify for overstocked by default
        ];

        return $notificationPrefs[$alertType] ?? $defaults[$alertType] ?? true;
    }

    /**
     * Handle a job failure.
     */
    public function failed(LowStockAlert $event, \Throwable $exception): void
    {
        Log::error('NotifyStockChange listener failed', [
            'product_id' => $event->product->id,
            'alert_type' => $event->alertType,
            'error' => $exception->getMessage(),
        ]);
    }
}
