<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\Category;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'organization_id' => Organization::factory(),
            'parent_id' => null,
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'color' => $this->faker->hexColor(),
            'icon' => $this->faker->randomElement(['box', 'tag', 'folder', 'archive', 'package']),
            'depth' => 0,
            'lft' => 1,
            'rgt' => 2,
            'sort_order' => $this->faker->numberBetween(1, 100),
            'is_active' => true,
            'metadata' => [],
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withParent(Category $parent): static
    {
        return $this->state([
            'parent_id' => $parent->id,
            'organization_id' => $parent->organization_id,
            'depth' => $parent->depth + 1,
        ]);
    }
}
