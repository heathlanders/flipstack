<?php

namespace App\Services;

use App\Models\Notification\NotificationLog;
use App\Models\User\User;
use App\Notifications\BaseNotification;
use App\Services\NotificationDispatch\EventDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public function __construct(
        private NotificationPreferenceService $preferenceService
    ) {}

    /**
     * Dispatch a notification event through the rules engine.
     *
     * Recipients, channels, dedup window, and escalation are determined by
     * the per-org NotificationRule (or registry defaults if no rule exists).
     */
    public function dispatchEvent(
        string $eventKey,
        int $organizationId,
        array $args = [],
        array $context = [],
        ?string $relatedId = null,
    ): void {
        app(EventDispatcher::class)->dispatch(
            eventKey: $eventKey,
            organizationId: $organizationId,
            args: $args,
            context: $context,
            relatedId: $relatedId,
        );
    }

    /**
     * Send notification to a single user.
     */
    public function send(User $user, BaseNotification $notification): void
    {
        // Check if user should receive this notification based on priority
        if (! $this->preferenceService->shouldSendBasedOnPriority($user, $notification->getPriority())) {
            return;
        }

        // Send the notification
        $user->notify($notification);
    }

    /**
     * Send notification to multiple users.
     */
    public function sendToMany(Collection $users, BaseNotification $notification): void
    {
        // Filter users based on priority preferences
        $filteredUsers = $users->filter(function ($user) use ($notification) {
            return $this->preferenceService->shouldSendBasedOnPriority($user, $notification->getPriority());
        });

        // Send notification
        Notification::send($filteredUsers, $notification);
    }

    /**
     * Send notification to users with specific roles.
     */
    public function sendToRoles(array $roles, BaseNotification $notification, ?int $tenantId = null): void
    {
        $users = User::whereHas('roles', function ($query) use ($roles) {
            $query->whereIn('name', $roles);
        });

        if ($tenantId) {
            $users->where('tenant_id', $tenantId);
        }

        $this->sendToMany($users->get(), $notification);
    }

    /**
     * Log notification delivery.
     */
    public function logDelivery(
        string $notificationId,
        User $user,
        string $channel,
        string $status,
        ?string $error = null,
        ?array $metadata = null
    ): NotificationLog {
        return NotificationLog::create([
            'notification_id' => $notificationId,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id ?? null,
            'channel' => $channel,
            'status' => $status,
            'sent_at' => $status === 'sent' ? now() : null,
            'error_message' => $error,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get unread notification count for a user.
     */
    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(string $notificationId, User $user): void
    {
        $user->notifications()->where('id', $notificationId)->update(['read_at' => now()]);
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }

    /**
     * Get recent notifications for a user.
     */
    public function getRecent(User $user, int $limit = 10)
    {
        return $user->notifications()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Delete old read notifications.
     */
    public function cleanupOldNotifications(int $daysOld = 30): int
    {
        return \DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays($daysOld))
            ->delete();
    }

    /**
     * Delete expired notifications.
     */
    public function cleanupExpiredNotifications(): int
    {
        return \DB::table('notifications')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();
    }
}
