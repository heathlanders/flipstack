<?php

namespace App\Jobs\Amazon;

use App\Models\Fulfillment\Order;
use App\Models\Integration\IntegrationCredential;
use App\Models\Marketplace\MarketplaceFee;
use App\Services\Amazon\AmazonOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backfill Amazon seller fees for recently-imported orders.
 *
 * Amazon's Orders API does NOT include fees. Real referral/FBA/closing
 * fees become available 5 min - 24h after order placement via the
 * Finances API. This job sweeps recent orders and fills them in.
 */
class SyncAmazonOrderFinancials implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public ?int $credentialId = null, public int $lookbackHours = 48) {}

    public function handle(): void
    {
        $credentials = $this->credentialId
            ? IntegrationCredential::where('id', $this->credentialId)->get()
            : IntegrationCredential::where('platform', 'amazon')
                ->where('is_active', true)
                ->get();

        foreach ($credentials as $credential) {
            if (! $credential->isAmazonReady()) {
                continue;
            }

            $this->syncCredential($credential);
        }
    }

    protected function syncCredential(IntegrationCredential $credential): void
    {
        $orders = Order::where('marketplace', 'amazon')
            ->where('credential_id', $credential->id)
            ->where('created_at', '>=', now()->subHours($this->lookbackHours))
            ->where(function ($q) {
                $q->whereNull('marketplace_fees')
                    ->orWhere('marketplace_fees', 0);
            })
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        $service = new AmazonOrderService($credential);

        foreach ($orders as $order) {
            try {
                $events = $service->getOrderFinancialEvents($order->marketplace_order_id);
                $this->applyFinancialEvents($order, $events);
            } catch (\Throwable $e) {
                Log::warning('SyncAmazonOrderFinancials: per-order fetch failed', [
                    'order_id' => $order->id,
                    'amazon_order_id' => $order->marketplace_order_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function applyFinancialEvents(Order $order, array $events): void
    {
        $fees = $this->extractFees($events);

        if (empty($fees)) {
            return;
        }

        DB::transaction(function () use ($order, $fees) {
            $totalFees = 0;

            foreach ($fees as $fee) {
                MarketplaceFee::updateOrCreate(
                    [
                        'organization_id' => $order->organization_id,
                        'order_id' => $order->id,
                        'marketplace' => 'amazon',
                        'fee_type' => $fee['type'],
                    ],
                    [
                        'amount' => $fee['amount'],
                        'currency' => $fee['currency'],
                        'description' => $fee['description'],
                        'fee_date' => $order->created_at?->toDateString() ?? now()->toDateString(),
                    ]
                );

                $totalFees += $fee['amount'];
            }

            $order->update(['marketplace_fees' => round($totalFees, 2)]);
        });
    }

    /**
     * Walk the FinancialEvents response and aggregate ChargeType fees per
     * order. Only ShipmentEventList entries map to seller-paid fees.
     */
    protected function extractFees(array $events): array
    {
        $shipments = $events['ShipmentEventList'] ?? [];
        $aggregated = [];

        foreach ($shipments as $shipment) {
            foreach ($shipment['ShipmentItemList'] ?? [] as $item) {
                foreach ($item['ItemFeeList'] ?? [] as $fee) {
                    $type = $this->mapFeeType($fee['FeeType'] ?? '');
                    $amount = abs((float) ($fee['FeeAmount']['CurrencyAmount'] ?? 0));
                    $currency = $fee['FeeAmount']['CurrencyCode'] ?? 'USD';

                    if ($amount === 0.0) {
                        continue;
                    }

                    $key = $type;
                    $aggregated[$key] = [
                        'type' => $type,
                        'amount' => round(($aggregated[$key]['amount'] ?? 0) + $amount, 2),
                        'currency' => $currency,
                        'description' => $fee['FeeType'] ?? $type,
                    ];
                }
            }
        }

        return array_values($aggregated);
    }

    protected function mapFeeType(string $amazonFeeType): string
    {
        return match (strtolower($amazonFeeType)) {
            'commission', 'referralfee' => MarketplaceFee::TYPE_REFERRAL,
            'fbaperunitfulfillmentfee', 'fbaweightbasedfee', 'fbaperorderfulfillmentfee' => MarketplaceFee::TYPE_FBA,
            'variableclosingfee', 'fixedclosingfee' => MarketplaceFee::TYPE_CLOSING,
            'shippingchargeback' => MarketplaceFee::TYPE_SHIPPING_LABEL,
            default => MarketplaceFee::TYPE_OTHER,
        };
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncAmazonOrderFinancials failed', [
            'credential_id' => $this->credentialId,
            'error' => $exception->getMessage(),
        ]);
    }
}
