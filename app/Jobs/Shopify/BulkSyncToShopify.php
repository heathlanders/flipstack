<?php

namespace App\Jobs\Shopify;

use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BulkSyncToShopify implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        protected array $productIds,
        protected int $credentialId,
        protected int $teamId,
        protected bool $forceUpdate = false
    ) {}

    public function handle(): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $credential = IntegrationCredential::find($this->credentialId);

        if (! $credential || ! $credential->is_active) {
            return;
        }

        $products = Product::whereIn('id', $this->productIds)
            ->where('status', 'active')
            ->get();

        foreach ($products as $index => $product) {
            SyncProductToShopify::dispatch($product, $credential, $this->forceUpdate)
                ->delay(now()->addSeconds($index * 2));
        }

        Log::info('BulkSyncToShopify: Dispatched individual sync jobs', [
            'count' => $products->count(),
            'credential_id' => $this->credentialId,
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('BulkSyncToShopify: Job failed', [
            'product_ids' => $this->productIds,
            'error' => $exception?->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return [
            'shopify',
            'bulk-sync',
            'team:'.$this->teamId,
        ];
    }
}
