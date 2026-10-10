<?php

namespace App\Notifications;

use App\Services\NotificationPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Notification priority levels.
     */
    const PRIORITY_CRITICAL = 'critical';

    const PRIORITY_HIGH = 'high';

    const PRIORITY_MEDIUM = 'medium';

    const PRIORITY_LOW = 'low';

    /**
     * Notification categories.
     */
    const CATEGORY_ORDER = 'orders';

    const CATEGORY_INVENTORY = 'inventory';

    const CATEGORY_FINANCIAL = 'financial';

    const CATEGORY_PLATFORM = 'platform';

    const CATEGORY_CUSTOMER = 'customer';

    const CATEGORY_SYSTEM = 'system';

    /**
     * Get the notification's priority.
     */
    abstract public function getPriority(): string;

    /**
     * Get the notification's category.
     */
    abstract public function getCategory(): string;

    /**
     * Get the notification's icon (deprecated - icons removed from UI).
     */
    public function getIcon(): string
    {
        return '';
    }

    /**
     * Get the notification's title.
     */
    abstract public function getTitle(): string;

    /**
     * Get the notification's message.
     */
    abstract public function getMessage(): string;

    /**
     * Get the notification's action URL.
     */
    abstract public function getActionUrl(): ?string;

    /**
     * Get the notification's action text.
     */
    public function getActionText(): string
    {
        return 'View';
    }

    /**
     * Determine which channels the notification should be sent through.
     */
    public function via($notifiable): array
    {
        $preferenceService = app(NotificationPreferenceService::class);
        $channels = $preferenceService->getEnabledChannels($notifiable, $this->getCategory());

        // Append fcm when push is enabled and the user has at least one
        // active device token. Replace 'broadcast' is NOT done — both can
        // fire (web bell via broadcast, mobile via fcm).
        if (
            in_array('broadcast', $channels, true)
            && method_exists($notifiable, 'getKey')
            && \App\Models\Notification\DeviceToken::where('user_id', $notifiable->getKey())
                ->whereNull('revoked_at')
                ->exists()
        ) {
            $channels[] = 'fcm';
        }

        return $channels;
    }

    /**
     * Get the array representation of the notification (for database channel).
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => class_basename($this),
            'priority' => $this->getPriority(),
            'category' => $this->getCategory(),
            'title' => $this->getTitle(),
            'message' => $this->getMessage(),
            'icon' => $this->getIcon(),
            'action_url' => $this->getActionUrl(),
            'action_text' => $this->getActionText(),
            'data' => $this->getData(),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast($notifiable): array
    {
        return $this->toArray($notifiable);
    }

    /**
     * Get additional data for the notification.
     */
    protected function getData(): array
    {
        return [];
    }

    /**
     * Get the priority color for UI rendering.
     */
    public function getPriorityColor(): string
    {
        return match ($this->getPriority()) {
            self::PRIORITY_CRITICAL => 'red',
            self::PRIORITY_HIGH => 'orange',
            self::PRIORITY_MEDIUM => 'blue',
            self::PRIORITY_LOW => 'green',
            default => 'gray',
        };
    }
}
