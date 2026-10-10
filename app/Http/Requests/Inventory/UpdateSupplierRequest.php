<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('update', $this->route('supplier'));
    }

    public function rules(): array
    {
        $supplier = $this->route('supplier');
        $organizationId = auth()->user()->current_organization_id;

        return [
            'name' => 'sometimes|string|max:255',
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('suppliers')->where('organization_id', $organizationId)->ignore($supplier->id),
            ],
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|max:100',
            'business_registration' => 'nullable|string|max:100',
            'payment_terms' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|size:3',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'lead_time_days' => 'nullable|integer|min:0',
            'performance_rating' => 'nullable|numeric|between:0,5',
            'is_active' => 'boolean',
            'is_preferred' => 'boolean',
            'notes' => 'nullable|string',
            'meta_data' => 'nullable|array',
        ];
    }
}
