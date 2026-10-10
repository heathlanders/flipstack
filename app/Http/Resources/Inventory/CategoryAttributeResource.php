<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryAttributeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'is_required' => $this->is_required,
            'is_variant' => $this->is_variant,
            'sort_order' => $this->sort_order,
            'options' => $this->options,
            'validation_rules' => $this->validation_rules,

            // Type-specific information
            'has_options' => in_array($this->type, ['select', 'multiselect']),
            'is_text_type' => in_array($this->type, ['text']),
            'is_number_type' => in_array($this->type, ['number']),
            'is_date_type' => in_array($this->type, ['date']),
            'is_boolean_type' => in_array($this->type, ['boolean']),

            // Relationships
            'category' => new CategoryResource($this->whenLoaded('category')),

            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
