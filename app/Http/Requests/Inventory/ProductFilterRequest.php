<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ProductFilterRequest extends FormRequest
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
            // Search
            'search' => ['nullable', 'string', 'max:255'],

            // Single category (backward compatibility)
            'category_id' => ['nullable', 'exists:categories,id'],

            // Multiple categories
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],

            // Single brand (backward compatibility)
            'brand_id' => ['nullable', 'exists:brands,id'],

            // Multiple brands
            'brand_ids' => ['nullable', 'array'],
            'brand_ids.*' => ['integer', 'exists:brands,id'],

            // Status filter
            'status' => ['nullable', 'string', 'in:draft,active,inactive,archived'],

            // Stock status (multiple)
            'stock_status' => ['nullable'],

            // Condition filter (multiple)
            'condition' => ['nullable'],

            // Price range
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],

            // Location filter (multiple)
            'location_ids' => ['nullable', 'array'],
            'location_ids.*' => ['integer', 'exists:inventory_locations,id'],

            // Date range filters
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'updated_from' => ['nullable', 'date'],
            'updated_to' => ['nullable', 'date', 'after_or_equal:updated_from'],

            // Sorting
            'sort_by' => ['nullable', 'string', 'in:name,sku,selling_price,stock_quantity,created_at,updated_at'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],

            // Pagination
            'per_page' => ['nullable', 'integer', 'in:20,50,100'],
            'page' => ['nullable', 'integer', 'min:1'],

            // View mode
            'view_mode' => ['nullable', 'string', 'in:grid,list'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert comma-separated strings to arrays for multi-value filters
        $this->convertToArray('category_ids');
        $this->convertToArray('brand_ids');
        $this->convertToArray('location_ids');
        $this->convertToArray('stock_status');
        $this->convertToArray('condition');
    }

    /**
     * Convert a comma-separated string input to an array.
     */
    protected function convertToArray(string $field): void
    {
        if ($this->has($field) && is_string($this->input($field))) {
            $value = $this->input($field);
            if (empty($value)) {
                $this->merge([$field => []]);
            } else {
                $this->merge([$field => array_map('trim', explode(',', $value))]);
            }
        }
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max' => 'Search query cannot exceed 255 characters.',
            'min_price.min' => 'Minimum price cannot be negative.',
            'max_price.min' => 'Maximum price cannot be negative.',
            'created_to.after_or_equal' => 'End date must be after or equal to start date.',
            'updated_to.after_or_equal' => 'End date must be after or equal to start date.',
        ];
    }

    /**
     * Get the validated data with default values.
     */
    public function validatedWithDefaults(): array
    {
        $validated = $this->validated();

        return array_merge([
            'sort_by' => 'created_at',
            'sort_order' => 'desc',
            'per_page' => 20,
            'view_mode' => 'grid',
        ], array_filter($validated, fn ($value) => $value !== null && $value !== ''));
    }
}
