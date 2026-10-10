<?php

use App\Jobs\Amazon\PullFbaInventoryJob;
use App\Jobs\Amazon\SyncAmazonOrderFinancials;
use App\Jobs\Amazon\UpdateAmazonBuyBoxPrices;
use App\Jobs\Analytics\AggregateAuditDerivedKPIs;
use App\Jobs\Analytics\AggregateDailyKPIs;
use App\Jobs\Analytics\SendScheduledReports;
use App\Jobs\Communication\SyncMarketplaceMessages;
use App\Jobs\Ebay\ProcessEbayScheduledTasks;
use App\Jobs\Ebay\ReleaseUnpaidOrders;
use App\Jobs\Fulfillment\AutoAssignOrders;
use App\Jobs\Fulfillment\CheckDropshipSLA;
use App\Jobs\Fulfillment\CheckStaleShipments;
use App\Jobs\Fulfillment\CreateSettlementItems;
use App\Jobs\Fulfillment\PullEbayTracking;
use App\Jobs\Fulfillment\SyncAllActiveShipmentTracking;
use App\Jobs\Fulfillment\SyncAmazonOrders;
use App\Jobs\Fulfillment\SyncEbayOrders;
use App\Jobs\Fulfillment\SyncShopifyOrders;
use App\Jobs\Inventory\ReconcileMarketplaceInventory;
use App\Jobs\Marketplace\ProcessScheduledListings;
use App\Jobs\RefreshOAuthTokens;
use App\Jobs\Shopify\RetryFailedShopifySync;
use App\Jobs\Supplier\EvaluateSupplierPerformance;
use App\Jobs\Supplier\ProcessDailySupplierMetrics;
use App\Jobs\Supplier\ProcessSettlements;
use App\Jobs\Supplier\SendTierChangeNotifications;
use App\Models\Organization\Organization;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// eBay Scheduled Jobs
Schedule::job(new ProcessEbayScheduledTasks('update_stale'))
    ->everySixHours()
    ->name('ebay-update-stale')
    ->withoutOverlapping();

Schedule::job(new ProcessEbayScheduledTasks('retry_failed'))
    ->everyThirtyMinutes()
    ->name('ebay-retry-failed')
    ->withoutOverlapping();

Schedule::job(new ProcessEbayScheduledTasks('sync_quantities'))
    ->hourly()
    ->name('ebay-sync-quantities')
    ->withoutOverlapping();

Schedule::job(new ProcessEbayScheduledTasks('cleanup_logs'))
    ->dailyAt('03:00')
    ->name('ebay-cleanup-logs')
    ->withoutOverlapping();

// OAuth Token Refresh - Run every 15 minutes to refresh tokens expiring soon
Schedule::job(new RefreshOAuthTokens)
    ->everyFifteenMinutes()
    ->name('oauth-token-refresh')
    ->withoutOverlapping();

// Supplier Workflow Jobs - Process for all organizations
Schedule::call(function () {
    // Process daily metrics for all orgs (null = all)
    ProcessDailySupplierMetrics::dispatch(null, null);
})->dailyAt('02:00')->name('supplier-daily-metrics')->withoutOverlapping();

Schedule::call(function () {
    // Evaluate performance for each organization
    Organization::all()->each(function ($org) {
        EvaluateSupplierPerformance::dispatch($org->id);
    });
})->dailyAt('03:30')->name('supplier-evaluate-performance')->withoutOverlapping();

Schedule::call(function () {
    // Process settlements for each organization
    Organization::all()->each(function ($org) {
        ProcessSettlements::dispatch($org->id, false);
    });
})->dailyAt('04:00')->name('supplier-process-settlements')->withoutOverlapping();

Schedule::call(function () {
    // Send tier notifications (null = all orgs)
    SendTierChangeNotifications::dispatch(null, null);
})->everyFifteenMinutes()->name('supplier-tier-notifications')->withoutOverlapping();

// Shopify Scheduled Jobs
Schedule::job(new RetryFailedShopifySync)
    ->everyThirtyMinutes()
    ->name('shopify-retry-failed')
    ->withoutOverlapping();

// Fulfillment Scheduled Jobs
Schedule::job(new SyncEbayOrders)
    ->everyFiveMinutes()
    ->name('fulfillment-sync-ebay-orders')
    ->withoutOverlapping();

