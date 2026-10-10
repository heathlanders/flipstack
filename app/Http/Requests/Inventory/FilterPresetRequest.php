<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class FilterPresetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'filters' => ['required', 'array'],
            'filters.search' => ['nullable', 'string', 'max:255'],
            'filters.category_ids' => ['nullable', 'array'],
            'filters.category_ids.*' => ['integer', 'exists:categories,id'],
            'filters.brand_ids' => ['nullable', 'array'],
            'filters.brand_ids.*' => ['integer', 'exists:brands,id'],
            'filters.stock_status' => ['nullable', 'array'],
            'filters.stock_status.*' => ['string', 'in:in_stock,low_stock,out_of_stock,overstocked'],
            'filters.min_price' => ['nullable', 'numeric', 'min:0'],
            'filters.max_price' => ['nullable', 'numeric', 'min:0'],
            'filters.condition' => ['nullable', 'array'],
            'filters.condition.*' => ['string'],
            'filters.location_ids' => ['nullable', 'array'],
            'filters.location_ids.*' => ['integer', 'exists:inventory_locations,id'],
            'filters.created_from' => ['nullable', 'date'],
            'filters.created_to' => ['nullable', 'date', 'after_or_equal:filters.created_from'],
            'filters.sort_by' => ['nullable', 'string', 'in:name,sku,selling_price,stock_quantity,created_at,updated_at'],
            'filters.sort_order' => ['nullable', 'string', 'in:asc,desc'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please provide a name for this filter preset.',
            'name.max' => 'The preset name cannot exceed 100 characters.',
            'filters.required' => 'At least one filter must be specified.',
            'filters.created_to.after_or_equal' => 'The end date must be after or equal to the start date.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure filters is always an array
        if (! $this->has('filters') || ! is_array($this->filters)) {
            $this->merge(['filters' => []]);
        }

        // Convert boolean string to actual boolean
        if ($this->has('is_default')) {
            $this->merge([
                'is_default' => filter_var($this->is_default, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
