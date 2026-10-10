<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class BulkProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->hasPermission('inventory.bulk.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'action' => 'required|in:delete,restore,force_delete,update_status,update_category,update_price,adjust_stock,export',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'required|exists:products,id',
            'data' => 'sometimes|array',
        ];

        // Add conditional rules based on action
        $action = $this->input('action');

        if ($action === 'update_status') {
            $rules['data.status'] = 'required|in:draft,active,inactive,archived';
        }

        if ($action === 'update_category') {
            $rules['data.category_id'] = 'required|exists:categories,id';
        }

        if ($action === 'update_price') {
            $rules['data.price_type'] = 'required|in:fixed,percentage';
            $rules['data.price_field'] = 'required|in:selling_price,cost_price';
            $rules['data.value'] = 'required|numeric';
            $rules['data.operation'] = 'required_if:data.price_type,percentage|in:increase,decrease';
        }

        if ($action === 'adjust_stock') {
            $rules['data.adjustment_type'] = 'required|in:set,add,subtract';
            $rules['data.quantity'] = 'required|integer';
            $rules['data.reason'] = 'nullable|string|max:255';
            $rules['data.location_id'] = 'nullable|exists:inventory_locations,id';
        }

        if ($action === 'export') {
            $rules['data.format'] = 'nullable|in:csv,xlsx';
            $rules['data.columns'] = 'nullable|array';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'product_ids.required' => 'Please select at least one product.',
            'product_ids.min' => 'Please select at least one product.',
            'data.status.required' => 'Status is required for this operation.',
            'data.category_id.required' => 'Category is required for this operation.',
            'data.price_type.required' => 'Price type is required for this operation.',
            'data.quantity.required' => 'Quantity is required for stock adjustment.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Verify all products belong to the user's organization
            $organizationId = auth()->user()->organization_id ?? auth()->user()->current_organization_id ?? 1;
            $productIds = $this->input('product_ids', []);
            $action = $this->input('action');

            // For restore/force_delete, products are soft-deleted so use withTrashed
            $query = \App\Models\Catalog\Product::whereIn('id', $productIds)
                ->where('organization_id', $organizationId);

            if (in_array($action, ['restore', 'force_delete'])) {
                $query->onlyTrashed();
            }

            $validCount = $query->count();

            if ($validCount !== count($productIds)) {
                $validator->errors()->add('product_ids', 'One or more products do not belong to your organization.');
            }
        });
    }
}
