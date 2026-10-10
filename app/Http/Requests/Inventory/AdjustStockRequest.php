<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('manage-inventory');
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'product_variation_id' => 'nullable|exists:product_variations,id',
            'location_id' => 'required|exists:inventory_locations,id',
            'quantity' => 'required|integer|not_in:0',
            'reason' => 'required|string|max:255',
            'unit_cost' => 'nullable|numeric|min:0',
            'metadata' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.not_in' => 'Quantity cannot be zero',
            'reason.required' => 'A reason for the adjustment is required',
        ];
    }
}
