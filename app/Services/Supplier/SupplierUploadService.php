<?php

namespace App\Services\Supplier;

use App\Models\Catalog\ProductIntakeQueue;
use App\Models\Supplier\SupplierProductSubmission;
use Illuminate\Support\Facades\DB;

class SupplierUploadService
{
    /**
     * Create a new supplier product submission.
     */
    public function submit(array $data, string $supplierId, string $orgId): SupplierProductSubmission
    {
        return SupplierProductSubmission::create([
            'organization_id' => $orgId,
            'supplier_id' => $supplierId,
            'status' => SupplierProductSubmission::STATUS_PENDING_REVIEW,
            'submitted_data' => $data,
        ]);
    }

    /**
     * Approve a submission and create a ProductIntakeQueue item.
     */
    public function approve(SupplierProductSubmission $submission, int $reviewerId): void
    {
        DB::transaction(function () use ($submission, $reviewerId) {
            $data = $submission->submitted_data;

            $intakeItem = ProductIntakeQueue::create([
                'organization_id' => $submission->organization_id,
                'supplier_id' => $submission->supplier_id,
                'barcode' => $data['barcode'] ?? null,
                'user_notes' => $data['description'] ?? null,
                'photos' => $data['images'] ?? [],
                'processing_status' => 'pending',
                'capture_method' => 'supplier_upload',
                'captured_at' => now(),
            ]);

            $submission->update([
                'status' => SupplierProductSubmission::STATUS_APPROVED,
                'intake_queue_id' => $intakeItem->id,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);
        });
    }

    /**
     * Reject a submission with review notes.
     */
    public function reject(SupplierProductSubmission $submission, int $reviewerId, string $notes): void
    {
        $submission->update([
            'status' => SupplierProductSubmission::STATUS_REJECTED,
            'review_notes' => $notes,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Get submission statistics for an organization.
     */
    public function getSubmissionStats(string $orgId): array
    {
        $counts = SupplierProductSubmission::where('organization_id', $orgId)
            ->selectRaw("COUNT(CASE WHEN status = 'pending_review' THEN 1 END) as pending")
            ->selectRaw("COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved")
            ->selectRaw("COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected")
            ->first();

        return [
            'pending' => (int) $counts->pending,
            'approved' => (int) $counts->approved,
            'rejected' => (int) $counts->rejected,
        ];
    }
}
