<?php

namespace Database\Factories\AI;

use App\Models\AI\AiCategoryPrompt;
use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiCategoryPromptFactory extends Factory
{
    protected $model = AiCategoryPrompt::class;

    public function definition(): array
    {
        $categories = [
            'industrial_automation_motion_controls',
            'computer_enterprise_it',
            'hydraulics_pneumatics',
            'electrical_equipment_supplies',
            'test_measurement_equipment',
            'cnc_metalworking',
            'healthcare_medical_equipment',
            'automotive_parts_motors',
            'consumer_electronics',
        ];

        $promptTypes = array_keys(AiCategoryPrompt::PROMPT_TYPES);

        return [
            'organization_id' => Organization::factory(),
            'category_slug' => fake()->randomElement($categories),
            'prompt_type' => fake()->randomElement($promptTypes),
            'name' => fake()->words(3, true).' Prompt',
            'description' => fake()->sentence(),
            'prompt_content' => $this->generateSamplePrompt(),
            'variable_definitions' => null,
            'is_active' => true,
            'is_system' => false,
            'priority' => fake()->numberBetween(0, 100),
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    /**
     * System prompt (read-only, no organization)
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => null,
            'is_system' => true,
            'created_by' => null,
            'updated_by' => null,
        ]);
    }

    /**
     * Inactive prompt
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Active prompt
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * For specific category
     */
    public function forCategory(string $slug): static
    {
        return $this->state(fn (array $attributes) => [
            'category_slug' => $slug,
        ]);
    }

    /**
     * For specific type
     */
    public function forType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'prompt_type' => $type,
        ]);
    }

    /**
     * Stage 1 identification prompt
     */
    public function stage1(): static
    {
        return $this->state(fn (array $attributes) => [
            'prompt_type' => 'stage1_identification',
        ]);
    }

    /**
     * Stage 2 research prompt
     */
    public function stage2Research(): static
    {
        return $this->state(fn (array $attributes) => [
            'prompt_type' => 'stage2_research',
        ]);
    }

    /**
     * Stage 2 eBay enrichment prompt
     */
    public function stage2Ebay(): static
    {
        return $this->state(fn (array $attributes) => [
            'prompt_type' => 'stage2_ebay_enrichment',
        ]);
    }

    /**
     * With specific organization
     */
    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $organization->id,
        ]);
    }

    /**
     * Without organization (system-level but not necessarily is_system=true)
     */
    public function withoutOrganization(): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => null,
        ]);
    }

    /**
     * High priority prompt
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 100,
        ]);
    }

    /**
     * Low priority prompt
     */
    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 0,
        ]);
    }

    /**
     * With custom variable definitions
     */
    public function withVariables(array $variables): static
    {
        return $this->state(fn (array $attributes) => [
            'variable_definitions' => $variables,
        ]);
    }

    /**
     * Generate a sample prompt template
     */
    protected function generateSamplePrompt(): string
    {
        return <<<'PROMPT'
EXPERT PRODUCT IDENTIFICATION

COMPONENT INFORMATION:
- Manufacturer/Brand: {brand}
- Part Number/Model: {manufacturer_part_number}
- Product Number: {product_number}
- Model Number: {model_number}
- Component Context: {user_notes}
- Additional Identifiers: {keywords}
- Condition: {condition}

USER CLASSIFICATION:
- Category: {product_category}
- Type: {product_type}
- Expected: {user_type_description}

Please analyze this product and provide identification details.

Return your analysis in JSON format:
{
  "identification_results": {
    "product_identified": true/false,
    "identification_confidence": "high/medium/low/none",
    "most_likely_product": {
      "complete_part_number": "",
      "product_name": "",
      "manufacturer": ""
    }
  }
}

Return ONLY valid JSON.
PROMPT;
    }
}
