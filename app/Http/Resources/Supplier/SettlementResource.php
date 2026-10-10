<?php

namespace App\Http\Resources\Supplier;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettlementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'settlement_number' => $this->settlement_number,
            'supplier_id' => $this->supplier_id,

            // Period
            'period' => [
                'start' => $this->settlement_period_start?->format('Y-m-d'),
                'end' => $this->settlement_period_end?->format('Y-m-d'),
                'label' => $this->periodLabel,
            ],

            // Financial Summary
            'financials' => [
                'gross_sales' => round($this->gross_sales_amount ?? 0, 2),
                'returns_amount' => round($this->returns_amount ?? 0, 2),
                'net_sales' => round($this->net_sales_amount ?? 0, 2),
                'commission_amount' => round($this->commission_amount ?? 0, 2),
                'fees_amount' => round($this->fees_deducted ?? 0, 2),
                'adjustments_amount' => round($this->adjustments ?? 0, 2),
                'bonus_amount' => round($this->bonuses ?? 0, 2),
                'net_amount' => round($this->net_amount ?? 0, 2),
            ],

            // Supplier payout (calculated)
            'supplier_payout' => round($this->supplierPayout ?? 0, 2),

            // Status
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_color' => $this->status?->color(),
            'can_edit' => $this->canBeEdited(),
            'can_cancel' => $this->canBeCancelled(),
            'can_dispute' => $this->canBeDisputed(),
            'is_finalized' => $this->isFinalized(),

            // Payment Information
            'payment' => [
                'method' => $this->payment_method,
                'reference' => $this->payment_reference,
                'date' => $this->payment_date?->format('Y-m-d'),
                'due_date' => $this->payment_due_date?->format('Y-m-d'),
                'days_until_due' => $this->daysUntilDue,
                'is_overdue' => $this->isOverdue,
                'details' => $this->when($request->user()?->can('viewPaymentDetails', $this->resource), $this->payment_details),
            ],

            // Tax Information
            'tax' => [
                'withheld' => round($this->tax_withheld ?? 0, 2),
                'documents' => $this->when($request->user()?->can('viewTaxDocuments', $this->resource), $this->tax_documents),
            ],

            // Dispute Information
            'dispute' => $this->when($this->is_disputed, [
                'is_disputed' => $this->is_disputed,
                'reason' => $this->dispute_reason,
                'resolution' => $this->dispute_resolution,
            ]),

            // Items Summary
            'items' => [
                'count' => $this->when($this->relationLoaded('items'), fn () => $this->items->count()),
                'returned_count' => $this->when(
                    $this->relationLoaded('items'),
                    fn () => $this->items->where('is_returned', true)->count()
                ),
            ],

            // Item details (when loaded)
            'item_details' => $this->when(
                $this->relationLoaded('items') && $request->input('include_items'),
                fn () => $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'sale_date' => $item->sale_date?->format('Y-m-d'),
                    'sale_price' => round($item->sale_price, 2),
                    'commission' => round($item->final_commission, 2),
                    'payout' => round($item->supplier_payout, 2),
                    'is_returned' => $item->is_returned,
                    'status' => $item->status,
                ])
            ),

            // Processing Information
            'processing' => [
                'reviewed_by' => $this->reviewedBy?->name,
                'reviewed_at' => $this->reviewed_at?->format('Y-m-d H:i:s'),
                'approved_by' => $this->approvedBy?->name,
                'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
                'processed_by' => $this->processedBy?->name,
                'processed_at' => $this->processed_at?->format('Y-m-d H:i:s'),
            ],

            // Notes
            'notes' => $this->notes,

            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Links
            'links' => [
                'self' => route('supplier.settlements.show', $this->id),
                'items' => route('supplier.settlements.items', $this->id),
                'download_pdf' => route('supplier.settlements.pdf', $this->id),
            ],
        ];
    }
}
