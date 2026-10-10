<?php

namespace App\Services\Inventory;

use App\Exceptions\CategoryException;
use App\Models\Catalog\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryService
{
    /**
     * Create a new category
     */
    public function createCategory(array $data): Category
    {
        return DB::transaction(function () use ($data) {
            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateUniqueSlug($data['name'], $data['organization_id']);
            }

            // Calculate nested set values
            if (! empty($data['parent_id'])) {
                $parent = Category::findOrFail($data['parent_id']);
                $data = $this->calculateNestedSetValues($data, $parent);
            } else {
                // Root category
                $data['depth'] = 0;
                $data['lft'] = $this->getNextLeftValue($data['organization_id']);
                $data['rgt'] = $data['lft'] + 1;
                $data['path'] = $data['slug'];
            }

            return Category::create($data);
        });
    }

    /**
     * Move category to new parent
     */
    public function moveCategory(Category $category, ?Category $newParent = null): Category
    {
        return DB::transaction(function () use ($category, $newParent) {
            $this->updateNestedSetForMove($category, $newParent);
            $this->updateCategoryPath($category, $newParent);

            return $category->fresh();
        });
    }

    /**
     * Delete category and handle children
     */
    public function deleteCategory(Category $category, string $strategy = 'move_to_parent'): bool
    {
        return DB::transaction(function () use ($category, $strategy) {
            $children = $category->children;

            if ($children->isNotEmpty()) {
                switch ($strategy) {
                    case 'move_to_parent':
                        $parent = $category->parent;
                        foreach ($children as $child) {
                            $this->moveCategory($child, $parent);
                        }
                        break;
                    case 'delete_cascade':
                        foreach ($children as $child) {
                            $this->deleteCategory($child, 'delete_cascade');
                        }
                        break;
                    case 'prohibit':
                        throw new CategoryException('Cannot delete category with children');
                }
            }

            // Move products to parent category or unassign
            if ($category->products()->exists()) {
                $parentId = $category->parent?->id;
                $category->products()->update(['category_id' => $parentId]);
            }

            return $category->delete();
        });
    }

    /**
     * Get category tree
     */
    public function getCategoryTree(string $organizationId, ?string $rootId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = Category::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->orderBy('lft');

        if ($rootId) {
            $root = Category::findOrFail($rootId);
            $query->where('lft', '>=', $root->lft)
                ->where('rgt', '<=', $root->rgt);
        }

        return $query->get();
    }

    /**
     * Generate unique slug
     */
    private function generateUniqueSlug(string $name, string $organizationId): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (Category::where('organization_id', $organizationId)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Calculate nested set values for new category
     */
    private function calculateNestedSetValues(array $data, Category $parent): array
    {
        // Move existing nodes to make space
        Category::where('organization_id', $parent->organization_id)
            ->where('rgt', '>=', $parent->rgt)
            ->increment('rgt', 2);

        Category::where('organization_id', $parent->organization_id)
            ->where('lft', '>', $parent->rgt)
            ->increment('lft', 2);

        $data['depth'] = $parent->depth + 1;
        $data['lft'] = $parent->rgt;
        $data['rgt'] = $parent->rgt + 1;
        $data['path'] = $parent->path.'.'.$data['slug'];

        return $data;
    }

    /**
     * Get next left value for root category
     */
    private function getNextLeftValue(string $organizationId): int
    {
        $maxRgt = Category::where('organization_id', $organizationId)->max('rgt');

        return ($maxRgt ?? 0) + 1;
    }

    /**
     * Update nested set values when moving category
     */
    private function updateNestedSetForMove(Category $category, ?Category $newParent): void
    {
        // This is a complex operation - for now, we'll rebuild the tree
        // In production, you might want to implement a more efficient algorithm
        $this->rebuildNestedSet($category->organization_id);
    }

    /**
     * Update category path
     */
    private function updateCategoryPath(Category $category, ?Category $newParent): void
    {
        $newPath = $newParent ? $newParent->path.'.'.$category->slug : $category->slug;

        $category->update(['path' => $newPath]);

        // Update all descendants
        $descendants = $category->getDescendants();
        foreach ($descendants as $descendant) {
            $descendantPath = str_replace($category->path, $newPath, $descendant->path);
            $descendant->update(['path' => $descendantPath]);
        }
    }

    /**
     * Rebuild nested set values for entire tree
     */
    private function rebuildNestedSet(string $organizationId): void
    {
        $categories = Category::where('organization_id', $organizationId)
            ->orderBy('path')
            ->get();

        $left = 1;
        foreach ($categories as $category) {
            $category->update([
                'lft' => $left,
                'rgt' => $left + 1,
                'depth' => substr_count($category->path, '.'),
            ]);
            $left += 2;
        }
    }
}
