<?php

namespace App\Jobs\Inventory;

use App\Models\Ebay\EbayListing;
use App\Models\Integration\IntegrationCredential;
use App\Models\Shopify\ShopifyListing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcileMarketplaceInventory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(protected ?int $organizationId = null) {}

    public function handle(): void
    {
        $query = IntegrationCredential::where('is_active', true)
            ->where('auto_sync_enabled', true);

        if ($this->organizationId) {
            $query->where('organization_id', $this->organizationId);
        }

        $credentials = $query->get();

        $totalDiscrepancies = 0;
        $totalCorrected = 0;

        foreach ($credentials as $credential) {
            $result = match ($credential->platform) {
                'ebay' => $this->reconcileEbay($credential),
                'shopify' => $this->reconcileShopify($credential),
                default => ['discrepancies' => 0, 'corrected' => 0],
            };

            $totalDiscrepancies += $result['discrepancies'];
            $totalCorrected += $result['corrected'];
        }

        Log::info('ReconcileMarketplaceInventory: completed', [
            'organization_id' => $this->organizationId ?? 'all',
            'total_discrepancies' => $totalDiscrepancies,
            'total_corrected' => $totalCorrected,
        ]);
    }

    protected function reconcileEbay(IntegrationCredential $credential): array
    {
        $discrepancies = 0;
        $corrected = 0;

        $listings = EbayListing::where('integration_credential_id', $credential->id)
            ->where('status', 'published')
            ->with('product:id,available_quantity,sku')
            ->get();

        foreach ($listings as $listing) {
            if (! $listing->product) {
                continue;
            }

            $expected = (int) $listing->product->available_quantity;
            $listingQty = (int) $listing->available_quantity;

            if ($expected !== $listingQty) {
                $discrepancies++;

                DB::table('inventory_reconciliation_logs')->insert([
                    'organization_id' => $credential->organization_id,
                    'product_id' => $listing->product_id,
                    'marketplace' => 'ebay',
                    'integration_credential_id' => $credential->id,
                    'expected_qty' => $expected,
                    'listing_qty' => $listingQty,
                    'discrepancy' => $expected - $listingQty,
                    'auto_corrected' => true,
                    'resolved' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                PushInventoryToMarketplaces::dispatch($listing->product_id);
                $corrected++;

                Log::warning('Inventory discrepancy detected and corrected', [
                    'product_id' => $listing->product_id,
                    'sku' => $listing->product->sku,
                    'marketplace' => 'ebay',
                    'expected' => $expected,
                    'listing_qty' => $listingQty,
                ]);
            }
        }

        return ['discrepancies' => $discrepancies, 'corrected' => $corrected];
    }

    protected function reconcileShopify(IntegrationCredential $credential): array
    {
        $discrepancies = 0;
        $corrected = 0;

        $listings = ShopifyListing::where('integration_credential_id', $credential->id)
            ->where('status', 'active')
            ->with('product:id,available_quantity,sku')
            ->get();

        foreach ($listings as $listing) {
            if (! $listing->product) {
                continue;
            }

            $expected = (int) $listing->product->available_quantity;
            $listingQty = (int) $listing->inventory_quantity;

            if ($expected !== $listingQty) {
                $discrepancies++;

                DB::table('inventory_reconciliation_logs')->insert([
                    'organization_id' => $credential->organization_id,
                    'product_id' => $listing->product_id,
                    'marketplace' => 'shopify',
                    'integration_credential_id' => $credential->id,
                    'expected_qty' => $expected,
                    'listing_qty' => $listingQty,
                    'discrepancy' => $expected - $listingQty,
                    'auto_corrected' => true,
                    'resolved' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                PushInventoryToMarketplaces::dispatch($listing->product_id);
                $corrected++;

                Log::warning('Inventory discrepancy detected and corrected', [
                    'product_id' => $listing->product_id,
                    'sku' => $listing->product->sku,
                    'marketplace' => 'shopify',
                    'expected' => $expected,
                    'listing_qty' => $listingQty,
                ]);
            }
        }

        return ['discrepancies' => $discrepancies, 'corrected' => $corrected];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ReconcileMarketplaceInventory: job failed', [
            'organization_id' => $this->organizationId ?? 'all',
            'error' => $exception->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return ['inventory', 'reconciliation'];
    }
}
