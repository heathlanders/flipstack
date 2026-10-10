<?php

namespace Tests\Feature\Admin;

use App\Models\AI\AiCategoryPrompt;
use App\Models\AI\AiPromptHistory;
use App\Models\Organization\Organization;
use App\Models\User\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class AiPromptManagementTest extends TestCase
{
    use RefreshDatabase;
    use WithoutMiddleware;

    protected Organization $organization;

    protected User $user;

    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();

        $this->adminRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
            'is_system' => true,
            'permissions' => [
                'ai_prompts.view',
                'ai_prompts.create',
                'ai_prompts.edit',
                'ai_prompts.delete',
                'admin.access',
            ],
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->user->roles()->attach($this->adminRole->id, [
            'organization_id' => $this->organization->id,
            'team_id' => null,
        ]);
    }

    // ================================
    // INDEX & FILTERING TESTS
    // ================================

    public function test_index_displays_prompts_list(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.ai-prompts.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/AiPrompts/Index')
            ->has('prompts')
        );
    }

    public function test_index_filters_by_category(): void
    {
        $prompt1 = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->forCategory('industrial_automation')
            ->create();

        $prompt2 = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->forCategory('consumer_electronics')
            ->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.ai-prompts.index', ['category' => 'industrial_automation']));

        $response->assertStatus(200);
    }

    public function test_index_filters_by_type(): void
    {
        $stage1Prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->stage1()
            ->create();

        $stage2Prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->stage2Research()
            ->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.ai-prompts.index', ['type' => 'stage1_identification']));

        $response->assertStatus(200);
    }

    public function test_index_search_filters_by_name(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create(['name' => 'Unique Search Test Prompt']);

        $response = $this->actingAs($this->user)
            ->get(route('admin.ai-prompts.index', ['search' => 'Unique Search']));

        $response->assertStatus(200);
    }

    public function test_index_shows_both_system_and_org_prompts(): void
    {
        $systemPrompt = AiCategoryPrompt::factory()->system()->create();
        $orgPrompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.ai-prompts.index'));

        $response->assertStatus(200);
    }

    // ================================
    // CREATE TESTS
    // ================================

    public function test_create_displays_form_with_categories_and_types(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('admin.ai-prompts.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/AiPrompts/Edit')
            ->has('categories')
            ->has('prompt_types')
            ->has('variables')
            ->where('is_new', true)
        );
    }

    public function test_store_creates_prompt_for_organization(): void
    {
        $data = [
            'category_slug' => 'industrial_automation',
            'prompt_type' => 'stage1_identification',
            'name' => 'Test Prompt',
            'description' => 'Test description',
            'prompt_content' => str_repeat('Test content with {brand} variable. ', 10),
            'is_active' => true,
            'priority' => 10,
        ];

        $response = $this->actingAs($this->user)
            ->post(route('admin.ai-prompts.store'), $data);

        $response->assertRedirect(route('admin.ai-prompts.index'));

        $this->assertDatabaseHas('ai_category_prompts', [
            'name' => 'Test Prompt',
            'organization_id' => $this->organization->id,
            'is_system' => false,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('admin.ai-prompts.store'), []);

        $response->assertSessionHasErrors(['category_slug', 'prompt_type', 'name', 'prompt_content']);
    }

    public function test_store_sets_created_by_to_current_user(): void
    {
        $data = [
            'category_slug' => 'test_category',
            'prompt_type' => 'stage1_identification',
            'name' => 'Test Prompt',
            'prompt_content' => str_repeat('Test content. ', 20),
        ];

        $this->actingAs($this->user)
            ->post(route('admin.ai-prompts.store'), $data);

        $this->assertDatabaseHas('ai_category_prompts', [
            'name' => 'Test Prompt',
            'created_by' => $this->user->id,
        ]);
    }

    // ================================
    // EDIT/UPDATE TESTS
    // ================================

    public function test_edit_displays_form_for_org_prompt(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.ai-prompts.edit', $prompt));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/AiPrompts/Edit')
            ->where('is_new', false)
            ->has('prompt')
        );
    }

    public function test_edit_displays_readonly_for_system_prompt(): void
    {
        $systemPrompt = AiCategoryPrompt::factory()->system()->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.ai-prompts.edit', $systemPrompt));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('prompt.is_system', true)
            ->where('prompt.can_edit', false)
        );
    }

    public function test_update_modifies_prompt(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.ai-prompts.update', $prompt), [
                'name' => 'Updated Name',
                'prompt_content' => str_repeat('Updated content. ', 20),
            ]);

        $response->assertRedirect(route('admin.ai-prompts.index'));

        $this->assertDatabaseHas('ai_category_prompts', [
            'id' => $prompt->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_update_creates_history_entry(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create(['prompt_content' => 'Original content that is long enough for validation purposes.']);

        $this->actingAs($this->user)
            ->put(route('admin.ai-prompts.update', $prompt), [
                'prompt_content' => str_repeat('New content. ', 20),
            ]);

        $this->assertDatabaseHas('ai_prompt_history', [
            'prompt_id' => $prompt->id,
            'action' => 'updated',
        ]);
    }

    public function test_update_cannot_modify_system_prompt(): void
    {
        $systemPrompt = AiCategoryPrompt::factory()->system()->create();

        $response = $this->actingAs($this->user)
            ->put(route('admin.ai-prompts.update', $systemPrompt), [
                'name' => 'Should Not Update',
            ]);

        $response->assertStatus(403);
    }

    // ================================
    // DELETE TESTS
    // ================================

    public function test_destroy_soft_deletes_org_prompt(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.ai-prompts.destroy', $prompt));

        $response->assertRedirect(route('admin.ai-prompts.index'));
        $this->assertSoftDeleted('ai_category_prompts', ['id' => $prompt->id]);
    }

    public function test_destroy_prevents_deletion_of_system_prompt(): void
    {
        $systemPrompt = AiCategoryPrompt::factory()->system()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('admin.ai-prompts.destroy', $systemPrompt));

        $response->assertSessionHasErrors();
        $this->assertNotSoftDeleted('ai_category_prompts', ['id' => $systemPrompt->id]);
    }

    // ================================
    // PREVIEW & TEST
    // ================================

    public function test_preview_renders_prompt_with_variables(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create(['prompt_content' => 'Brand: {brand}, Model: {model_number}']);

        $response = $this->actingAs($this->user)
            ->postJson(route('admin.ai-prompts.preview', $prompt), [
                'sample_data' => [
                    'brand' => 'TestBrand',
                    'model_number' => 'ABC123',
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'rendered' => 'Brand: TestBrand, Model: ABC123',
        ]);
    }

    // ================================
    // DUPLICATE & HISTORY
    // ================================

    public function test_duplicate_creates_copy_for_organization(): void
    {
        $originalPrompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create(['name' => 'Original Prompt']);

        $response = $this->actingAs($this->user)
            ->post(route('admin.ai-prompts.duplicate', $originalPrompt));

        $response->assertRedirect();

        $this->assertDatabaseHas('ai_category_prompts', [
            'name' => 'Original Prompt (Copy)',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_duplicate_does_not_copy_is_system_flag(): void
    {
        $systemPrompt = AiCategoryPrompt::factory()->system()->create();

        $this->actingAs($this->user)
            ->post(route('admin.ai-prompts.duplicate', $systemPrompt));

        $duplicate = AiCategoryPrompt::where('name', $systemPrompt->name.' (Copy)')->first();

        $this->assertNotNull($duplicate);
        $this->assertFalse($duplicate->is_system);
    }

    public function test_history_displays_all_changes(): void
    {
        $prompt = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->create();

        AiPromptHistory::recordChange($prompt, 'created', $this->user->id);
        AiPromptHistory::recordChange($prompt, 'updated', $this->user->id);

        $response = $this->actingAs($this->user)
            ->get(route('admin.ai-prompts.history', $prompt));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/AiPrompts/History')
            ->has('history.data', 2)
        );
    }

    // ================================
    // IMPORT/EXPORT
    // ================================

    public function test_export_returns_json(): void
    {
        AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->count(3)
            ->create();

        $response = $this->actingAs($this->user)
            ->get(route('admin.ai-prompts.export'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'version',
            'exported_at',
            'count',
            'prompts',
        ]);
    }

    public function test_export_selected_prompts_only(): void
    {
        $prompts = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->count(3)
            ->create();

        $selectedIds = $prompts->take(2)->pluck('id')->toArray();

        $response = $this->actingAs($this->user)
            ->get(route('admin.ai-prompts.export', ['ids' => implode(',', $selectedIds)]));

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(2, $data['count']);
    }

    // ================================
    // BULK OPERATIONS
    // ================================

    public function test_bulk_activate_prompts(): void
    {
        $prompts = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->inactive()
            ->count(3)
            ->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.ai-prompts.bulk-update'), [
                'ids' => $prompts->pluck('id')->toArray(),
                'action' => 'activate',
            ]);

        $response->assertRedirect();

        foreach ($prompts as $prompt) {
            $this->assertDatabaseHas('ai_category_prompts', [
                'id' => $prompt->id,
                'is_active' => true,
            ]);
        }
    }

    public function test_bulk_deactivate_prompts(): void
    {
        $prompts = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->active()
            ->count(3)
            ->create();

        $response = $this->actingAs($this->user)
            ->post(route('admin.ai-prompts.bulk-update'), [
                'ids' => $prompts->pluck('id')->toArray(),
                'action' => 'deactivate',
            ]);

        $response->assertRedirect();

        foreach ($prompts as $prompt) {
            $this->assertDatabaseHas('ai_category_prompts', [
                'id' => $prompt->id,
                'is_active' => false,
            ]);
        }
    }

    public function test_bulk_update_ignores_system_prompts(): void
    {
        $systemPrompts = AiCategoryPrompt::factory()
            ->system()
            ->active()
            ->count(2)
            ->create();

        $orgPrompts = AiCategoryPrompt::factory()
            ->forOrganization($this->organization)
            ->active()
            ->count(2)
            ->create();

        $allIds = $systemPrompts->pluck('id')
            ->merge($orgPrompts->pluck('id'))
            ->toArray();

        $this->actingAs($this->user)
            ->post(route('admin.ai-prompts.bulk-update'), [
                'ids' => $allIds,
                'action' => 'deactivate',
            ]);

        // System prompts should still be active
        foreach ($systemPrompts as $prompt) {
            $this->assertDatabaseHas('ai_category_prompts', [
                'id' => $prompt->id,
                'is_active' => true,
            ]);
        }

        // Org prompts should be deactivated
        foreach ($orgPrompts as $prompt) {
            $this->assertDatabaseHas('ai_category_prompts', [
                'id' => $prompt->id,
                'is_active' => false,
            ]);
        }
    }

    // ================================
    // PERMISSION TESTS
    // ================================

    public function test_index_requires_view_permission(): void
    {
        $userWithoutPermission = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $roleWithoutPermission = Role::factory()->create([
            'permissions' => [],
        ]);

        $userWithoutPermission->roles()->attach($roleWithoutPermission->id, [
            'organization_id' => $this->organization->id,
            'team_id' => null,
        ]);

        $response = $this->withMiddleware()
            ->actingAs($userWithoutPermission)
            ->get(route('admin.ai-prompts.index'));

        $response->assertStatus(403);
    }
}
