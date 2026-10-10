<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $product = $this->route('product');
        $organizationId = auth()->user()->organization_id ?? auth()->user()->current_organization_id ?? 1;

        return $product->organization_id === $organizationId
            && auth()->user()->hasPermission('inventory.products.edit');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $organizationId = auth()->user()->organization_id ?? auth()->user()->current_organization_id ?? 1;
        $productId = $this->route('product')->id;

        return [
            // Basic Information
            'name' => 'sometimes|required|string|max:255',
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('products')->where(function ($query) use ($organizationId) {
                    return $query->where('organization_id', $organizationId);
                })->ignore($productId),
            ],
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:65535',
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('products')->where(function ($query) use ($organizationId) {
                    return $query->where('organization_id', $organizationId);
                })->ignore($productId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products')->where(function ($query) use ($organizationId) {
                    return $query->where('organization_id', $organizationId);
                })->ignore($productId),
            ],

            // Classification
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'type' => 'nullable|in:simple,variable,bundle,service',
            'status' => 'sometimes|required|in:draft,active,inactive,archived',

            // Pricing
            'cost_price' => 'nullable|numeric|min:0|max:999999.99',
            'selling_price' => 'sometimes|required|numeric|min:0|max:999999.99',
            'msrp' => 'nullable|numeric|min:0|max:999999.99',
            'currency' => 'nullable|string|size:3',

            // Inventory
            'track_inventory' => 'boolean',
            'manage_stock' => 'boolean',
            'stock_quantity' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'allow_backorders' => 'boolean',

            // Physical Properties
            'weight' => 'nullable|numeric|min:0|max:9999.999',
            'length' => 'nullable|numeric|min:0|max:9999.99',
            'width' => 'nullable|numeric|min:0|max:9999.99',
            'height' => 'nullable|numeric|min:0|max:9999.99',

            // Shipping
            'requires_shipping' => 'boolean',
            'shipping_class' => 'nullable|string|max:100',
            'tax_class' => 'nullable|string|max:100',

            // Condition (for consignment/resale)
            'condition' => 'nullable|in:new,like_new,excellent,very_good,good,acceptable,for_parts,refurbished',
            'condition_description' => 'nullable|string|max:1000',

            // SEO
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'search_keywords' => 'nullable|string|max:500',

            // Attributes
            'attributes' => 'nullable|array',
            'attributes.*' => 'nullable|string|max:255',

            // Images
            'featured_image' => 'nullable|string|max:500',
            'gallery_images' => 'nullable|array',
            'gallery_images.*' => 'string|max:500',
            'images' => 'nullable|array',
            'images.*.id' => 'nullable|exists:product_images,id',
            'images.*.url' => 'required_with:images|string|max:500',
            'images.*.alt_text' => 'nullable|string|max:255',
            'images.*.sort_order' => 'nullable|integer|min:0',
            'images_to_delete' => 'nullable|array',
            'images_to_delete.*' => 'exists:product_images,id',

            // Publishing
            'published_at' => 'nullable|date',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required.',
            'sku.required' => 'SKU is required.',
            'sku.unique' => 'This SKU is already in use.',
            'barcode.unique' => 'This barcode is already in use.',
            'selling_price.required' => 'Selling price is required.',
            'selling_price.min' => 'Selling price must be at least 0.',
        ];
    }
}
