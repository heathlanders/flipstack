<?php

namespace App\Jobs\Fulfillment;

use App\Models\Fulfillment\SupplierFulfillmentRequest;
use App\Services\Fulfillment\FulfillmentAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckDropshipSLA implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct()
    {
        $this->onQueue('fulfillment');
    }

    public function handle(): void
    {
        $overdueRequests = SupplierFulfillmentRequest::overdue()
            ->where('status', '!=', SupplierFulfillmentRequest::STATUS_ESCALATED)
            ->with(['supplier:id,name', 'order:id,marketplace_order_id'])
            ->get();

        if ($overdueRequests->isEmpty()) {
            return;
        }

        $auditService = new FulfillmentAuditService;

        foreach ($overdueRequests as $request) {
            $request->update([
                'status' => SupplierFulfillmentRequest::STATUS_ESCALATED,
                'escalation_reason' => 'SLA deadline exceeded',
                'escalated_at' => now(),
            ]);

            // Log using a system user context
            Log::warning('Dropship SLA exceeded', [
                'request_id' => $request->id,
                'supplier' => $request->supplier?->name,
                'order' => $request->order?->marketplace_order_id,
                'sla_deadline' => $request->sla_deadline,
                'hours_overdue' => now()->diffInHours($request->sla_deadline),
            ]);
        }

        Log::info('Dropship SLA check completed', [
            'escalated' => $overdueRequests->count(),
        ]);
    }
}
