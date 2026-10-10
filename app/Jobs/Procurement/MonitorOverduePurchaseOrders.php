<?php

namespace App\Jobs\Procurement;

use App\Models\Procurement\PurchaseOrder;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Daily scheduled job. Finds POs whose expected_delivery_date is past, in
 * SUBMITTED or PARTIALLY_RECEIVED status, and dispatches overdue notifications.
 *
 * Dedup window in the registry (1440 minutes / 24h) prevents repeat alerts
 * within the same day if the job runs more than once.
 */
class MonitorOverduePurchaseOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificationService $notificationService): void
    {
        $today = now()->startOfDay();

        $overdue = PurchaseOrder::withoutGlobalScopes()
            ->whereIn('status', [
                PurchaseOrder::STATUS_SUBMITTED,
                PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            ])
            ->whereNotNull('expected_delivery_date')
            ->where('expected_delivery_date', '<', $today)
            ->with('supplier')
            ->cursor();

        foreach ($overdue as $po) {
            $expected = \Carbon\Carbon::parse($po->expected_delivery_date);
            $daysLate = (int) $expected->diffInDays($today);

            $notificationService->dispatchEvent(
                eventKey: 'procurement.po.overdue',
                organizationId: (int) $po->organization_id,
                args: [
                    (string) $po->id,
                    (string) $po->po_number,
                    $po->supplier?->name,
                    $expected->toDateString(),
                    $daysLate,
                ],
                relatedId: (string) $po->id,
            );
        }
    }
}
