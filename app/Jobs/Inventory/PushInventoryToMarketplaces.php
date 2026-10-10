<?php

namespace App\Jobs\Inventory;

use App\Models\Catalog\Product;
use App\Models\Ebay\EbaySyncLog;
use App\Models\Shopify\ShopifySyncLog;
use App\Models\User\User;
use App\Notifications\Inventory\MarketplaceInventorySyncFailedNotification;
use App\Services\MarketplaceInventorySync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushInventoryToMarketplaces implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 120;

    public bool $deleteWhenMissingModels = true;

    public function __construct(protected string $productId)
    {
        $this->onQueue('default');
    }

    public function handle(MarketplaceInventorySync $sync): void
    {
        $product = Product::find($this->productId);

        if (! $product) {
            return;
        }

        Log::info('PushInventoryToMarketplaces: pushing quantity to all marketplaces', [
            'product_id' => $product->id,
            'available_quantity' => $product->available_quantity,
        ]);

        $sync->pushQuantityToAll($product);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('PushInventoryToMarketplaces: failed to push inventory', [
            'product_id' => $this->productId,
            'error' => $exception->getMessage(),
        ]);

        $product = Product::find($this->productId);

        if (! $product) {
            return;
        }

        // Log to eBay sync logs for dashboard visibility
        foreach ($product->ebayListings()->where('status', 'published')->get() as $listing) {
            EbaySyncLog::create([
                'integration_credential_id' => $listing->integration_credential_id,
                'team_id' => $listing->team_id,
                'product_id' => $product->id,
                'operation' => 'update_inventory',
                'direction' => EbaySyncLog::DIRECTION_OUTBOUND,
                'ebay_item_id' => $listing->ebay_listing_id,
                'ebay_sku' => $listing->ebay_sku,
                'status' => EbaySyncLog::STATUS_ERROR,
                'error_message' => $exception->getMessage(),
                'started_at' => now(),
                'completed_at' => now(),
            ]);
        }

        // Notify org admins of inventory sync failure
        $admins = User::where('organization_id', $product->organization_id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'owner']))
            ->get();

        $notification = new MarketplaceInventorySyncFailedNotification(
            productName: $product->name ?? 'Unknown Product',
            productId: (string) $product->id,
            marketplace: 'all marketplaces',
            errorMessage: $exception->getMessage(),
        );

        foreach ($admins as $admin) {
            $admin->notify($notification);
        }

        // Log to Shopify sync logs for dashboard visibility
        foreach ($product->shopifyListings()->where('status', 'active')->get() as $listing) {
            ShopifySyncLog::create([
                'integration_credential_id' => $listing->integration_credential_id,
                'product_id' => $product->id,
                'operation' => 'set_inventory',
                'direction' => 'outbound',
                'status' => 'error',
                'error_message' => $exception->getMessage(),
                'started_at' => now(),
                'completed_at' => now(),
            ]);
        }
    }
}
