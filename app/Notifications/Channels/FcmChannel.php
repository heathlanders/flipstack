<?php

namespace App\Notifications\Channels;

use App\Models\Notification\DeviceToken;
use App\Services\Notification\Fcm\FcmDispatcher;
use Illuminate\Notifications\Notification;

/**
 * Channel that pushes a notification to every active FCM token registered
 * for the notifiable user.
 *
 * Notification classes opt in by defining `toFcm($notifiable): array` with
 * keys `title`, `body`, optional `data`. If absent, falls back to data-only
 * payload from `toArray()`.
 */
class FcmChannel
{
    public function __construct(private FcmDispatcher $dispatcher) {}

    public function send($notifiable, Notification $notification): void
    {
        if (! $this->dispatcher->isConfigured()) {
            return;
        }

        if (! method_exists($notifiable, 'getKey')) {
            return;
        }

        $tokens = DeviceToken::query()
            ->where('user_id', $notifiable->getKey())
            ->whereNull('revoked_at')
            ->pluck('fcm_token')
            ->all();

        if (! $tokens) {
            return;
        }

        $payload = $this->buildPayload($notifiable, $notification);

        foreach ($tokens as $token) {
            $this->dispatcher->send(
                $token,
                $payload['title'],
                $payload['body'],
                $payload['data'] ?? [],
            );
        }
    }

    private function buildPayload($notifiable, Notification $notification): array
    {
        if (method_exists($notification, 'toFcm')) {
            $custom = $notification->toFcm($notifiable);
            if (is_array($custom) && isset($custom['title'], $custom['body'])) {
                return $custom + ['data' => []];
            }
        }

        if (method_exists($notification, 'toArray')) {
            $arr = $notification->toArray($notifiable);

            return [
                'title' => $arr['title'] ?? 'Notification',
                'body' => $arr['message'] ?? '',
                'data' => [
                    'type' => $arr['type'] ?? '',
                    'category' => $arr['category'] ?? '',
                    'priority' => $arr['priority'] ?? '',
                    'action_url' => $arr['action_url'] ?? '',
                ],
            ];
        }

        return ['title' => 'Notification', 'body' => '', 'data' => []];
    }
}
