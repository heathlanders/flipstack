<?php

namespace App\Jobs\Fulfillment;

use App\Models\Fulfillment\Order;
use App\Services\Fulfillment\FulfillmentAuditService;
use App\Services\Fulfillment\FulfillmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoAssignOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('fulfillment');
    }

    public function handle(): void
    {
        $auditService = new FulfillmentAuditService;
        $fulfillmentService = new FulfillmentService($auditService);

        $pendingOrders = Order::where('status', Order::STATUS_PENDING)
            ->whereNull('location_id')
            ->orderBy('priority', 'desc')
            ->orderBy('ship_by_date')
            ->limit(100)
            ->get();

        if ($pendingOrders->isEmpty()) {
            return;
        }

        $assigned = $fulfillmentService->autoAssignOrders($pendingOrders);

        Log::info('Auto-assign orders completed', [
            'pending' => $pendingOrders->count(),
            'assigned' => $assigned,
        ]);
    }
}
