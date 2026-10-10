<?php

namespace App\Http\Resources\Supplier;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'company_name' => $this->company_name,
            'phone' => $this->phone,
            'avatar' => $this->avatar_url,

            // Status
            'status' => $this->supplier_status?->value,
            'status_label' => $this->supplier_status?->label(),
            'status_color' => $this->supplier_status?->color(),
            'is_active' => $this->supplier_status?->canSubmitProducts(),

            // Tier information
            'tier' => $this->when($this->relationLoaded('currentTier') || $this->currentTier, [
                'id' => $this->currentTier?->id,
                'name' => $this->currentTier?->tier_name,
                'code' => $this->currentTier?->tier_code,
                'slug' => $this->currentTier?->tier_code,
                'benefits' => $this->currentTier?->benefits ?? [],
                'settlement_frequency' => $this->currentTier?->settlement_frequency,
                'commission_adjustment' => $this->currentTier?->commission_adjustment,
            ]),
            'next_tier_evaluation' => $this->tier_evaluation_due_at?->format('Y-m-d'),
            'tier_change_date' => $this->tier_assigned_at?->format('Y-m-d'),

            // Commission information
            'commission' => $this->when($this->relationLoaded('commissionStructure') || $this->commissionStructure, [
                'structure_id' => $this->commissionStructure?->id,
                'structure_name' => $this->commissionStructure?->name,
                'type' => $this->commissionStructure?->type->value,
                'type_label' => $this->commissionStructure?->type->label(),
                'base_percentage' => $this->commissionStructure?->base_percentage,
                'custom_rate' => $this->custom_commission_rate,
            ]),

            // Settlement information
            'settlement' => [
                'schedule' => $this->settlement_schedule,
                'day' => $this->settlement_day,
                'next_date' => $this->next_settlement_date?->format('Y-m-d'),
                'pending_amount' => round($this->pending_balance ?? 0, 2),
            ],

            // Performance
            'performance' => [
                'score' => $this->cached_performance_score,
                'last_calculated' => $this->performance_calculated_at?->format('Y-m-d H:i:s'),
            ],

            // Per-axis scores consumed by the Overview "Performance Summary" widget.
            // Quality/fulfillment live on the supplier cache; sales is derived from
            // overall_performance_score minus quality+fulfillment as a rough split.
            'sales_score' => $this->cached_performance_score,
            'quality_score' => $this->cached_quality_score,
            'fulfillment_score' => $this->cached_fulfillment_score,
            'return_rate' => $this->meta_data['return_rate'] ?? 0,

            // Financial summary (YTD)
            'financials' => [
                'total_sales_ytd' => round($this->total_sales_ytd ?? 0, 2),
                'total_commissions_paid' => round($this->total_commissions_paid ?? 0, 2),
                'pending_balance' => round($this->pending_balance ?? 0, 2),
            ],

            // Portal access
            'portal' => [
                'access_enabled' => $this->portal_enabled,
                'last_login' => $this->last_portal_login_at?->format('Y-m-d H:i:s'),
                'activated_at' => $this->portal_activated_at?->format('Y-m-d'),
            ],

            // Business information
            'business' => [
                'type' => $this->business_type,
                'tax_id' => $this->tax_id,
                'website' => $this->website,
            ],

            // Contact info
            'contact' => [
                'contact_name' => $this->contact_name,
            ],

            // Timestamps
            'last_activity' => $this->last_portal_login_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Admin notes (stored as array under meta_data.admin_notes).
            // Single-row supplier.notes column is reserved for legacy free-text.
            'notes' => collect((array) ($this->meta_data['admin_notes'] ?? []))
                ->map(fn ($n) => [
                    'id' => $n['id'] ?? null,
                    'content' => $n['content'] ?? '',
                    'created_at' => $n['created_at'] ?? null,
                    'user' => ['name' => $n['user_name'] ?? 'System'],
                ])
                ->values()
                ->all(),
        ];
    }
}
