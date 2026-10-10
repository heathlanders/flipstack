<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('update', $this->route('category'));
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $organizationId = auth()->user()->current_organization_id;

        return [
            'name' => 'sometimes|string|max:255',
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('categories')->where('organization_id', $organizationId)->ignore($category->id),
            ],
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'meta_data' => 'nullable|array',
            'image_url' => 'nullable|url|max:500',
        ];
    }
}
