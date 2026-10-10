<?php

namespace Tests\Unit\Models;

use App\Models\AI\AiCategoryPrompt;
use App\Models\AI\AiPromptHistory;
use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiCategoryPromptTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_model_uses_uuid_primary_key(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create();

        $this->assertIsString($prompt->id);
        $this->assertEquals(36, strlen($prompt->id));
    }

    public function test_fillable_attributes_are_correct(): void
    {
        $data = [
            'organization_id' => $this->organization->id,
            'category_slug' => 'test_category',
            'prompt_type' => 'stage1_identification',
            'name' => 'Test Prompt',
            'description' => 'A test description',
            'prompt_content' => 'Test content with {brand}',
            'variable_definitions' => ['custom_var' => 'Custom variable'],
            'is_active' => true,
            'is_system' => false,
            'priority' => 10,
            'created_by' => $this->user->id,
        ];

        $prompt = AiCategoryPrompt::create($data);

        $this->assertEquals($data['name'], $prompt->name);
        $this->assertEquals($data['category_slug'], $prompt->category_slug);
        $this->assertEquals($data['prompt_type'], $prompt->prompt_type);
    }

    public function test_casts_are_correct(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->withVariables(['test_var' => 'Test variable description'])
            ->create([
                'is_active' => true,
                'is_system' => false,
                'priority' => 50,
            ]);

        $this->assertIsBool($prompt->is_active);
        $this->assertIsBool($prompt->is_system);
        $this->assertIsInt($prompt->priority);
        $this->assertIsArray($prompt->variable_definitions);
    }

    public function test_scope_active_filters_correctly(): void
    {
        $activePrompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->active()
            ->create();

        $inactivePrompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->inactive()
            ->create();

        $activePrompts = AiCategoryPrompt::active()->get();

        $this->assertTrue($activePrompts->contains($activePrompt));
        $this->assertFalse($activePrompts->contains($inactivePrompt));
    }

    public function test_scope_for_organization_filters_correctly(): void
    {
        $otherOrg = Organization::factory()->create();

        $orgPrompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create();

        $systemPrompt = AiCategoryPrompt::factory()
            ->system()
            ->create();

        $otherOrgPrompt = AiCategoryPrompt::factory()
            ->forOrganization($otherOrg)
            ->create();

        $prompts = AiCategoryPrompt::forOrganization($this->organization->id)->get();

        $this->assertTrue($prompts->contains($orgPrompt));
        $this->assertTrue($prompts->contains($systemPrompt));
        $this->assertFalse($prompts->contains($otherOrgPrompt));
    }

    public function test_scope_by_category_filters_correctly(): void
    {
        $category1Prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->forCategory('industrial_automation')
            ->create();

        $category2Prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->forCategory('consumer_electronics')
            ->create();

        $prompts = AiCategoryPrompt::byCategory('industrial_automation')->get();

        $this->assertTrue($prompts->contains($category1Prompt));
        $this->assertFalse($prompts->contains($category2Prompt));
    }

    public function test_scope_by_type_filters_correctly(): void
    {
        $stage1Prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->stage1()
            ->create();

        $stage2Prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->stage2Research()
            ->create();

        $prompts = AiCategoryPrompt::byType('stage1_identification')->get();

        $this->assertTrue($prompts->contains($stage1Prompt));
        $this->assertFalse($prompts->contains($stage2Prompt));
    }

    public function test_scope_system_filters_system_prompts(): void
    {
        $systemPrompt = AiCategoryPrompt::factory()
            ->system()
            ->create();

        $customPrompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create();

        $systemPrompts = AiCategoryPrompt::system()->get();

        $this->assertTrue($systemPrompts->contains($systemPrompt));
        $this->assertFalse($systemPrompts->contains($customPrompt));
    }

    public function test_render_prompt_substitutes_all_variables(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create([
                'prompt_content' => 'Brand: {brand}, Part: {manufacturer_part_number}, Notes: {user_notes}',
            ]);

        $rendered = $prompt->renderPrompt([
            'brand' => 'TestBrand',
            'manufacturer_part_number' => 'ABC123',
            'user_notes' => 'Test notes',
        ]);

        $this->assertEquals('Brand: TestBrand, Part: ABC123, Notes: Test notes', $rendered);
    }

    public function test_render_prompt_leaves_unknown_variables_intact(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create([
                'prompt_content' => 'Brand: {brand}, Unknown: {unknown_var}',
            ]);

        $rendered = $prompt->renderPrompt([
            'brand' => 'TestBrand',
        ]);

        $this->assertEquals('Brand: TestBrand, Unknown: {unknown_var}', $rendered);
    }

    public function test_get_available_variables_returns_definitions(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->withVariables(['custom_var' => 'A custom variable'])
            ->create();

        $variables = $prompt->getAvailableVariables();

        $this->assertArrayHasKey('brand', $variables);
        $this->assertArrayHasKey('manufacturer_part_number', $variables);
        $this->assertArrayHasKey('custom_var', $variables);
        $this->assertEquals('A custom variable', $variables['custom_var']);
    }

    public function test_get_used_variables_extracts_from_content(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create([
                'prompt_content' => 'Testing {brand} with {model_number} and {custom_field}',
            ]);

        $used = $prompt->getUsedVariables();

        $this->assertContains('brand', $used);
        $this->assertContains('model_number', $used);
        $this->assertContains('custom_field', $used);
        $this->assertCount(3, $used);
    }

    public function test_soft_delete_works(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create();

        $promptId = $prompt->id;
        $prompt->delete();

        $this->assertSoftDeleted('ai_category_prompts', ['id' => $promptId]);
        $this->assertNotNull(AiCategoryPrompt::withTrashed()->find($promptId));
    }

    public function test_organization_relationship(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create();

        $this->assertInstanceOf(Organization::class, $prompt->organization);
        $this->assertEquals($this->organization->id, $prompt->organization->id);
    }

    public function test_creator_relationship(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create(['created_by' => $this->user->id]);

        $this->assertInstanceOf(User::class, $prompt->creator);
        $this->assertEquals($this->user->id, $prompt->creator->id);
    }

    public function test_history_relationship(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create();

        AiPromptHistory::recordChange($prompt, 'created', $this->user->id);
        AiPromptHistory::recordChange($prompt, 'updated', $this->user->id);

        $this->assertCount(2, $prompt->history);
        $this->assertInstanceOf(AiPromptHistory::class, $prompt->history->first());
    }

    public function test_is_editable_returns_false_for_system_prompts(): void
    {
        $systemPrompt = AiCategoryPrompt::factory()->system()->create();
        $customPrompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create();

        $this->assertFalse($systemPrompt->isEditable());
        $this->assertTrue($customPrompt->isEditable());
    }

    public function test_is_deletable_returns_false_for_system_prompts(): void
    {
        $systemPrompt = AiCategoryPrompt::factory()->system()->create();
        $customPrompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create();

        $this->assertFalse($systemPrompt->isDeletable());
        $this->assertTrue($customPrompt->isDeletable());
    }

    public function test_get_prompt_type_label_returns_correct_label(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->stage1()
            ->create();

        $this->assertEquals('Stage 1: Initial Identification', $prompt->getPromptTypeLabel());
    }

    public function test_duplicate_for_creates_copy(): void
    {
        $originalPrompt = AiCategoryPrompt::factory()
            ->system()
            ->create([
                'name' => 'Original Prompt',
                'category_slug' => 'test_category',
            ]);

        $duplicate = $originalPrompt->duplicateFor($this->organization->id, $this->user->id);

        $this->assertNotEquals($originalPrompt->id, $duplicate->id);
        $this->assertEquals('Original Prompt (Copy)', $duplicate->name);
        $this->assertEquals($this->organization->id, $duplicate->organization_id);
        $this->assertFalse($duplicate->is_system);
        $this->assertEquals($originalPrompt->category_slug, $duplicate->category_slug);
        $this->assertEquals($originalPrompt->prompt_content, $duplicate->prompt_content);
    }

    public function test_get_character_count_returns_content_length(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create(['prompt_content' => 'Hello World']);

        $this->assertEquals(11, $prompt->getCharacterCount());
    }

    public function test_validate_variables_finds_undefined_variables(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create([
                'prompt_content' => '{brand} {undefined_var} {another_undefined}',
            ]);

        $undefined = $prompt->validateVariables();

        $this->assertContains('undefined_var', $undefined);
        $this->assertContains('another_undefined', $undefined);
        $this->assertNotContains('brand', $undefined);
    }

    public function test_priority_ordering_scope(): void
    {
        $lowPriority = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->lowPriority()
            ->create();

        $highPriority = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->highPriority()
            ->create();

        $prompts = AiCategoryPrompt::byPriority()->get();

        $this->assertEquals($highPriority->id, $prompts->first()->id);
    }

    public function test_static_get_prompt_types_returns_all_types(): void
    {
        $types = AiCategoryPrompt::getPromptTypes();

        $this->assertArrayHasKey('stage1_identification', $types);
        $this->assertArrayHasKey('stage2_research', $types);
        $this->assertArrayHasKey('stage2_ebay_enrichment', $types);
    }

    public function test_static_get_standard_variables_returns_all_variables(): void
    {
        $variables = AiCategoryPrompt::getStandardVariables();

        $this->assertArrayHasKey('brand', $variables);
        $this->assertArrayHasKey('manufacturer_part_number', $variables);
        $this->assertArrayHasKey('product_category', $variables);
        $this->assertArrayHasKey('product_type', $variables);
    }
}
