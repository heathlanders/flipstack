<?php

namespace App\Console\Commands\Ebay;

use App\Models\Fulfillment\Order;
use App\Models\Integration\IntegrationCredential;
use App\Services\Fulfillment\OrderSyncService;
use Illuminate\Console\Command;
use Throwable;

class PullEbayTrackingCommand extends Command
{
    protected $signature = 'ebay:pull-tracking
                            {--order= : Pull tracking for a single Order ID (UUID) only}
                            {--credential= : IntegrationCredential ID (defaults to all active eBay credentials)}
                            {--days=14 : Look back this many days at unshipped orders}';

    protected $description = 'Pull tracking from eBay-purchased labels and create local Shipments for unshipped orders.';

    public function handle(): int
    {
        if ($orderId = $this->option('order')) {
            return $this->pullForOrder($orderId);
        }

        $credentials = $this->resolveCredentials();
        if ($credentials->isEmpty()) {
            $this->error('No active eBay credentials found.');

            return Command::FAILURE;
        }

        $days = (int) $this->option('days');
        $unshipped = [
            Order::STATUS_PENDING,
            Order::STATUS_ASSIGNED,
            Order::STATUS_PICKING,
            Order::STATUS_PICKED,
            Order::STATUS_PACKING,
            Order::STATUS_PACKED,
        ];

        $totalCreated = 0;

        foreach ($credentials as $credential) {
            $this->info("Credential {$credential->id} (org {$credential->organization_id})");

            $service = new OrderSyncService($credential);

            $orders = Order::withoutGlobalScopes()
                ->where('credential_id', $credential->id)
                ->where('marketplace', 'ebay')
                ->whereIn('status', $unshipped)
                ->where('updated_at', '>=', now()->subDays($days))
                ->whereNotNull('marketplace_order_id')
                ->get();

            $this->line("  Inspecting {$orders->count()} unshipped orders");

            foreach ($orders as $order) {
                try {
                    if ($service->pullEbayTracking($order)) {
                        $totalCreated++;
                        $this->line("  + Imported tracking for {$order->marketplace_order_id}");
                    }
                } catch (Throwable $e) {
                    $this->warn("  ! {$order->marketplace_order_id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Done. New shipments created: {$totalCreated}");

        return Command::SUCCESS;
    }

    protected function pullForOrder(string $orderId): int
    {
        $order = Order::withoutGlobalScopes()->find($orderId);
        if (! $order) {
            $this->error("Order {$orderId} not found.");

            return Command::FAILURE;
        }

        $credential = $order->credential;
        if (! $credential || $credential->platform !== 'ebay') {
            $this->error('Order is not an eBay order or has no eBay credential.');

            return Command::FAILURE;
        }

        $service = new OrderSyncService($credential);
        $created = $service->pullEbayTracking($order);

        $this->info($created
            ? "Imported tracking for {$order->marketplace_order_id}"
            : "No new tracking found for {$order->marketplace_order_id}");

        return Command::SUCCESS;
    }

    protected function resolveCredentials()
    {
        $query = IntegrationCredential::where('platform', 'ebay')->where('is_active', true);

        if ($id = $this->option('credential')) {
            $query->where('id', $id);
        }

        return $query->get();
    }
}
