<?php

namespace App\Http\Controllers\Marketplaces;

use App\Http\Controllers\Controller;
use App\Jobs\Ebay\BulkSyncToEbay;
use App\Jobs\Ebay\ImportEbayInventory;
use App\Jobs\Ebay\ImportEbayInventoryFromCsv;
use App\Jobs\Ebay\RetryFailedEbaySync;
use App\Jobs\Ebay\SyncProductToEbay;
use App\Jobs\Ebay\UpdateEbayInventory;
use App\Models\Catalog\Product;
use App\Models\Ebay\EbayListing;
use App\Models\Ebay\EbaySyncLog;
use App\Models\Integration\IntegrationCredential;
use App\Models\Inventory\InventoryLocation;
use App\Models\System\BulkOperationJob;
use App\Services\Ebay\EbayCsvParser;
use App\Services\EbayApiException;
use App\Services\EbayConfigService;
use App\Services\EbayInventoryService;
use App\Services\EbayStoreImportService;
use App\Services\SkuParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EbayMarketplaceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $credential = $this->getCredential($user->organization_id);
        $integrationStatus = $this->getIntegrationStatus($credential);
        $stats = $this->getListingStats($credential);
        $lastImport = $this->getLastImport($user->organization_id);

        return Inertia::render('Marketplaces/Ebay/Index', [
            'integrationStatus' => $integrationStatus,
            'stats' => $stats,
            'lastImport' => $lastImport,
        ]);
    }

    public function setup(Request $request, EbayConfigService $ebayConfig): Response
    {
        try {
            $user = $request->user();
            $this->authorizeAdmin($user);

            $credential = $this->getCredential($user->organization_id);

            $policies = null;
            $ebayLocations = null;

            if ($credential && $credential->getActiveEbayToken()) {
                try {
                    $service = new EbayInventoryService($credential);
                    $policies = $service->getBusinessPolicies();

                    if ($credential->merchant_location_key) {
                        $ebayLocations = [$service->getLocation($credential->merchant_location_key)];
                    }
                } catch (\Exception $e) {
                    // Policies might not be available
                }
            }

            // Get inventory locations with address data for merchant location sync
            $inventoryLocations = InventoryLocation::where('organization_id', $user->organization_id)
                ->where('is_active', true)
                ->whereNotNull('address_line1')
                ->whereNotNull('city')
                ->whereNotNull('state')
                ->whereNotNull('postal_code')
                ->select('id', 'name', 'code', 'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country')
                ->orderBy('name')
                ->get();

            return Inertia::render('Marketplaces/Ebay/Setup', [
                'credential' => $credential ? [
                    'id' => $credential->id,
                    'environment' => $credential->environment,
                    'marketplace_id' => $credential->marketplace_id ?? 'EBAY_US',
                    'merchant_location_key' => $credential->merchant_location_key,
                    'fulfillment_policy_id' => $credential->fulfillment_policy_id,
                    'payment_policy_id' => $credential->payment_policy_id,
                    'return_policy_id' => $credential->return_policy_id,
                    'has_token' => $credential->getActiveEbayToken() !== null,
                    'seller_username' => $credential->seller_username,
                    'account_username' => $credential->seller_username ?? ($credential->getActiveEbayToken()?->metadata ?? [])['username'] ?? null,
                ] : null,
                'policies' => $policies,
                'locations' => $ebayLocations,
                'inventoryLocations' => $inventoryLocations,
                'ebayConfig' => [
                    'configured' => $ebayConfig->isConfigured(),
                    'environment' => $ebayConfig->getEnvironment(),
                    'is_sandbox' => $ebayConfig->isSandbox(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('eBay setup page failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('Marketplaces/Ebay/Setup', [
                'credential' => null,
                'policies' => null,
                'locations' => null,
                'inventoryLocations' => [],
                'ebayConfig' => [
                    'configured' => $ebayConfig->isConfigured(),
                    'environment' => $ebayConfig->getEnvironment(),
                    'is_sandbox' => $ebayConfig->isSandbox(),
                ],
            ]);
        }
    }

    public function saveSetup(Request $request)
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return back()->withErrors(['ebay' => 'No eBay integration configured.']);
        }

        $validated = $request->validate([
            'marketplace_id' => 'nullable|string|max:20',
            'fulfillment_policy_id' => 'nullable|string|max:50',
            'payment_policy_id' => 'nullable|string|max:50',
            'return_policy_id' => 'nullable|string|max:50',
            'merchant_location_key' => 'nullable|string|max:50',
        ]);

        $credential->update($validated);

        return back()->with('success', 'Configuration saved successfully.');
    }

    public function import(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $credential = $this->getCredential($user->organization_id);
        $integrationStatus = $this->getIntegrationStatus($credential);

        $activeImport = BulkOperationJob::where('organization_id', $user->organization_id)
            ->whereIn('operation_type', [BulkOperationJob::TYPE_EBAY_IMPORT, BulkOperationJob::TYPE_EBAY_CSV_IMPORT])
            ->whereIn('status', [BulkOperationJob::STATUS_PENDING, BulkOperationJob::STATUS_PROCESSING])
            ->first();

        $lastCompleted = BulkOperationJob::where('organization_id', $user->organization_id)
            ->whereIn('operation_type', [BulkOperationJob::TYPE_EBAY_IMPORT, BulkOperationJob::TYPE_EBAY_CSV_IMPORT])
            ->whereIn('status', [BulkOperationJob::STATUS_COMPLETED, BulkOperationJob::STATUS_FAILED])
            ->orderBy('completed_at', 'desc')
            ->first();

        $locations = InventoryLocation::where('organization_id', $user->organization_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return Inertia::render('Marketplaces/Ebay/Import', [
            'integrationStatus' => $integrationStatus,
            'activeImport' => $activeImport ? $this->formatBulkJob($activeImport) : null,
            'lastCompleted' => $lastCompleted ? $this->formatBulkJob($lastCompleted) : null,
            'credentialId' => $credential?->id,
            'skuParserConfig' => $credential?->config['sku_parser'] ?? null,
            'skuParsers' => SkuParserService::normalizeParsers($credential?->config ?? []),
            'locations' => $locations,
        ]);
    }

    public function startImport(Request $request)
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return back()->withErrors(['ebay' => 'No eBay integration configured.']);
        }

        if (! $credential->getActiveEbayToken()) {
            return back()->withErrors(['ebay' => 'eBay account is not connected. Please connect your account first.']);
        }

        // Check for running import
        $running = BulkOperationJob::where('organization_id', $user->organization_id)
            ->whereIn('operation_type', [BulkOperationJob::TYPE_EBAY_IMPORT, BulkOperationJob::TYPE_EBAY_CSV_IMPORT])
            ->whereIn('status', [BulkOperationJob::STATUS_PENDING, BulkOperationJob::STATUS_PROCESSING])
            ->exists();

        if ($running) {
            return back()->withErrors(['ebay' => 'An import is already in progress.']);
        }

        $request->validate([
            'default_status' => 'nullable|in:draft,active,inactive',
            'location_id' => [
                'nullable',
                'uuid',
                \Illuminate\Validation\Rule::exists('inventory_locations', 'id')
                    ->where('organization_id', $user->organization_id),
            ],
        ]);

        $options = [
            'skip_existing_skus' => $request->boolean('skip_existing_skus', true),
            'update_existing_products' => $request->boolean('update_existing_products', false),
            'default_status' => $request->input('default_status', 'draft'),
            'preserve_price' => $request->boolean('preserve_price', false),
            'preserve_description' => $request->boolean('preserve_description', false),
            'location_id' => $request->input('location_id'),
        ];

        $bulkJob = BulkOperationJob::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'operation_type' => BulkOperationJob::TYPE_EBAY_IMPORT,
            'status' => BulkOperationJob::STATUS_PENDING,
            'total_items' => 0,
            'processed_items' => 0,
            'failed_items' => 0,
            'parameters' => $options,
        ]);

        ImportEbayInventory::dispatch(
            $credential->id,
            $user->organization_id,
            $user->id,
            $bulkJob->id,
            $options
        );

        return back()->with('success', 'Import started. You can track the progress on this page.');
    }

    public function startCsvImport(Request $request)
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return back()->withErrors(['ebay' => 'No eBay integration configured.']);
        }

        $running = BulkOperationJob::where('organization_id', $user->organization_id)
            ->whereIn('operation_type', [BulkOperationJob::TYPE_EBAY_IMPORT, BulkOperationJob::TYPE_EBAY_CSV_IMPORT])
            ->whereIn('status', [BulkOperationJob::STATUS_PENDING, BulkOperationJob::STATUS_PROCESSING])
            ->exists();

        if ($running) {
            return back()->withErrors(['ebay' => 'An import is already in progress.']);
        }

        $request->validate([
            'csv' => 'required|file|max:51200|mimes:csv,txt',
            'default_status' => 'nullable|in:draft,active,inactive',
            'location_id' => [
                'nullable',
                'uuid',
                \Illuminate\Validation\Rule::exists('inventory_locations', 'id')
                    ->where('organization_id', $user->organization_id),
            ],
        ]);

        $file = $request->file('csv');

        $header = EbayCsvParser::readHeader($file->getRealPath());

        if ($header === null || ! EbayCsvParser::validateHeader($header)) {
            return back()->withErrors([
                'csv' => 'CSV is missing required columns. Expected: '.implode(', ', EbayCsvParser::REQUIRED_HEADERS).'.',
            ]);
        }

        $relativeDir = 'ebay-csv-imports/'.$user->organization_id;
        $filename = (string) Str::ulid().'.csv';
        $relativePath = $file->storeAs($relativeDir, $filename, 'local');

        if ($relativePath === false || ! Storage::disk('local')->exists($relativePath)) {
            return back()->withErrors(['csv' => 'Failed to store uploaded CSV.']);
        }

        $absolutePath = Storage::disk('local')->path($relativePath);
        $totalRows = EbayCsvParser::countDataRows($absolutePath);

        if ($totalRows === 0) {
            Storage::disk('local')->delete($relativePath);

            return back()->withErrors(['csv' => 'CSV has no data rows.']);
        }

        $options = [
            'source' => 'csv',
            'csv_path' => $relativePath,
            'csv_filename' => $file->getClientOriginalName(),
            'default_status' => $request->input('default_status', 'draft'),
            'skip_existing_skus' => true,
            'update_existing_products' => false,
            'location_id' => $request->input('location_id'),
        ];

        $bulkJob = BulkOperationJob::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'operation_type' => BulkOperationJob::TYPE_EBAY_CSV_IMPORT,
            'status' => BulkOperationJob::STATUS_PENDING,
            'total_items' => $totalRows,
            'processed_items' => 0,
            'failed_items' => 0,
            'parameters' => $options,
        ]);

        ImportEbayInventoryFromCsv::dispatch(
            $credential->id,
            $user->organization_id,
            $user->id,
            $bulkJob->id,
            $relativePath,
            $options
        );

        return back()->with('success', "CSV import started ({$totalRows} rows). Track progress on this page.");
    }

    public function importStatus(Request $request)
    {
        $user = $request->user();

        $activeImport = BulkOperationJob::where('organization_id', $user->organization_id)
            ->whereIn('operation_type', [BulkOperationJob::TYPE_EBAY_IMPORT, BulkOperationJob::TYPE_EBAY_CSV_IMPORT])
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $activeImport) {
            return response()->json(['status' => 'none']);
        }

        $data = $this->formatBulkJob($activeImport);

        if ($request->boolean('debug')) {
            $data['debug_log'] = $activeImport->parameters['debug_log'] ?? [];
        }

        return response()->json($data);
    }

    public function cancelImport(Request $request)
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $activeImport = BulkOperationJob::where('organization_id', $user->organization_id)
            ->whereIn('operation_type', [BulkOperationJob::TYPE_EBAY_IMPORT, BulkOperationJob::TYPE_EBAY_CSV_IMPORT])
            ->whereIn('status', [BulkOperationJob::STATUS_PENDING, BulkOperationJob::STATUS_PROCESSING])
            ->first();

        if (! $activeImport) {
            return back()->withErrors(['ebay' => 'No active import to cancel.']);
        }

        $activeImport->markAsCancelled();

        return back()->with('success', 'Import cancelled.');
    }

    public function resumeImport(Request $request)
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $credential = $this->getCredential($user->organization_id);

        if (! $credential || ! $credential->getActiveEbayToken()) {
            return back()->withErrors(['ebay' => 'eBay account is not connected.']);
        }

        // Check no import already running
        $running = BulkOperationJob::where('organization_id', $user->organization_id)
            ->whereIn('operation_type', [BulkOperationJob::TYPE_EBAY_IMPORT, BulkOperationJob::TYPE_EBAY_CSV_IMPORT])
            ->whereIn('status', [BulkOperationJob::STATUS_PENDING, BulkOperationJob::STATUS_PROCESSING])
            ->exists();

        if ($running) {
            return back()->withErrors(['ebay' => 'An import is already in progress.']);
        }

        // Find the most recent failed import with checkpoint data
        $failedImport = BulkOperationJob::where('organization_id', $user->organization_id)
            ->where('operation_type', BulkOperationJob::TYPE_EBAY_IMPORT)
            ->where('status', BulkOperationJob::STATUS_FAILED)
            ->latest()
            ->first();

        if (! $failedImport) {
            return back()->withErrors(['ebay' => 'No failed import found to resume.']);
        }

        $lastPage = $failedImport->parameters['last_completed_page'] ?? null;
        $totalPages = $failedImport->parameters['total_pages'] ?? null;

        if (! $lastPage || ! $totalPages || $lastPage >= $totalPages) {
            return back()->withErrors(['ebay' => 'No checkpoint data available for this import.']);
        }

        $resumePage = $lastPage + 1;

        // Reset the job status to processing so it continues
        $failedImport->update([
            'status' => BulkOperationJob::STATUS_PROCESSING,
        ]);

        $options = collect($failedImport->parameters)
            ->only(['skip_existing_skus', 'update_existing_products', 'default_status', 'preserve_price', 'preserve_description', 'location_id'])
            ->toArray();

        ImportEbayInventory::dispatch(
            $credential->id,
            $user->organization_id,
            $user->id,
            $failedImport->id,
            $options,
            $resumePage
        );

        return back()->with('success', "Import resumed from page {$resumePage} of {$totalPages}.");
    }

    public function importDebug(Request $request)
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $credential = $this->getCredential($user->organization_id);

        // Environment info
        $environment = [
            'app_debug' => config('app.debug'),
            'queue_connection' => config('queue.default'),
            'ebay_environment' => config('services.ebay.environment', 'not set'),
            'app_env' => config('app.env'),
            'ebay_app_id_set' => ! empty(config('services.ebay.app_id')),
            'ebay_cert_id_set' => ! empty(config('services.ebay.cert_id')),
            'ebay_dev_id_set' => ! empty(config('services.ebay.dev_id')),
        ];

        // Credential info
        $credentialInfo = null;
        if ($credential) {
            $token = $credential->getActiveEbayToken();
            $credentialInfo = [
                'id' => $credential->id,
                'environment' => $credential->environment,
                'has_token' => $token !== null,
                'token_is_expired' => $token ? $token->expires_at?->isPast() : null,
                'token_needs_refresh' => $token ? $token->needsRefresh() : null,
                'token_expires_at' => $token?->expires_at?->toISOString(),
                'has_refresh_token' => $token ? ! empty($token->encrypted_refresh_token) : false,
                'seller_username' => $credential->seller_username,
                'is_active' => $credential->is_active,
                'marketplace_id' => $credential->marketplace_id,
                'merchant_location_key' => $credential->merchant_location_key,
            ];
        }

        // Connectivity test
        $connectivity = $this->testEbayConnectivity($credential);

        // Queue health
        $queueHealth = $this->checkQueueHealth($user->organization_id);

        // Recent sync logs
        $recentLogs = EbaySyncLog::where('integration_credential_id', $credential?->id)
            ->where('operation', EbaySyncLog::OP_IMPORT_INVENTORY)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'status' => $log->status,
                'ebay_item_id' => $log->ebay_item_id,
                'error_message' => $log->error_message,
                'created_at' => $log->created_at->toISOString(),
                'duration' => $log->duration,
            ]);

        // Job history
        $jobHistory = BulkOperationJob::where('organization_id', $user->organization_id)
            ->where('operation_type', BulkOperationJob::TYPE_EBAY_IMPORT)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn ($job) => [
                'id' => $job->id,
                'status' => $job->status,
                'total_items' => $job->total_items,
                'processed_items' => $job->processed_items,
                'failed_items' => $job->failed_items,
                'parameters' => $job->parameters,
                'created_at' => $job->created_at->toISOString(),
                'started_at' => $job->started_at?->toISOString(),
                'completed_at' => $job->completed_at?->toISOString(),
            ]);

        // Failed jobs
        $failedJobs = DB::table('failed_jobs')
            ->where('payload', 'like', '%ImportEbayInventory%')
            ->orderBy('failed_at', 'desc')
            ->take(3)
            ->get()
            ->map(fn ($job) => [
                'id' => $job->id,
                'failed_at' => $job->failed_at,
                'exception_preview' => Str::limit($job->exception, 500),
            ]);

        return response()->json([
            'environment' => $environment,
            'credential' => $credentialInfo,
            'connectivity' => $connectivity,
            'queue_health' => $queueHealth,
            'recent_sync_logs' => $recentLogs,
            'job_history' => $jobHistory,
            'failed_jobs' => $failedJobs,
        ]);
    }

    protected function testEbayConnectivity(?IntegrationCredential $credential): array
    {
        if (! $credential) {
            return ['status' => 'error', 'message' => 'No credential configured'];
        }

        $token = $credential->getActiveEbayToken();
        if (! $token) {
            return ['status' => 'error', 'message' => 'No active token'];
        }

        try {
            $start = microtime(true);
            $service = new EbayStoreImportService($credential);
            $result = $service->fetchActiveListings(1, 1);
            $elapsed = round((microtime(true) - $start) * 1000);

            return [
                'status' => 'ok',
                'response_time_ms' => $elapsed,
                'total_listings' => $result['total_items'] ?? 0,
                'message' => 'Connected successfully',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function checkQueueHealth(int $organizationId): array
    {
        $driver = config('queue.default');

        $pendingImports = BulkOperationJob::where('organization_id', $organizationId)
            ->where('operation_type', BulkOperationJob::TYPE_EBAY_IMPORT)
            ->where('status', BulkOperationJob::STATUS_PENDING)
            ->count();

        $stuckImports = BulkOperationJob::where('organization_id', $organizationId)
            ->where('operation_type', BulkOperationJob::TYPE_EBAY_IMPORT)
            ->where('status', BulkOperationJob::STATUS_PENDING)
            ->where('created_at', '<', now()->subMinutes(5))
            ->count();

        $processingImports = BulkOperationJob::where('organization_id', $organizationId)
            ->where('operation_type', BulkOperationJob::TYPE_EBAY_IMPORT)
            ->where('status', BulkOperationJob::STATUS_PROCESSING)
            ->count();

        return [
            'driver' => $driver,
            'pending_imports' => $pendingImports,
            'stuck_imports' => $stuckImports,
            'processing_imports' => $processingImports,
            'worker_likely_running' => $stuckImports === 0 && ($pendingImports === 0 || $processingImports > 0),
        ];
    }

    public function products(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $credential = $this->getCredential($user->organization_id);

        $query = Product::query()
            ->where('organization_id', $user->organization_id)
            ->readyForEbay();

        if ($credential) {
            $query->notOnEbay($credential->id);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'selling_price' => $product->selling_price,
                'stock_quantity' => $product->stock_quantity,
                'condition' => $product->condition,
                'featured_image' => $product->featured_image,
            ]);

        return Inertia::render('Marketplaces/Ebay/Products', [
            'products' => $products,
            'filters' => [
                'search' => $request->get('search'),
            ],
            'hasCredential' => $credential !== null,
            'isConfigured' => $credential?->isEbayReady() ?? false,
        ]);
    }

    public function syncProduct(Request $request, Product $product)
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return back()->withErrors(['ebay' => 'No eBay integration configured.']);
        }

        if (! $credential->isEbayReady()) {
            return back()->withErrors(['ebay' => 'eBay integration is not fully configured.']);
        }

        $validated = $request->validate([
            'category_id' => 'nullable|string',
        ]);

        SyncProductToEbay::dispatch(
            $product,
            $credential,
            $validated['category_id'] ?? null,
            false,
            $user->id
        );

        return back()->with('success', 'Product sync queued successfully.');
    }

    public function syncBulk(Request $request)
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return back()->withErrors(['ebay' => 'No eBay integration configured.']);
        }

        if (! $credential->isEbayReady()) {
            return back()->withErrors(['ebay' => 'eBay integration is not fully configured.']);
        }

        $validated = $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'uuid|exists:products,id',
            'category_id' => 'nullable|string',
        ]);

        $batchId = BulkSyncToEbay::syncProducts(
            $credential,
            $validated['product_ids'],
            $validated['category_id'] ?? null,
            $user->id
        );

        return back()->with('success', 'Bulk sync queued. Batch ID: '.$batchId);
    }

    public function updateListing(Request $request, EbayListing $listing)
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $credential = $this->getCredential($user->organization_id);
        if (! $credential || $listing->integration_credential_id !== $credential->id) {
            abort(403);
        }

        $validated = $request->validate([
            'update_type' => 'required|in:quantity,price,both',
        ]);

        UpdateEbayInventory::dispatch($listing, $validated['update_type']);

        return back()->with('success', 'Update queued successfully.');
    }

    public function retryListing(Request $request, EbayListing $listing)
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $credential = $this->getCredential($user->organization_id);
        if (! $credential || $listing->integration_credential_id !== $credential->id) {
            abort(403);
        }

        $listing->update([
            'status' => EbayListing::STATUS_PENDING,
            'consecutive_errors' => 0,
        ]);

        SyncProductToEbay::dispatch(
            $listing->product,
            $credential,
            $listing->ebay_category_id,
            false,
            $user->id
        );

        return back()->with('success', 'Retry queued successfully.');
    }

    public function retryAll(Request $request)
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return back()->withErrors(['ebay' => 'No eBay integration configured.']);
        }

        RetryFailedEbaySync::retryForCredential($credential);

        return back()->with('success', 'Retry job queued successfully.');
    }

    public function createLocation(Request $request)
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return back()->withErrors(['ebay' => 'No eBay integration configured.']);
        }

        // If inventory_location_id is provided, sync from existing inventory location
        if ($request->filled('inventory_location_id')) {
            $invLocation = InventoryLocation::where('organization_id', $user->organization_id)
                ->findOrFail($request->inventory_location_id);

            // Generate location key from code or ID
            $locationKey = $invLocation->code ?: 'loc-'.$invLocation->id;
            // Sanitize: eBay location keys must be alphanumeric with dashes/underscores
            $locationKey = preg_replace('/[^a-zA-Z0-9_-]/', '-', $locationKey);

            $locationData = [
                'name' => $invLocation->name,
                'address_line1' => $invLocation->address_line1,
                'address_line2' => $invLocation->address_line2,
                'city' => $invLocation->city,
                'state' => $invLocation->state,
                'postal_code' => $invLocation->postal_code,
                'country' => $invLocation->country ?: 'US',
            ];

            try {
                $service = new EbayInventoryService($credential);
                $service->createLocation($locationKey, $locationData);

                $credential->update(['merchant_location_key' => $locationKey]);

                return back()->with('success', 'Location synced to eBay successfully.');

            } catch (EbayApiException $e) {
                if (str_contains($e->getMessage(), 'already exists')) {
                    $credential->update(['merchant_location_key' => $locationKey]);

                    return back()->with('success', 'Existing eBay location linked successfully.');
                }

                return back()->withErrors(['location' => $e->getUserMessage()]);

            } catch (\Exception $e) {
                return back()->withErrors(['location' => $e->getMessage()]);
            }
        }

        // Manual location creation (original behavior)
        $validated = $request->validate([
            'location_key' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'address_line1' => 'required|string|max:200',
            'address_line2' => 'nullable|string|max:200',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:50',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|size:2',
        ]);

        try {
            $service = new EbayInventoryService($credential);
            $service->createLocation($validated['location_key'], $validated);

            $credential->update(['merchant_location_key' => $validated['location_key']]);

            return back()->with('success', 'Location created successfully.');

        } catch (EbayApiException $e) {
            if (str_contains($e->getMessage(), 'already exists')) {
                $credential->update(['merchant_location_key' => $validated['location_key']]);

                return back()->with('success', 'Existing location linked successfully.');
            }

            return back()->withErrors(['location' => $e->getUserMessage()]);

        } catch (\Exception $e) {
            return back()->withErrors(['location' => $e->getMessage()]);
        }
    }

    public function categorySuggestions(Request $request)
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return response()->json(['suggestions' => []]);
        }

        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json(['suggestions' => []]);
        }

        try {
            $service = new EbayInventoryService($credential);
            $suggestions = $service->getCategorySuggestions($query);

            $formatted = collect($suggestions)->take(10)->map(fn ($s) => [
                'id' => $s['category']['categoryId'] ?? null,
                'name' => $s['category']['categoryName'] ?? null,
                'path' => $s['categoryTreeNodeAncestors'] ?? [],
            ])->filter(fn ($s) => $s['id'] !== null);

            return response()->json(['suggestions' => $formatted->values()]);

        } catch (\Exception $e) {
            return response()->json(['suggestions' => [], 'error' => $e->getMessage()]);
        }
    }

    public function toggleAutoSync(Request $request)
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return back()->withErrors(['ebay' => 'No eBay integration configured.']);
        }

        $credential->update([
            'auto_sync_enabled' => ! $credential->auto_sync_enabled,
        ]);

        $status = $credential->auto_sync_enabled ? 'enabled' : 'disabled';

        return back()->with('success', "Auto-sync {$status}.");
    }

    public function toggleCredentialAutoSync(Request $request, IntegrationCredential $credential)
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        if ($credential->organization_id !== $user->organization_id || $credential->platform !== 'ebay') {
            abort(403);
        }

        $credential->update([
            'auto_sync_enabled' => ! $credential->auto_sync_enabled,
        ]);

        $status = $credential->auto_sync_enabled ? 'enabled' : 'disabled';

        return back()->with('success', "Auto-sync {$status} for {$credential->name}.");
    }

    public function registerNotifications(Request $request, IntegrationCredential $credential)
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        if ($credential->organization_id !== $user->organization_id || $credential->platform !== 'ebay') {
            abort(403);
        }

        try {
            Artisan::call('ebay:register-notifications', [
                '--credential' => $credential->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('eBay notification registration failed', [
                'credential_id' => $credential->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['ebay' => 'Notification registration failed: '.$e->getMessage()]);
        }

        return back()->with('success', 'eBay notifications registered. Webhook is live.');
    }

    public function listings(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $credential = $this->getCredential($user->organization_id);

        $query = EbayListing::where('integration_credential_id', $credential?->id)
            ->with(['product:id,name,sku,selling_price,stock_quantity,featured_image'])
            ->orderBy('updated_at', 'desc');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ebay_sku', 'like', "%{$search}%")
                    ->orWhere('ebay_listing_id', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        $listings = $query->paginate(20)->through(fn ($listing) => [
            'id' => $listing->id,
            'ebay_sku' => $listing->ebay_sku,
            'ebay_listing_id' => $listing->ebay_listing_id,
            'ebay_listing_url' => $listing->ebay_url,
            'status' => $listing->status,
            'status_label' => $listing->status_label,
            'status_color' => $listing->status_color,
            'listed_price' => $listing->listed_price,
            'available_quantity' => $listing->available_quantity,
            'sold_quantity' => $listing->sold_quantity,
            'last_synced_at' => $listing->last_synced_at?->toISOString(),
            'last_error_message' => $listing->last_error_message,
            'product' => $listing->product ? [
                'id' => $listing->product->id,
                'name' => $listing->product->name,
                'sku' => $listing->product->sku,
                'selling_price' => $listing->product->selling_price,
                'stock_quantity' => $listing->product->stock_quantity,
                'featured_image' => $listing->product->featured_image,
            ] : null,
        ]);

        return Inertia::render('Marketplaces/Ebay/Listings', [
            'listings' => $listings,
            'filters' => [
                'status' => $request->get('status'),
                'search' => $request->get('search'),
            ],
            'statuses' => EbayListing::getStatuses(),
        ]);
    }

    public function logs(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $credential = $this->getCredential($user->organization_id);

        $query = EbaySyncLog::where('integration_credential_id', $credential?->id)
            ->with(['product:id,name,sku'])
            ->orderBy('created_at', 'desc');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($operation = $request->get('operation')) {
            $query->where('operation', $operation);
        }

        $logs = $query->paginate(30)->through(fn ($log) => [
            'id' => $log->id,
            'operation' => $log->operation,
            'operation_label' => $log->operation_label,
            'direction' => $log->direction,
            'status' => $log->status,
            'status_color' => $log->status_color,
            'ebay_sku' => $log->ebay_sku,
            'ebay_item_id' => $log->ebay_item_id,
            'error_message' => $log->error_message,
            'duration' => $log->duration,
            'created_at' => $log->created_at->toISOString(),
            'product' => $log->product ? [
                'id' => $log->product->id,
                'name' => $log->product->name,
                'sku' => $log->product->sku,
            ] : null,
        ]);

        $stats = [
            'total' => EbaySyncLog::where('integration_credential_id', $credential?->id)->count(),
            'success' => EbaySyncLog::where('integration_credential_id', $credential?->id)->where('status', 'success')->count(),
            'failed' => EbaySyncLog::where('integration_credential_id', $credential?->id)->where('status', 'error')->count(),
            'pending' => EbaySyncLog::where('integration_credential_id', $credential?->id)->where('status', 'pending')->count(),
        ];

        return Inertia::render('Marketplaces/Ebay/Logs', [
            'logs' => $logs,
            'filters' => [
                'status' => $request->get('status'),
                'operation' => $request->get('operation'),
            ],
            'stats' => $stats,
        ]);
    }

    public function testSkuParser(Request $request)
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $credential = $this->getCredential($user->organization_id);
        if (! $credential) {
            abort(404, 'No eBay credential found');
        }

        $request->validate([
            'config' => 'required|array',
            'sample_sku' => 'required|string|max:500',
        ]);

        $parser = new SkuParserService;
        $result = $parser->test($request->input('sample_sku'), $request->input('config'));

        return response()->json($result);
    }

    public function saveSkuParser(Request $request)
    {
        $user = $request->user();
        $this->authorizeAdmin($user);

        $credential = $this->getCredential($user->organization_id);
        if (! $credential) {
            abort(404, 'No eBay credential found');
        }

        $request->validate([
            'config' => 'required|array',
        ]);

        $parserConfig = $request->input('config');
        $parser = new SkuParserService;

        // Support both single config and array of configs
        $parsers = $request->input('parsers');
        if (is_array($parsers)) {
            // Multi-parser format
            foreach ($parsers as $i => $p) {
                if (! empty($p['enabled'])) {
                    $errors = $parser->validate($p);
                    if (! empty($errors)) {
                        return response()->json(['success' => false, 'errors' => ["Parser {$i}: ".implode(' ', $errors)]], 422);
                    }
                }
            }

            $config = $credential->config ?? [];
            $config['sku_parsers'] = $parsers;
            // Keep old format for backward compat with any other code reading it
            if (! empty($parsers[0])) {
                $config['sku_parser'] = $parsers[0];
            }
            $credential->update(['config' => $config]);

            return response()->json(['success' => true]);
        }

        // Legacy single config format
        if (! empty($parserConfig['enabled'])) {
            $errors = $parser->validate($parserConfig);
            if (! empty($errors)) {
                return response()->json(['success' => false, 'errors' => $errors], 422);
            }
        }

        $config = $credential->config ?? [];
        $config['sku_parser'] = $parserConfig;
        $credential->update(['config' => $config]);

        return response()->json(['success' => true]);
    }

    protected function authorizeAdmin($user): void
    {
        $roles = $user->roles ? $user->roles->pluck('slug')->toArray() : [];
        $allowed = ['super_admin', 'organization_owner', 'admin'];

        if (! array_intersect($roles, $allowed)) {
            abort(403, 'You do not have permission to access this page.');
        }
    }

    protected function getCredential(?int $organizationId): ?IntegrationCredential
    {
        if ($organizationId === null) {
            return null;
        }

        return IntegrationCredential::where('organization_id', $organizationId)
            ->where('platform', 'ebay')
            ->where('is_active', true)
            ->first();
    }

    protected function getIntegrationStatus(?IntegrationCredential $credential): array
    {
        if (! $credential) {
            return [
                'connected' => false,
                'has_token' => false,
                'token_expires_at' => null,
                'environment' => null,
                'is_configured' => false,
            ];
        }

        $token = $credential->getActiveEbayToken();

        return [
            'connected' => $token !== null,
            'has_token' => $token !== null,
            'token_expires_at' => $token?->expires_at?->toISOString(),
            'environment' => $credential->environment,
            'is_configured' => $credential->isEbayReady(),
            'marketplace_id' => $credential->marketplace_id ?? 'EBAY_US',
            'seller_username' => $credential->seller_username,
            'account_username' => $credential->seller_username ?? ($token?->metadata ?? [])['username'] ?? null,
        ];
    }

    protected function getListingStats(?IntegrationCredential $credential): array
    {
        if (! $credential) {
            return [
                'total' => 0,
                'published' => 0,
                'pending' => 0,
                'errors' => 0,
            ];
        }

        return [
            'total' => EbayListing::where('integration_credential_id', $credential->id)->count(),
            'published' => EbayListing::where('integration_credential_id', $credential->id)->where('status', 'published')->count(),
            'pending' => EbayListing::where('integration_credential_id', $credential->id)->where('status', 'pending')->count(),
            'errors' => EbayListing::where('integration_credential_id', $credential->id)->where('status', 'error')->count(),
        ];
    }

    protected function getLastImport(int $organizationId): ?array
    {
        $job = BulkOperationJob::where('organization_id', $organizationId)
            ->where('operation_type', BulkOperationJob::TYPE_EBAY_IMPORT)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $job) {
            return null;
        }

        return $this->formatBulkJob($job);
    }

    protected function formatBulkJob(BulkOperationJob $job): array
    {
        return [
            'id' => $job->id,
            'status' => $job->status,
            'operation_type' => $job->operation_type,
            'total_items' => $job->total_items,
            'processed_items' => $job->processed_items,
            'failed_items' => $job->failed_items,
            'progress_percent' => $job->progress_percent,
            'parameters' => $job->parameters,
            'errors' => $job->errors,
            'started_at' => $job->started_at?->toISOString(),
            'completed_at' => $job->completed_at?->toISOString(),
            'created_at' => $job->created_at->toISOString(),
        ];
    }
}
