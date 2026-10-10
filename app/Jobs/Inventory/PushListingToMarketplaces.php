<?php

namespace App\Jobs\Inventory;

use App\Models\Catalog\Product;
use App\Services\MarketplaceListingSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushListingToMarketplaces implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $uniqueFor = 60;

    public bool $deleteWhenMissingModels = true;

    public function __construct(protected string $productId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return $this->productId;
    }

    public function handle(MarketplaceListingSync $sync): void
    {
        $product = Product::find($this->productId);

        if (! $product) {
            return;
        }

        Log::info('PushListingToMarketplaces: pushing listing details to all marketplaces', [
            'product_id' => $product->id,
            'product_name' => $product->name,
        ]);

        $sync->pushListingToAll($product);
    }
}
