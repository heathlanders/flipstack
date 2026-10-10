<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'type_label' => ucwords(str_replace('_', ' ', $this->type)),

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

            // Contact information
            'contact' => [
                'contact_person' => $this->contact_person,
                'email' => $this->email,
                'phone' => $this->phone,
            ],

            // Status
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,

            // Analytics (when loaded)
            'analytics' => $this->when($this->relationLoaded('inventoryLevels'), function () {
                return [
                    'total_inventory_value' => $this->getTotalInventoryValue(),
                    'total_product_count' => $this->getTotalProductCount(),
                    'inventory_levels_count' => $this->inventoryLevels->count(),
                    'formatted_inventory_value' => '$'.number_format($this->getTotalInventoryValue(), 2),
                ];
            }),

            // Relationships
            'inventory_levels' => InventoryLevelResource::collection($this->whenLoaded('inventoryLevels')),
            'inventory_levels_count' => $this->whenCounted('inventoryLevels'),
            'recent_movements_count' => $this->whenCounted('inventoryMovements'),

            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
