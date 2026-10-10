<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class SlackChannel
{
    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification)
    {
        // Get the Slack webhook URL
        $url = $notifiable->routeNotificationForSlack($notification)
               ?? config('services.slack.webhook_url');

        if (! $url) {
            return;
        }

        // Get the Slack message from the notification
        $message = $notification->toSlack($notifiable);

        // Send to Slack
        try {
            Http::post($url, $message);
        } catch (\Exception $e) {
            logger()->error('Slack notification failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

/*
 * Add this to your config/services.php:
 *
 * 'slack' => [
 *     'webhook_url' => env('SLACK_WEBHOOK_URL'),
 * ],
 *
 * Add to .env:
 * SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
 *
 * In your User model (or Team model), add:
 *
 * public function routeNotificationForSlack($notification)
 * {
 *     return $this->slack_webhook_url; // or use global config
 * }
 *
 * In your Notification class, add:
 *
 * public function via($notifiable): array
 * {
 *     return ['database', 'broadcast', 'mail', SlackChannel::class];
 * }
 *
 * public function toSlack($notifiable): array
 * {
 *     return [
 *         'text' => "{$this->getIcon()} *{$this->getTitle()}*",
 *         'blocks' => [
 *             [
 *                 'type' => 'header',
 *                 'text' => [
 *                     'type' => 'plain_text',
 *                     'text' => $this->getTitle(),
 *                 ],
 *             ],
 *             [
 *                 'type' => 'section',
 *                 'text' => [
 *                     'type' => 'mrkdwn',
 *                     'text' => $this->getMessage(),
 *                 ],
 *             ],
 *             [
 *                 'type' => 'section',
 *                 'fields' => [
 *                     [
 *                         'type' => 'mrkdwn',
 *                         'text' => "*Priority:*\n{$this->getPriority()}",
 *                     ],
 *                     [
 *                         'type' => 'mrkdwn',
 *                         'text' => "*Category:*\n{$this->getCategory()}",
 *                     ],
 *                 ],
 *             ],
 *             [
 *                 'type' => 'actions',
 *                 'elements' => [
 *                     [
 *                         'type' => 'button',
 *                         'text' => [
 *                             'type' => 'plain_text',
 *                             'text' => $this->getActionText(),
 *                         ],
 *                         'url' => $this->getActionUrl(),
 *                         'style' => $this->getPriority() === 'critical' ? 'danger' : 'primary',
 *                     ],
 *                 ],
 *             ],
 *         ],
 *     ];
 * }
 */
