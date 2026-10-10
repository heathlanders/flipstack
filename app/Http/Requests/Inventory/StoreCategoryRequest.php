<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('create', \App\Models\Inventory\Category::class);
    }

    public function rules(): array
    {
        $organizationId = auth()->user()->current_organization_id;

        return [
            'name' => 'required|string|max:255',
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('categories')->where('organization_id', $organizationId),
            ],
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'meta_data' => 'nullable|array',
            'image_url' => 'nullable|url|max:500',
        ];
    }
}
