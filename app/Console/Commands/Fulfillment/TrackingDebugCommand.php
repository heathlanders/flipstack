<?php

namespace App\Console\Commands\Fulfillment;

use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\Shipment;
use App\Models\Integration\IntegrationCredential;
use App\Services\Tracking\Adapters\EbayTrackingAdapter;
use App\Services\Tracking\TrackingProviderRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TrackingDebugCommand extends Command
{
    protected $signature = 'tracking:debug
                            {--shipment= : Shipment UUID to inspect}
                            {--order= : Order UUID — uses the latest shipment on that order}
                            {--no-cache : Forget the eBay Trading API cache for this order before fetching}';

    protected $description = 'Read-only diagnostic for a Shipment: prints provider, token state, raw eBay XML excerpt, and parsed TrackingResult.';

    public function handle(): int
    {
        $shipment = $this->resolveShipment();

        if (! $shipment) {
            return Command::FAILURE;
        }

        $shipment->loadMissing('order');

        $this->printShipment($shipment);
        $this->printOrder($shipment->order);

        $registry = app(TrackingProviderRegistry::class);
        $provider = $registry->resolve($shipment);

        if (! $provider) {
            $this->warn('No tracking provider matched this shipment. Check carrier, tracking_number, marketplace, and credentials.');

            return Command::SUCCESS;
        }

        $this->line('');
        $this->info("Provider matched: {$provider->name()}");

        if ($provider instanceof EbayTrackingAdapter) {
            $this->inspectEbay($shipment, $provider);
        }

        $this->line('');
        $this->info('Calling provider->fetch() ...');
        $result = $provider->fetch($shipment);

        $this->printResult($result);

        return Command::SUCCESS;
    }

    protected function resolveShipment(): ?Shipment
    {
        if ($shipmentId = $this->option('shipment')) {
            $shipment = Shipment::find($shipmentId);
            if (! $shipment) {
                $this->error("No shipment found with id {$shipmentId}");

                return null;
            }

            return $shipment;
        }

        if ($orderId = $this->option('order')) {
            $order = Order::find($orderId);
            if (! $order) {
                $this->error("No order found with id {$orderId}");

                return null;
            }

            $shipment = $order->shipments()->latest()->first();
            if (! $shipment) {
                $this->error("Order {$orderId} has no shipments. eBay may not have reported tracking yet — try `php artisan ebay:pull-tracking --order={$orderId}` first.");

                return null;
            }

            return $shipment;
        }

        $this->error('Provide --shipment=UUID or --order=UUID');

        return null;
    }

    protected function printShipment(Shipment $shipment): void
    {
        $this->info('Shipment');
        $this->line('  id:                       '.$shipment->id);
        $this->line('  status:                   '.$shipment->status);
        $this->line('  carrier:                  '.($shipment->carrier ?: 'null'));
        $this->line('  tracking_number:          '.($shipment->tracking_number ?: 'null'));
        $this->line('  shipping_provider:        '.($shipment->shipping_provider ?: 'null'));
        $this->line('  tracking_provider:        '.($shipment->tracking_provider ?: 'null'));
        $this->line('  shipped_at:               '.($shipment->shipped_at?->toIso8601String() ?? 'null'));
        $this->line('  actual_delivery_date:     '.($shipment->actual_delivery_date?->toIso8601String() ?? 'null'));
        $this->line('  last_tracking_synced_at:  '.($shipment->last_tracking_synced_at?->toIso8601String() ?? 'null'));
    }

    protected function printOrder(?Order $order): void
    {
        $this->line('');
        $this->info('Order');
        if (! $order) {
            $this->line('  (none)');

            return;
        }

        $this->line('  id:                   '.$order->id);
        $this->line('  marketplace:          '.($order->marketplace ?: 'null'));
        $this->line('  marketplace_order_id: '.($order->marketplace_order_id ?: 'null'));
        $this->line('  status:               '.$order->status);
        $this->line('  shipped_at:           '.($order->shipped_at?->toIso8601String() ?? 'null'));
        $this->line('  delivered_at:         '.($order->delivered_at?->toIso8601String() ?? 'null'));
    }

    protected function inspectEbay(Shipment $shipment, EbayTrackingAdapter $adapter): void
    {
        $order = $shipment->order;
        $this->line('');
        $this->info('eBay adapter context');

        $credential = IntegrationCredential::where('organization_id', $order->organization_id)
            ->where('platform', 'ebay')
            ->where('is_active', true)
            ->first();

        if (! $credential) {
            $this->warn('  No active eBay IntegrationCredential for organization '.$order->organization_id);

            return;
        }

        $this->line('  credential_id:        '.$credential->id);
        $this->line('  environment:          '.($credential->environment ?: 'null'));

        try {
            $token = $credential->getActiveEbayToken();
            if (! $token) {
                $this->warn('  active OAuth token: NONE — re-run eBay OAuth for this credential');
            } else {
                $this->line('  active OAuth token:   id='.$token->id);
                $this->line('    expires_at:         '.($token->expires_at?->toIso8601String() ?? 'null'));
                $this->line('    is_active:          '.($token->is_active ? 'true' : 'false'));
                $this->line('    needsRefresh:       '.($token->needsRefresh() ? 'true' : 'false'));
            }
        } catch (\Throwable $e) {
            $this->warn('  token resolution threw: '.$e->getMessage());
        }

        $cacheKey = $adapter->cacheKeyFor((string) $order->marketplace_order_id);
        $this->line('  cache_key:            '.$cacheKey);
        $cachedBody = Cache::get($cacheKey);
        $this->line('  cached_body:          '.($cachedBody ? strlen($cachedBody).' bytes' : 'EMPTY'));

        if ($this->option('no-cache')) {
            Cache::forget($cacheKey);
            $this->warn('  --no-cache: forgot cache key, next fetch will re-call Trading API.');
        }
    }

    protected function printResult($result): void
    {
        $this->line('');
        $this->info('TrackingResult');
        $this->line('  isEmpty():              '.($result->isEmpty() ? 'true' : 'false'));
        $this->line('  latestStatus:           '.($result->latestStatus ?? 'null'));
        $this->line('  deliveredAt:            '.($result->deliveredAt?->format('c') ?? 'null'));
        $this->line('  estimatedDeliveryAt:    '.($result->estimatedDeliveryAt?->format('c') ?? 'null'));
        $this->line('  events:                 '.count($result->events).' event(s)');

        foreach ($result->events as $i => $event) {
            $this->line(sprintf(
                '    [%d] %s @ %s — %s',
                $i,
                $event['status'],
                $event['occurred_at']?->format('c') ?? 'null',
                $event['description'] ?? '(no description)'
            ));
        }
    }
}
