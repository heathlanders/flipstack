<?php

namespace Tests\Unit\Services;

use App\Models\AI\AiCategoryPrompt;
use App\Models\Organization\Organization;
use App\Services\AI\PromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PromptService $service;

    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PromptService;
        $this->organization = Organization::factory()->create();
    }

    public function test_render_prompt_string_substitutes_variables(): void
    {
        $content = 'Brand: {brand}, Part: {manufacturer_part_number}';
        $variables = [
            'brand' => 'TestBrand',
            'manufacturer_part_number' => 'ABC123',
        ];

        $result = $this->service->renderPromptString($content, $variables);

        $this->assertEquals('Brand: TestBrand, Part: ABC123', $result);
    }

    public function test_render_prompt_string_handles_empty_variables(): void
    {
        $content = 'Brand: {brand}, Part: {manufacturer_part_number}';
        $variables = [];

        $result = $this->service->renderPromptString($content, $variables);

        $this->assertEquals('Brand: {brand}, Part: {manufacturer_part_number}', $result);
    }

    public function test_get_default_variables_returns_expected_list(): void
    {
        $variables = $this->service->getDefaultVariables();

        $this->assertArrayHasKey('brand', $variables);
        $this->assertArrayHasKey('manufacturer_part_number', $variables);
        $this->assertArrayHasKey('product_number', $variables);
        $this->assertArrayHasKey('model_number', $variables);
        $this->assertArrayHasKey('user_notes', $variables);
        $this->assertArrayHasKey('keywords', $variables);
        $this->assertArrayHasKey('condition', $variables);
        $this->assertArrayHasKey('product_category', $variables);
        $this->assertArrayHasKey('product_type', $variables);
        $this->assertArrayHasKey('user_type_description', $variables);
    }

    public function test_get_prompt_templates_returns_all_templates(): void
    {
        $templates = $this->service->getPromptTemplates();

        $this->assertArrayHasKey('basic', $templates);
        $this->assertArrayHasKey('detailed', $templates);
        $this->assertArrayHasKey('ebay', $templates);

        foreach ($templates as $template) {
            $this->assertArrayHasKey('name', $template);
            $this->assertArrayHasKey('description', $template);
            $this->assertArrayHasKey('content', $template);
        }
    }

    public function test_get_category_slugs_returns_all_categories(): void
    {
        $categories = $this->service->getCategorySlugs();

        $this->assertArrayHasKey('industrial_automation_motion_controls', $categories);
        $this->assertArrayHasKey('computer_enterprise_it', $categories);
        $this->assertArrayHasKey('consumer_electronics', $categories);
        $this->assertArrayHasKey('advanced_general_product', $categories);
        $this->assertGreaterThan(20, count($categories));
    }

    public function test_get_sample_data_returns_complete_data(): void
    {
        $sampleData = $this->service->getSampleData();

        $this->assertArrayHasKey('brand', $sampleData);
        $this->assertArrayHasKey('manufacturer_part_number', $sampleData);
        $this->assertArrayHasKey('product_type', $sampleData);
        $this->assertNotEmpty($sampleData['brand']);
    }

    public function test_get_prompt_returns_org_prompt_when_exists(): void
    {
        $systemPrompt = AiCategoryPrompt::factory()
            ->system()
            ->forCategory('test_category')
            ->stage1()
            ->create(['priority' => 0]);

        $orgPrompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->forCategory('test_category')
            ->stage1()
            ->create(['priority' => 0]);

        $result = $this->service->getPromptForCategory(
            'stage1_identification',
            'test_category',
            $this->organization->id
        );

        $this->assertNotNull($result);
        $this->assertEquals($orgPrompt->id, $result->id);
    }

    public function test_get_prompt_falls_back_to_system_prompt(): void
    {
        $systemPrompt = AiCategoryPrompt::factory()
            ->system()
            ->forCategory('test_category')
            ->stage1()
            ->create();

        $result = $this->service->getPromptForCategory(
            'stage1_identification',
            'test_category',
            $this->organization->id
        );

        $this->assertNotNull($result);
        $this->assertEquals($systemPrompt->id, $result->id);
        $this->assertTrue($result->is_system);
    }

    public function test_get_prompt_returns_null_when_no_prompt_exists(): void
    {
        $result = $this->service->getPromptForCategory(
            'stage1_identification',
            'nonexistent_category',
            $this->organization->id
        );

        $this->assertNull($result);
    }

    public function test_higher_priority_prompt_is_selected(): void
    {
        $lowPriorityPrompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->forCategory('test_category')
            ->stage1()
            ->lowPriority()
            ->create();

        $highPriorityPrompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->forCategory('test_category')
            ->stage1()
            ->highPriority()
            ->create();

        $result = $this->service->getPromptForCategory(
            'stage1_identification',
            'test_category',
            $this->organization->id
        );

        $this->assertEquals($highPriorityPrompt->id, $result->id);
    }

    public function test_inactive_prompts_are_not_returned(): void
    {
        $inactivePrompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->forCategory('test_category')
            ->stage1()
            ->inactive()
            ->create();

        $result = $this->service->getPromptForCategory(
            'stage1_identification',
            'test_category',
            $this->organization->id
        );

        $this->assertNull($result);
    }

    public function test_render_prompt_uses_model_method(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create([
                'prompt_content' => 'Test {brand} content',
            ]);

        $result = $this->service->renderPrompt($prompt, ['brand' => 'MyBrand']);

        $this->assertEquals('Test MyBrand content', $result);
    }

    public function test_clear_cache_does_not_throw_exception(): void
    {
        // Just verify this doesn't throw
        $this->service->clearCache($this->organization->id, 'test_category');
        $this->service->clearCache($this->organization->id);
        $this->service->clearCache();

        $this->assertTrue(true);
    }
}
