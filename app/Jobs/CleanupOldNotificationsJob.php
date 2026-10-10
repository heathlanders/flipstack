<?php

namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CleanupOldNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $daysOld = 30
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        // Delete old read notifications
        $deletedRead = $notificationService->cleanupOldNotifications($this->daysOld);

        // Delete expired notifications
        $deletedExpired = $notificationService->cleanupExpiredNotifications();

        logger()->info('Cleaned up old notifications', [
            'deleted_read' => $deletedRead,
            'deleted_expired' => $deletedExpired,
        ]);
    }
}

/*
 * Schedule this job in app/Console/Kernel.php:
 *
 * protected function schedule(Schedule $schedule): void
 * {
 *     // Run daily at midnight
 *     $schedule->job(new CleanupOldNotificationsJob(30))
 *              ->daily()
 *              ->at('00:00');
 * }
 *
 * Or dispatch manually:
 * CleanupOldNotificationsJob::dispatch(30);
 */
