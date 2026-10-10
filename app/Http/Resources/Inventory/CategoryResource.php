<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'path' => $this->path,
            'depth' => $this->depth,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'image_url' => $this->image_url,
            'meta_data' => $this->meta_data,

            // Tree structure
            'is_leaf' => $this->isLeaf(),
            'is_root' => $this->isRoot(),
            'breadcrumb' => $this->getBreadcrumb(),

            // Relationships
            'attributes' => CategoryAttributeResource::collection($this->whenLoaded('attributes')),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'parent' => new CategoryResource($this->whenLoaded('parent')),
            'products_count' => $this->whenCounted('products'),

            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
