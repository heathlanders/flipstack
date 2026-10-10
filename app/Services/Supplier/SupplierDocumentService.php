<?php

namespace App\Services\Supplier;

use App\Models\Inventory\Supplier;
use App\Models\Supplier\SupplierOnboarding;
use App\Models\User\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SupplierDocumentService
{
    public const TYPES = [
        'business_license' => 'Business License',
        'tax_documents' => 'Tax Documents',
        'insurance' => 'Insurance Certificate',
        'identity' => 'ID Verification',
    ];

    public const DISK = 'private';

    /**
     * Return a stable list of all four document slots, with state pulled
     * from supplier_onboarding step2_data + verification flags.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(Supplier $supplier): array
    {
        $onboarding = $supplier->onboarding ?? SupplierOnboarding::where('supplier_id', $supplier->id)->first();
        $stored = $onboarding?->step2_data['documents'] ?? [];

        $rows = [];
        foreach (self::TYPES as $type => $label) {
            $doc = $stored[$type] ?? null;
            $verifiedFlag = $onboarding?->{$type.'_verified'} ?? false;
            $status = $doc['status'] ?? ($doc ? 'pending' : 'missing');
            if ($verifiedFlag && $status !== 'rejected') {
                $status = 'verified';
            }

            $rows[] = [
                'type' => $type,
                'label' => $label,
                'uploaded' => $doc !== null,
                'original_name' => $doc['original_name'] ?? null,
                'uploaded_at' => $doc['uploaded_at'] ?? null,
                'status' => $status,
                'verified' => (bool) $verifiedFlag,
                'reviewed_at' => $doc['reviewed_at'] ?? null,
                'reviewed_by' => $doc['reviewed_by'] ?? null,
                'rejection_reason' => $doc['rejection_reason'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * Resolve the disk path for a given supplier doc, or null if absent.
     */
    public function pathFor(Supplier $supplier, string $type): ?array
    {
        $onboarding = SupplierOnboarding::where('supplier_id', $supplier->id)->first();
        $doc = $onboarding?->step2_data['documents'][$type] ?? null;

        if (! $doc || ! Storage::disk(self::DISK)->exists($doc['path'])) {
            return null;
        }

        return [
            'path' => $doc['path'],
            'original_name' => $doc['original_name'] ?? basename($doc['path']),
        ];
    }

    /**
     * Store (or replace) a document for the supplier. Resets verification.
     */
    public function store(Supplier $supplier, string $type, UploadedFile $file): SupplierOnboarding
    {
        $this->assertType($type);

        $onboarding = SupplierOnboarding::firstOrCreate(
            ['supplier_id' => $supplier->id],
            [
                'organization_id' => $supplier->organization_id,
                'status' => 'in_progress',
                'started_at' => now(),
                'current_step' => 2,
            ]
        );

        $documents = $onboarding->step2_data['documents'] ?? [];

        if (isset($documents[$type]['path'])) {
            Storage::disk(self::DISK)->delete($documents[$type]['path']);
        }

        $path = $file->store("suppliers/{$supplier->id}/documents", self::DISK);

        $documents[$type] = [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'uploaded_at' => now()->toIso8601String(),
            'status' => 'pending',
        ];

        $onboarding->update([
            'step2_data' => array_merge($onboarding->step2_data ?? [], ['documents' => $documents]),
            $type.'_verified' => false,
        ]);

        return $onboarding->fresh();
    }

    /**
     * Mark a document as verified by a reviewer.
     */
    public function verify(Supplier $supplier, string $type, User $reviewer): SupplierOnboarding
    {
        $this->assertType($type);

        $onboarding = SupplierOnboarding::where('supplier_id', $supplier->id)->firstOrFail();
        $documents = $onboarding->step2_data['documents'] ?? [];

        if (! isset($documents[$type])) {
            abort(404, 'Document not uploaded.');
        }

        $documents[$type] = array_merge($documents[$type], [
            'status' => 'verified',
            'reviewed_at' => now()->toIso8601String(),
            'reviewed_by' => $reviewer->name ?? $reviewer->email,
            'rejection_reason' => null,
        ]);

        $onboarding->update([
            'step2_data' => array_merge($onboarding->step2_data ?? [], ['documents' => $documents]),
            $type.'_verified' => true,
        ]);

        return $onboarding->fresh();
    }

    /**
     * Reject a document with a reason. Clears the verified flag.
     */
    public function reject(Supplier $supplier, string $type, User $reviewer, string $reason): SupplierOnboarding
    {
        $this->assertType($type);

        $onboarding = SupplierOnboarding::where('supplier_id', $supplier->id)->firstOrFail();
        $documents = $onboarding->step2_data['documents'] ?? [];

        if (! isset($documents[$type])) {
            abort(404, 'Document not uploaded.');
        }

        $documents[$type] = array_merge($documents[$type], [
            'status' => 'rejected',
            'reviewed_at' => now()->toIso8601String(),
            'reviewed_by' => $reviewer->name ?? $reviewer->email,
            'rejection_reason' => $reason,
        ]);

        $onboarding->update([
            'step2_data' => array_merge($onboarding->step2_data ?? [], ['documents' => $documents]),
            $type.'_verified' => false,
        ]);

        return $onboarding->fresh();
    }

    protected function assertType(string $type): void
    {
        if (! array_key_exists($type, self::TYPES)) {
            abort(404, "Unknown document type: {$type}");
        }
    }
}
