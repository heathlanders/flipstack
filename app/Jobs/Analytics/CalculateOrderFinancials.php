<?php

namespace App\Jobs\Analytics;

use App\Models\Fulfillment\Order;
use App\Services\Analytics\OrderFinancialCalculator;
use App\Services\Marketplace\FeeTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CalculateOrderFinancials implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 120;

    public function __construct(
        protected string $orderId
    ) {
        $this->onQueue('default');
    }

    public function handle(
        OrderFinancialCalculator $calculator,
        FeeTrackingService $feeTrackingService
    ): void {
        $order = Order::withoutGlobalScopes()->find($this->orderId);

        if (! $order) {
            Log::warning('Order not found for financial calculation', ['order_id' => $this->orderId]);

            return;
        }

        // First extract marketplace fees from order data
        $feeTrackingService->recordFeesFromOrder($order);

        // Then calculate full financials
        $calculator->calculate($order);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to calculate order financials', [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return ['order-financials', 'order:'.$this->orderId];
    }
}
