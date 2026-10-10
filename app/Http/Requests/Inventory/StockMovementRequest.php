<?php

namespace App\Http\Requests\Inventory;

use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryMovement;
use Illuminate\Foundation\Http\FormRequest;

class StockMovementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('inventory.update');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:'.implode(',', InventoryMovement::getMovementTypes())],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.product_variation_id' => ['nullable', 'uuid', 'exists:product_variations,id'],
            'items.*.location_id' => ['required', 'uuid', 'exists:inventory_locations,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'reference_type' => ['nullable', 'string', 'max:50'],
            'reference_id' => ['nullable', 'uuid'],
            'reference_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateProductsAndLocations($validator);
            $this->validateStockAvailability($validator);
        });
    }

    /**
     * Validate products and locations belong to organization.
     */
    protected function validateProductsAndLocations($validator): void
    {
        $organizationId = $this->getOrganizationId();
        $items = $this->items ?? [];

        foreach ($items as $index => $item) {
            // Validate product
            if (! empty($item['product_id'])) {
                $product = Product::where('id', $item['product_id'])
                    ->where('organization_id', $organizationId)
                    ->first();

                if (! $product) {
                    $validator->errors()->add("items.{$index}.product_id", 'The selected product does not belong to your organization.');
                }
            }

            // Validate location
            if (! empty($item['location_id'])) {
                $location = InventoryLocation::where('id', $item['location_id'])
                    ->where('organization_id', $organizationId)
                    ->where('is_active', true)
                    ->first();

                if (! $location) {
                    $validator->errors()->add("items.{$index}.location_id", 'The selected location is not valid.');
                }
            }
        }
    }

    /**
     * Validate stock availability for movements that decrease stock.
     */
    protected function validateStockAvailability($validator): void
    {
        $type = $this->type;
        $items = $this->items ?? [];
        $organizationId = $this->getOrganizationId();

        // Types that decrease stock
        $decreaseTypes = [
            InventoryMovement::TYPE_SALE,
            InventoryMovement::TYPE_TRANSFER_OUT,
            InventoryMovement::TYPE_DAMAGED,
            InventoryMovement::TYPE_LOST,
        ];

        if (! in_array($type, $decreaseTypes)) {
            return;
        }

        foreach ($items as $index => $item) {
            if (empty($item['product_id']) || empty($item['location_id']) || empty($item['quantity'])) {
                continue;
            }

            $product = Product::find($item['product_id']);
            if (! $product || $product->allow_backorders) {
                continue;
            }

            $level = InventoryLevel::where('organization_id', $organizationId)
                ->where('product_id', $item['product_id'])
                ->where('location_id', $item['location_id'])
                ->when(! empty($item['product_variation_id']), fn ($q) => $q->where('product_variation_id', $item['product_variation_id']))
                ->first();

            $availableStock = $level?->quantity_available ?? 0;

            if ($item['quantity'] > $availableStock) {
                $validator->errors()->add(
                    "items.{$index}.quantity",
                    "Insufficient stock at this location. Available: {$availableStock}"
                );
            }
        }
    }

    /**
     * Get the organization ID.
     */
    protected function getOrganizationId(): int
    {
        return $this->user()->organization_id
            ?? $this->user()->current_organization_id
            ?? $this->user()->currentTeam?->id
            ?? 1;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'items.required' => 'At least one item is required.',
            'items.max' => 'You can process a maximum of 100 items at once.',
            'items.*.product_id.required' => 'Each item must have a product selected.',
            'items.*.location_id.required' => 'Each item must have a location selected.',
            'items.*.quantity.required' => 'Each item must have a quantity.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
        ];
    }
}