Schedule::job(new PullEbayTracking)
    ->everyFifteenMinutes()
    ->name('fulfillment-pull-ebay-tracking')
    ->withoutOverlapping();

Schedule::job(new SyncAllActiveShipmentTracking)
    ->everyThirtyMinutes()
    ->name('fulfillment-sync-shipment-tracking')
    ->withoutOverlapping();

Schedule::job(new SyncShopifyOrders)
    ->everyFiveMinutes()
    ->name('fulfillment-sync-shopify-orders')
    ->withoutOverlapping();

Schedule::job(new SyncAmazonOrders)
    ->everyFiveMinutes()
    ->name('fulfillment-sync-amazon-orders')
    ->withoutOverlapping();

Schedule::job(new PullFbaInventoryJob)
    ->hourly()
    ->name('amazon-pull-fba-inventory')
    ->withoutOverlapping();

Schedule::job(new SyncAmazonOrderFinancials)
    ->hourly()
    ->name('amazon-sync-order-financials')
    ->withoutOverlapping();

Schedule::job(new UpdateAmazonBuyBoxPrices)
    ->everyThirtyMinutes()
    ->name('amazon-update-buy-box-prices')
    ->withoutOverlapping();

Schedule::job(new AutoAssignOrders)
    ->everyTenMinutes()
    ->name('fulfillment-auto-assign-orders')
    ->withoutOverlapping();

Schedule::job(new CheckDropshipSLA)
    ->hourly()
    ->name('fulfillment-check-dropship-sla')
    ->withoutOverlapping();

Schedule::job(new CheckStaleShipments)
    ->everySixHours()
    ->name('fulfillment-check-stale-shipments')
    ->withoutOverlapping();

Schedule::job(new CreateSettlementItems)
    ->dailyAt('05:00')
    ->name('fulfillment-create-settlement-items')
    ->withoutOverlapping();

// Marketplace Scheduled Listings - Check for due listings every 5 minutes
Schedule::job(new ProcessScheduledListings)
    ->everyFiveMinutes()
    ->name('marketplace-process-scheduled-listings')
    ->withoutOverlapping();

// Inventory Reconciliation - Detect and correct marketplace quantity drift
Schedule::job(new ReconcileMarketplaceInventory)
    ->dailyAt('06:00')
    ->name('inventory-reconcile-marketplace')
    ->withoutOverlapping();

// Release unpaid eBay orders - Free inventory locked by unpaid orders
Schedule::job(new ReleaseUnpaidOrders(48))
    ->everySixHours()
    ->name('ebay-release-unpaid-orders')
    ->withoutOverlapping();

// Marketplace Message Sync - Pull buyer messages from eBay
Schedule::call(function () {
    Organization::all()->each(function ($org) {
        SyncMarketplaceMessages::dispatch($org->id);
    });
})->everyFifteenMinutes()->name('communication-sync-marketplace-messages')->withoutOverlapping();

// Analytics KPI Aggregation - Nightly rollup of daily metrics
Schedule::call(function () {
    $yesterday = now()->subDay()->toDateString();
    Organization::all()->each(function ($org) use ($yesterday) {
        AggregateDailyKPIs::dispatch($org->id, $yesterday);
    });
})->dailyAt('02:00')->name('analytics-aggregate-daily-kpis')->withoutOverlapping();

// Audit-derived KPI Aggregation - Counts user_activity_logs action prefixes into user_productivity_daily.
// Runs at 02:15 so it lands after AggregateDailyKPIs but still in the early-morning quiet window.
Schedule::call(function () {
    $yesterday = now()->subDay()->toDateString();
    Organization::all()->each(function ($org) use ($yesterday) {
        AggregateAuditDerivedKPIs::dispatch((int) $org->id, $yesterday);
    });
})->dailyAt('02:15')->name('analytics-aggregate-audit-derived-kpis')->withoutOverlapping();

// Scheduled report email digests - sweep due reports every 15 minutes
Schedule::job(new SendScheduledReports)
    ->everyFifteenMinutes()
    ->name('analytics-send-scheduled-reports')
    ->withoutOverlapping();

// Public demo instance self-heals overnight (APP_DEMO=true only)
if (config('app.demo')) {
    Schedule::command('demo:reset --force')
        ->dailyAt('09:00')
        ->name('demo-nightly-reset')
        ->withoutOverlapping();
}
