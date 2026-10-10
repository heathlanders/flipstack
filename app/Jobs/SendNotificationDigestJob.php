<?php

namespace App\Jobs;

use App\Mail\NotificationDigestMail;
use App\Models\User\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a digest email summarising unread notifications since the last digest
 * window. Three flavours scheduled separately: hourly, daily, weekly.
 *
 * Reads digest_frequency from each user's notification_settings JSON column
 * (managed by NotificationPreferenceService).
 */
class SendNotificationDigestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $frequency,
    ) {}

    public function handle(): void
    {
        User::query()
            ->whereNotNull('email')
            ->where(function ($q) {
                $q->whereJsonContains('notification_settings->digest_frequency', $this->frequency)
                    ->orWhere(function ($inner) {
                        // legacy users without explicit frequency: only daily picks them up
                        if ($this->frequency === 'daily') {
                            $inner->whereNull('notification_settings');
                        }
                    });
            })
            ->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    $this->sendDigestToUser($user);
                }
            });
    }

    private function sendDigestToUser(User $user): void
    {
        $since = $this->getTimeSinceLastDigest();

        $notifications = $user->unreadNotifications()
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->get();

        if ($notifications->isEmpty()) {
            return;
        }

        $grouped = $notifications->groupBy(fn ($n) => data_get($n->data, 'category', 'other'));

        try {
            Mail::to($user->email)->send(new NotificationDigestMail(
                $user,
                $notifications,
                $grouped,
                $this->frequency,
            ));

            Log::info('notification.digest.sent', [
                'user_id' => $user->id,
                'frequency' => $this->frequency,
                'count' => $notifications->count(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('notification.digest.send_failed', [
                'user_id' => $user->id,
                'frequency' => $this->frequency,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getTimeSinceLastDigest(): \Carbon\Carbon
    {
        return match ($this->frequency) {
            'hourly' => now()->subHour(),
            'daily' => now()->subDay(),
            'weekly' => now()->subWeek(),
            default => now()->subDay(),
        };
    }
}
