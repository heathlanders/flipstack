<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Twilio\Rest\Client;

class SmsChannel
{
    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification)
    {
        // Get the phone number to send to
        $to = $notifiable->routeNotificationFor('sms', $notification);

        if (! $to) {
            return;
        }

        // Get the SMS message from the notification
        $message = $notification->toSms($notifiable);

        // Send via Twilio
        try {
            $twilio = new Client(
                config('services.twilio.sid'),
                config('services.twilio.token')
            );

            $twilio->messages->create($to, [
                'from' => config('services.twilio.from'),
                'body' => $message,
            ]);
        } catch (\Exception $e) {
            // Log the error
            logger()->error('SMS notification failed', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

/*
 * Add this to your config/services.php:
 *
 * 'twilio' => [
 *     'sid' => env('TWILIO_SID'),
 *     'token' => env('TWILIO_TOKEN'),
 *     'from' => env('TWILIO_FROM'),
 * ],
 *
 * Add to .env:
 * TWILIO_SID=your-twilio-sid
 * TWILIO_TOKEN=your-twilio-token
 * TWILIO_FROM=+1234567890
 *
 * Install Twilio SDK:
 * composer require twilio/sdk
 *
 * In your User model, add:
 *
 * public function routeNotificationForSms($notification)
 * {
 *     return $this->phone_number; // or however you store phone numbers
 * }
 *
 * In your Notification class, add:
 *
 * public function via($notifiable): array
 * {
 *     return ['database', 'broadcast', 'mail', SmsChannel::class];
 * }
 *
 * public function toSms($notifiable): string
 * {
 *     return "Alert: {$this->getTitle()} - {$this->getMessage()}";
 * }
 */
