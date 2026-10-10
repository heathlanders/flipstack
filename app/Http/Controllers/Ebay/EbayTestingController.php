<?php

namespace App\Http\Controllers\Ebay;

use App\Http\Controllers\Controller;
use App\Jobs\CreateEbayListingJob;
use App\Models\Catalog\ProductIntakeQueue;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EbayTestingController extends Controller
{
    public function dashboard()
    {
        $queueStats = [
            'total' => ProductIntakeQueue::count(),
            'ready_for_listing' => ProductIntakeQueue::whereIn('processing_status', ['ready', 'validated'])->count(),
            'listed' => ProductIntakeQueue::where('processing_status', 'listed_on_ebay')->count(),
            'failed' => ProductIntakeQueue::whereIn('processing_status', ['failed', 'listing_failed'])->count(),
            'needs_review' => ProductIntakeQueue::where('processing_status', 'needs_review')->count(),
            'in_progress' => ProductIntakeQueue::where('processing_status', 'in_progress')->count(),
        ];

        $recentItems = ProductIntakeQueue::with('capturedBy')
            ->select([
                'id',
                'product_number',
                'manufacturer_part_number',
                'barcode',
                'processing_status',
                'quality_score',
                'validation_errors',
                'condition',
                'created_at',
                'enriched_data',
            ])
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('EbayTesting/Dashboard', [
            'queueStats' => $queueStats,
            'recentItems' => $recentItems,
        ]);
    }

    public function testSingleListing(Request $request)
    {
        $queueItem = ProductIntakeQueue::findOrFail($request->queue_item_id);

        // Accept both 'ready' and 'validated' as ready for listing
        if (! in_array($queueItem->processing_status, ['ready', 'validated'])) {
            return back()->with('error', "Item must be 'ready' or 'validated' before listing. Current status: {$queueItem->processing_status}");
        }

        // Check if organization_id exists
        if (! $queueItem->organization_id) {
            return back()->with('error', 'Queue item missing organization ID. Cannot create listing.');
        }

        try {
            CreateEbayListingJob::dispatch($queueItem, true);

            return back()->with('success', 'eBay listing job queued for processing');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to queue listing job: '.$e->getMessage());
        }
    }

    public function bulkTest(Request $request)
    {
        $limit = $request->input('limit', 5);

        $items = ProductIntakeQueue::whereIn('processing_status', ['ready', 'validated'])
            ->whereNotNull('organization_id')
            ->limit($limit)
            ->get();

        if ($items->isEmpty()) {
            return back()->with('error', 'No items ready for listing found');
        }

        $successCount = 0;
        foreach ($items as $item) {
            try {
                CreateEbayListingJob::dispatch($item, true);
                $successCount++;
            } catch (\Exception $e) {
                \Log::error('Failed to queue eBay listing job', [
                    'item_id' => $item->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($successCount > 0) {
            return back()->with('success', "Queued {$successCount} items for eBay listing");
        } else {
            return back()->with('error', 'Failed to queue any items for listing');
        }
    }

    public function showItem(Request $request, $id)
    {
        $queueItem = ProductIntakeQueue::with(['capturedBy', 'processedBy'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $queueItem->id,
                'product_number' => $queueItem->product_number,
                'manufacturer_part_number' => $queueItem->manufacturer_part_number,
                'barcode' => $queueItem->barcode,
                'processing_status' => $queueItem->processing_status,
                'condition' => $queueItem->condition,
                'condition_description' => $queueItem->condition_description,
                'quality_score' => $queueItem->quality_score,
                'validation_errors' => $queueItem->validation_errors,
                'enriched_data' => $queueItem->enriched_data,
                'photos' => $queueItem->photos,
                'created_at' => $queueItem->created_at,
                'captured_by' => $queueItem->capturedBy?->name,
                'processed_by' => $queueItem->processedBy?->name,
            ],
        ]);
    }

    public function updateItemStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:ready,validated,needs_review,failed,listing_failed,listed_on_ebay',
        ]);

        $queueItem = ProductIntakeQueue::findOrFail($id);
        $oldStatus = $queueItem->processing_status;

        $queueItem->update([
            'processing_status' => $request->status,
            'last_processed_at' => now(),
        ]);

        \Log::info('Queue item status updated manually', [
            'item_id' => $id,
            'old_status' => $oldStatus,
            'new_status' => $request->status,
            'updated_by' => auth()->user()->id,
        ]);

        return back()->with('success', "Item status updated from '{$oldStatus}' to '{$request->status}'");
    }

    public function retryFailedItem(Request $request, $id)
    {
        $queueItem = ProductIntakeQueue::findOrFail($id);

        if (! in_array($queueItem->processing_status, ['failed', 'listing_failed'])) {
            return back()->with('error', 'Only failed items can be retried');
        }

        // Reset to ready status for retry
        $queueItem->update([
            'processing_status' => 'ready',
            'validation_errors' => null,
            'last_processed_at' => now(),
        ]);

        return back()->with('success', 'Item status reset to ready for retry');
    }
}
