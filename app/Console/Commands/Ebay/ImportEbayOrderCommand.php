<?php

namespace App\Console\Commands\Ebay;

use App\Models\Integration\IntegrationCredential;
use App\Services\Fulfillment\OrderSyncService;
use Illuminate\Console\Command;
use Throwable;

class ImportEbayOrderCommand extends Command
{
    protected $signature = 'ebay:import-order
                            {orderId : eBay marketplace order ID}
                            {--credential= : IntegrationCredential ID (defaults to first active eBay credential)}';

    protected $description = 'Import a single eBay order by ID using the Fulfillment API. Idempotent.';

    public function handle(): int
    {
        $orderId = $this->argument('orderId');

        $credential = $this->resolveCredential();
        if (! $credential) {
            return Command::FAILURE;
        }

        $this->info("Importing eBay order {$orderId} via credential {$credential->id} (org {$credential->organization_id})");

        try {
            $service = new OrderSyncService($credential);
            $order = $service->syncSingleOrder($orderId);
        } catch (Throwable $e) {
            $this->error('Import failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        $order->load('items');

        $this->line('');
        $this->info("Order {$order->id} ({$order->marketplace_order_id}) status={$order->status}");
        $this->line("Buyer: {$order->buyer_username}");
        $this->line("Total: {$order->total_amount} {$order->currency}");
        $this->line("Items: {$order->items_count}");

        foreach ($order->items as $item) {
            $matched = $item->product_id ? "product={$item->product_id}" : 'UNMATCHED';
            $this->line("  - sku={$item->sku} qty={$item->quantity} {$matched}");
        }

        $this->line('');
        $this->info('Inventory reservation + marketplace push dispatched (if order was new).');

        return Command::SUCCESS;
    }

    protected function resolveCredential(): ?IntegrationCredential
    {
        if ($id = $this->option('credential')) {
            $credential = IntegrationCredential::find($id);
            if (! $credential || $credential->platform !== 'ebay') {
                $this->error("Credential {$id} not found or not eBay.");

                return null;
            }

            return $credential;
        }

        $credential = IntegrationCredential::where('platform', 'ebay')
            ->where('is_active', true)
            ->orderBy('created_at')
            ->first();

        if (! $credential) {
            $this->error('No active eBay credential found. Pass --credential=<id>.');

            return null;
        }

        return $credential;
    }
}
