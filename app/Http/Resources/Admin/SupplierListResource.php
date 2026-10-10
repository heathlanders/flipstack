<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Resource for admin supplier list with full supplier details.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company_name' => $this->company_name,
            'display_name' => $this->display_name ?? ($this->company_name ?: $this->name),
            'code' => $this->code,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,

            // Address
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,

            // Status
            'is_active' => $this->is_active,
            'is_preferred' => $this->is_preferred,
            'is_auto_generated' => (bool) ($this->meta_data['auto_generated'] ?? false),
            'is_consignor' => (bool) ($this->meta_data['is_consignor'] ?? false),
            'supplier_status' => $this->supplier_status?->value ?? 'pending',
            'supplier_status_label' => $this->supplier_status?->label() ?? 'Pending',
            'supplier_status_color' => $this->supplier_status?->color() ?? 'gray',
            'status_reason' => $this->status_reason,

            // Tier
            'current_tier_id' => $this->current_tier_id,
            'current_tier' => $this->whenLoaded('currentTier', fn () => [
                'id' => $this->currentTier->id,
                'name' => $this->currentTier->tier_name,
                'code' => $this->currentTier->tier_code,
                'color' => $this->currentTier->color_code,
            ]),

            // Commission
            'commission_structure_id' => $this->commission_structure_id,
            'commission_structure' => $this->whenLoaded('commissionStructure', fn () => [
                'id' => $this->commissionStructure->id,
                'name' => $this->commissionStructure->name,
                'type' => $this->commissionStructure->type,
                'base_percentage' => $this->commissionStructure->base_percentage,
            ]),
            'custom_commission_rate' => $this->custom_commission_rate,
            'effective_commission_rate' => $this->getEffectiveCommissionRate(),

            // Performance
            'performance_rating' => $this->performance_rating,
            'cached_performance_score' => $this->cached_performance_score,
            'cached_quality_score' => $this->cached_quality_score,
            'cached_fulfillment_score' => $this->cached_fulfillment_score,
            'cached_volume_score' => $this->cached_volume_score,
            'total_orders' => $this->total_orders,
            'on_time_delivery_rate' => $this->on_time_delivery_rate,

            // Financials
            'total_sales_ytd' => $this->total_sales_ytd,
            'total_sales_all_time' => $this->total_sales_all_time,
            'total_commissions_paid' => $this->total_commissions_paid,
            'pending_balance' => $this->pending_balance,
            'available_balance' => $this->available_balance,

            // Settlement
            'settlement_schedule' => $this->settlement_schedule,
            'settlement_day' => $this->settlement_day,
            'next_settlement_date' => $this->next_settlement_date?->format('Y-m-d'),

            // Portal Access
            'portal_enabled' => $this->portal_enabled,
            'onboarding_complete' => $this->onboarding_complete,
            'last_portal_login_at' => $this->last_portal_login_at?->format('Y-m-d H:i:s'),

            // Business Terms
            'payment_terms' => $this->payment_terms,
            'currency' => $this->currency,
            'minimum_order_amount' => $this->minimum_order_amount,
            'lead_time_days' => $this->lead_time_days,

            // Notes
            'notes' => $this->notes,

            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get effective commission rate from the model
     */
    protected function getEffectiveCommissionRate(): float
    {
        if (method_exists($this->resource, 'getEffectiveCommissionRate')) {
            return $this->resource->getEffectiveCommissionRate();
        }

        if ($this->custom_commission_rate !== null) {
            return (float) $this->custom_commission_rate;
        }

        if ($this->commissionStructure) {
            return (float) $this->commissionStructure->base_percentage;
        }

        return 0;
    }
}
