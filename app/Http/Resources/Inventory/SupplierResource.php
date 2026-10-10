<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'code' => $this->code,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,

            // Address information
            'address' => [
                'address_line1' => $this->address_line1,
                'address_line2' => $this->address_line2,
                'city' => $this->city,
                'state' => $this->state,
                'postal_code' => $this->postal_code,
                'country' => $this->country,
                'full_address' => $this->getFullAddressAttribute(),
            ],

            // Business information
            'business_info' => [
                'tax_id' => $this->tax_id,
                'business_registration' => $this->business_registration,
            ],

            // Terms and conditions
            'terms' => [
                'payment_terms' => $this->payment_terms,
                'payment_terms_text' => "Net {$this->payment_terms} days",
                'currency' => $this->currency,
                'minimum_order_amount' => $this->minimum_order_amount,
                'lead_time_days' => $this->lead_time_days,
                'lead_time_text' => "{$this->lead_time_days} business days",
            ],

            // Performance metrics
            'performance' => [
                'rating' => $this->performance_rating,
                'total_orders' => $this->total_orders,
                'on_time_delivery_rate' => $this->on_time_delivery_rate,
                'on_time_delivery_percentage' => "{$this->on_time_delivery_rate}%",
            ],

            // Status and settings
            'is_active' => $this->is_active,
            'is_preferred' => $this->is_preferred,
            'notes' => $this->notes,
            'meta_data' => $this->meta_data,

            // Relationships
            'supplier_products' => SupplierProductResource::collection($this->whenLoaded('supplierProducts')),
            'products_count' => $this->whenCounted('supplierProducts'),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),

            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
