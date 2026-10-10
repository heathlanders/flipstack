<?php

namespace App\Listeners\Inventory;

use App\Events\Inventory\BulkOperationCompleted;
use App\Models\User\User;
use App\Notifications\Inventory\BulkOperationCompletedNotification;

class SendBulkOperationNotification
{
    public function handle(BulkOperationCompleted $event): void
    {
        $user = User::find($event->userId);

        if (! $user) {
            return;
        }

        $user->notify(new BulkOperationCompletedNotification(
            operationType: $event->operationType,
            totalItems: $event->totalItems,
            processedItems: $event->processedItems,
            failedItems: $event->failedItems,
            status: $event->status,
            jobId: $event->jobId,
        ));
    }
}
