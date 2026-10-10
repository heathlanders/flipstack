<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ReserveStockRequest extends FormRequest
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
            'quantity' => 'required|integer|min:1',
            'reference_type' => 'required|string|max:50',
            'reference_id' => 'required|string|max:255',
        ];
    }
}
