<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAiPromptRequest;
use App\Http\Requests\Admin\UpdateAiPromptRequest;
use App\Models\AI\AiCategoryPrompt;
use App\Models\AI\AiPromptHistory;
use App\Services\AI\PromptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AiPromptController extends Controller
{
    protected PromptService $promptService;

    public function __construct(PromptService $promptService)
    {
        $this->promptService = $promptService;
        $this->middleware(['auth']);
    }

    /**
     * Display prompts index
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        $query = AiCategoryPrompt::with(['creator', 'updater'])
            ->forOrganization($organization?->id);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category_slug', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('category_slug', $request->category);
        }

        // Type filter
        if ($request->filled('type')) {
            $query->where('prompt_type', $request->type);
        }

        // Status filter
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'inactive') {
                $query->inactive();
            }
        }

        // Scope filter (system vs custom)
        if ($request->filled('scope')) {
            if ($request->scope === 'system') {
                $query->system();
            } elseif ($request->scope === 'custom') {
                $query->custom();
            }
        }

        $prompts = $query->orderBy('category_slug')
            ->orderByDesc('priority')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // Transform prompts data
        $prompts->through(function ($prompt) {
            return [
                'id' => $prompt->id,
                'name' => $prompt->name,
                'category_slug' => $prompt->category_slug,
                'category_display' => $this->promptService->getCategorySlugs()[$prompt->category_slug] ?? $prompt->category_slug,
                'prompt_type' => $prompt->prompt_type,
                'prompt_type_label' => $prompt->getPromptTypeLabel(),
                'description' => $prompt->description,
                'is_active' => $prompt->is_active,
                'is_system' => $prompt->is_system,
                'priority' => $prompt->priority,
                'character_count' => $prompt->getCharacterCount(),
                'created_by' => $prompt->creator?->name,
                'updated_by' => $prompt->updater?->name,
                'created_at' => $prompt->created_at?->diffForHumans(),
                'updated_at' => $prompt->updated_at?->diffForHumans(),
                'can_edit' => $prompt->isEditable(),
                'can_delete' => $prompt->isDeletable(),
            ];
        });

        // Stats
        $stats = [
            'total' => AiCategoryPrompt::forOrganization($organization?->id)->count(),
            'system' => AiCategoryPrompt::system()->count(),
            'custom' => AiCategoryPrompt::forOrganization($organization?->id)->custom()->count(),
            'active' => AiCategoryPrompt::forOrganization($organization?->id)->active()->count(),
            'by_type' => [
                'stage1' => AiCategoryPrompt::forOrganization($organization?->id)->byType('stage1_identification')->count(),
                'stage2_research' => AiCategoryPrompt::forOrganization($organization?->id)->byType('stage2_research')->count(),
                'stage2_ebay' => AiCategoryPrompt::forOrganization($organization?->id)->byType('stage2_ebay_enrichment')->count(),
            ],
        ];

        return Inertia::render('Admin/AiPrompts/Index', [
            'prompts' => $prompts,
            'stats' => $stats,
            'filters' => $request->only(['search', 'category', 'type', 'status', 'scope']),
            'categories' => $this->promptService->getCategorySlugs(),
            'prompt_types' => AiCategoryPrompt::getPromptTypes(),
            'can' => [
                'create' => $user->hasPermission('ai_prompts.create'),
                'edit' => $user->hasPermission('ai_prompts.edit'),
                'delete' => $user->hasPermission('ai_prompts.delete'),
            ],
        ]);
    }

    /**
     * Show create form
     */
    public function create(Request $request)
    {
        return Inertia::render('Admin/AiPrompts/Edit', [
            'prompt' => null,
            'categories' => $this->promptService->getCategorySlugs(),
            'prompt_types' => AiCategoryPrompt::getPromptTypes(),
            'variables' => AiCategoryPrompt::getStandardVariables(),
            'templates' => $this->promptService->getPromptTemplates(),
            'sample_data' => $this->promptService->getSampleData(),
            'is_new' => true,
        ]);
    }

    /**
     * Store new prompt
     */
    public function store(StoreAiPromptRequest $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        try {
            $prompt = AiCategoryPrompt::create([
                'organization_id' => $organization?->id,
                'category_slug' => $request->category_slug,
                'prompt_type' => $request->prompt_type,
                'name' => $request->name,
                'description' => $request->description,
                'prompt_content' => $request->prompt_content,
                'variable_definitions' => $request->variable_definitions,
                'is_active' => $request->is_active,
                'is_system' => false,
                'priority' => $request->priority ?? 0,
                'created_by' => $user->id,
            ]);

            // Record history
            AiPromptHistory::recordChange($prompt, 'created', $user->id);

            // Clear cache
            $this->promptService->clearCache($organization?->id, $request->category_slug);

            Log::info('AI Prompt created', [
                'prompt_id' => $prompt->id,
                'user_id' => $user->id,
                'category' => $request->category_slug,
            ]);

            return redirect()->route('admin.ai-prompts.index')
                ->with('success', 'Prompt created successfully!');

        } catch (\Exception $e) {
            Log::error('Failed to create AI prompt', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return back()->withErrors(['error' => 'Failed to create prompt. Please try again.']);
        }
    }

    /**
     * Show edit form
     */
    public function edit($prompt)
    {
        // Ensure we have the model, not just the ID
        if (is_string($prompt)) {
            $prompt = AiCategoryPrompt::findOrFail($prompt);
        }

        $prompt->load(['creator', 'updater', 'history' => function ($q) {
            $q->with('user')->limit(10);
        }]);

        return Inertia::render('Admin/AiPrompts/Edit', [
            'prompt' => [
                'id' => $prompt->id,
                'name' => $prompt->name,
                'category_slug' => $prompt->category_slug,
                'prompt_type' => $prompt->prompt_type,
                'description' => $prompt->description,
                'prompt_content' => $prompt->prompt_content,
                'variable_definitions' => $prompt->variable_definitions,
                'is_active' => $prompt->is_active,
                'is_system' => $prompt->is_system,
                'priority' => $prompt->priority,
                'character_count' => $prompt->getCharacterCount(),
                'used_variables' => $prompt->getUsedVariables(),
                'undefined_variables' => $prompt->validateVariables(),
                'created_by' => $prompt->creator?->name,
                'updated_by' => $prompt->updater?->name,
                'created_at' => $prompt->created_at?->toISOString(),
                'updated_at' => $prompt->updated_at?->toISOString(),
                'can_edit' => $prompt->isEditable(),
                'recent_history' => $prompt->history->map(fn ($h) => [
                    'id' => $h->id,
                    'action' => $h->action,
                    'action_label' => $h->getActionLabel(),
                    'summary' => $h->getChangeSummary(),
                    'user' => $h->user?->name,
                    'created_at' => $h->created_at?->diffForHumans(),
                    'change_reason' => $h->change_reason,
                ]),
            ],
            'categories' => $this->promptService->getCategorySlugs(),
            'prompt_types' => AiCategoryPrompt::getPromptTypes(),
            'variables' => AiCategoryPrompt::getStandardVariables(),
            'templates' => $this->promptService->getPromptTemplates(),
            'sample_data' => $this->promptService->getSampleData(),
            'is_new' => false,
        ]);
    }

    /**
     * Update prompt
     */
    public function update(UpdateAiPromptRequest $request, $prompt)
    {
        // Ensure we have the model, not just the ID
        if (is_string($prompt)) {
            $prompt = AiCategoryPrompt::findOrFail($prompt);
        }

        $user = $request->user();
        $organization = $user->organization;

        if ($prompt->is_system) {
            abort(403, 'System prompts cannot be modified.');
        }

        try {
            // Track changes for history
            $previousContent = $prompt->prompt_content;
            $changedFields = [];

            foreach ($request->validated() as $key => $value) {
                if ($key !== 'change_reason' && $value != $prompt->$key) {
                    $changedFields[] = $key;
                }
            }

            // Update prompt
            $prompt->update(array_merge($request->validated(), [
                'updated_by' => $user->id,
            ]));

            // Record history if content changed
            if (in_array('prompt_content', $changedFields) || count($changedFields) > 0) {
                AiPromptHistory::recordChange(
                    $prompt,
                    'updated',
                    $user->id,
                    $previousContent,
                    $prompt->prompt_content,
                    $changedFields,
                    $request->change_reason
                );
            }

            // Clear cache
            $this->promptService->clearCache($organization?->id, $prompt->category_slug);

            Log::info('AI Prompt updated', [
                'prompt_id' => $prompt->id,
                'user_id' => $user->id,
                'changed_fields' => $changedFields,
            ]);

            return redirect()->route('admin.ai-prompts.index')
                ->with('success', 'Prompt updated successfully!');

        } catch (\Exception $e) {
            Log::error('Failed to update AI prompt', [
                'prompt_id' => $prompt->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to update prompt. Please try again.']);
        }
    }

    /**
     * Delete prompt
     */
    public function destroy($prompt)
    {
        // Ensure we have the model, not just the ID
        if (is_string($prompt)) {
            $prompt = AiCategoryPrompt::findOrFail($prompt);
        }

        $user = auth()->user();

        if ($prompt->is_system) {
            return back()->withErrors(['error' => 'System prompts cannot be deleted.']);
        }

        if (! $user->hasPermission('ai_prompts.delete')) {
            abort(403);
        }

        try {
            $categorySlug = $prompt->category_slug;
            $organizationId = $prompt->organization_id;

            // Record history before delete
            AiPromptHistory::recordChange($prompt, 'deleted', $user->id);

            $prompt->delete();

            // Clear cache
            $this->promptService->clearCache($organizationId, $categorySlug);

            Log::info('AI Prompt deleted', [
                'prompt_id' => $prompt->id,
                'user_id' => $user->id,
            ]);

            return redirect()->route('admin.ai-prompts.index')
                ->with('success', 'Prompt deleted successfully!');

        } catch (\Exception $e) {
            Log::error('Failed to delete AI prompt', [
                'prompt_id' => $prompt->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to delete prompt. Please try again.']);
        }
    }

    /**
     * Preview rendered prompt with sample data
     */
    public function preview(Request $request, $prompt)
    {
        // Ensure we have the model, not just the ID
        if (is_string($prompt)) {
            $prompt = AiCategoryPrompt::findOrFail($prompt);
        }

        $sampleData = $request->input('sample_data', $this->promptService->getSampleData());

        $rendered = $prompt->renderPrompt($sampleData);

        return response()->json([
            'rendered' => $rendered,
            'variables_used' => $prompt->getUsedVariables(),
            'undefined_variables' => $prompt->validateVariables(),
            'character_count' => strlen($rendered),
        ]);
    }

    /**
     * Test prompt with Gemini API
     */
    public function test(Request $request, $prompt)
    {
        // Ensure we have the model, not just the ID
        if (is_string($prompt)) {
            $prompt = AiCategoryPrompt::findOrFail($prompt);
        }

        $sampleData = $request->input('sample_data', $this->promptService->getSampleData());

        $result = $this->promptService->testPromptWithApi(
            $prompt->prompt_content,
            $sampleData
        );

        return response()->json($result);
    }

    /**
     * Duplicate a prompt
     */
    public function duplicate($prompt)
    {
        // Ensure we have the model, not just the ID
        if (is_string($prompt)) {
            $prompt = AiCategoryPrompt::findOrFail($prompt);
        }

        $user = auth()->user();
        $organization = $user->organization;

        if (! $user->hasPermission('ai_prompts.create')) {
            abort(403);
        }

        try {
            $duplicate = $prompt->duplicateFor($organization?->id, $user->id);

            // Record history
            AiPromptHistory::recordChange($duplicate, 'duplicated', $user->id);

            Log::info('AI Prompt duplicated', [
                'original_id' => $prompt->id,
                'duplicate_id' => $duplicate->id,
                'user_id' => $user->id,
            ]);

            return redirect()->route('admin.ai-prompts.edit', $duplicate)
                ->with('success', 'Prompt duplicated successfully!');

        } catch (\Exception $e) {
            Log::error('Failed to duplicate AI prompt', [
                'prompt_id' => $prompt->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to duplicate prompt. Please try again.']);
        }
    }

    /**
     * View prompt history
     */
    public function history($prompt)
    {
        // Ensure we have the model, not just the ID
        if (is_string($prompt)) {
            $prompt = AiCategoryPrompt::findOrFail($prompt);
        }

        $history = $prompt->history()
            ->with('user')
            ->paginate(20);

        return Inertia::render('Admin/AiPrompts/History', [
            'prompt' => [
                'id' => $prompt->id,
                'name' => $prompt->name,
            ],
            'history' => $history->through(fn ($h) => [
                'id' => $h->id,
                'action' => $h->action,
                'action_label' => $h->getActionLabel(),
                'summary' => $h->getChangeSummary(),
                'previous_content' => $h->previous_content,
                'new_content' => $h->new_content,
                'changed_fields' => $h->changed_fields,
                'user' => $h->user?->name,
                'change_reason' => $h->change_reason,
                'created_at' => $h->created_at?->toISOString(),
                'created_at_human' => $h->created_at?->diffForHumans(),
            ]),
        ]);
    }

    /**
     * Export prompts to JSON
     */
    public function export(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        $query = AiCategoryPrompt::forOrganization($organization?->id);

        // Export specific prompts if IDs provided
        if ($request->filled('ids')) {
            $ids = is_array($request->ids) ? $request->ids : explode(',', $request->ids);
            $query->whereIn('id', $ids);
        }

        // Exclude system prompts by default
        if (! $request->boolean('include_system')) {
            $query->custom();
        }

        $prompts = $query->get()->map(fn ($p) => [
            'category_slug' => $p->category_slug,
            'prompt_type' => $p->prompt_type,
            'name' => $p->name,
            'description' => $p->description,
            'prompt_content' => $p->prompt_content,
            'variable_definitions' => $p->variable_definitions,
            'is_active' => $p->is_active,
            'priority' => $p->priority,
        ]);

        return response()->json([
            'version' => '1.0',
            'exported_at' => now()->toISOString(),
            'count' => $prompts->count(),
            'prompts' => $prompts,
        ])->header('Content-Disposition', 'attachment; filename="ai-prompts-export.json"');
    }

    /**
     * Import prompts from JSON
     */
    public function import(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        if (! $user->hasPermission('ai_prompts.create')) {
            abort(403);
        }

        $request->validate([
            'file' => 'required|file|mimes:json|max:10240',
            'conflict_resolution' => 'in:skip,overwrite',
        ]);

        try {
            $content = json_decode(file_get_contents($request->file('file')->path()), true);

            if (! isset($content['prompts']) || ! is_array($content['prompts'])) {
                return back()->withErrors(['file' => 'Invalid import file format.']);
            }

            $imported = 0;
            $skipped = 0;
            $resolution = $request->input('conflict_resolution', 'skip');

            DB::transaction(function () use ($content, $user, $organization, $resolution, &$imported, &$skipped) {
                foreach ($content['prompts'] as $promptData) {
                    // Check for existing prompt with same name/category/type
                    $existing = AiCategoryPrompt::where('organization_id', $organization?->id)
                        ->where('category_slug', $promptData['category_slug'])
                        ->where('prompt_type', $promptData['prompt_type'])
                        ->where('name', $promptData['name'])
                        ->first();

                    if ($existing) {
                        if ($resolution === 'skip') {
                            $skipped++;

                            continue;
                        }
                        // Overwrite
                        $existing->update([
                            'description' => $promptData['description'] ?? null,
                            'prompt_content' => $promptData['prompt_content'],
                            'variable_definitions' => $promptData['variable_definitions'] ?? null,
                            'is_active' => $promptData['is_active'] ?? true,
                            'priority' => $promptData['priority'] ?? 0,
                            'updated_by' => $user->id,
                        ]);
                        AiPromptHistory::recordChange($existing, 'updated', $user->id, null, null, ['import']);
                        $imported++;
                    } else {
                        $prompt = AiCategoryPrompt::create([
                            'organization_id' => $organization?->id,
                            'category_slug' => $promptData['category_slug'],
                            'prompt_type' => $promptData['prompt_type'],
                            'name' => $promptData['name'],
                            'description' => $promptData['description'] ?? null,
                            'prompt_content' => $promptData['prompt_content'],
                            'variable_definitions' => $promptData['variable_definitions'] ?? null,
                            'is_active' => $promptData['is_active'] ?? true,
                            'is_system' => false,
                            'priority' => $promptData['priority'] ?? 0,
                            'created_by' => $user->id,
                        ]);
                        AiPromptHistory::recordChange($prompt, 'created', $user->id);
                        $imported++;
                    }
                }
            });

            // Clear cache
            $this->promptService->clearCache($organization?->id);

            return redirect()->route('admin.ai-prompts.index')
                ->with('success', "Imported {$imported} prompts. Skipped {$skipped}.");

        } catch (\Exception $e) {
            Log::error('Failed to import AI prompts', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return back()->withErrors(['file' => 'Failed to import prompts. Please check the file format.']);
        }
    }

    /**
     * Bulk update prompts (activate/deactivate)
     */
    public function bulkUpdate(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        if (! $user->hasPermission('ai_prompts.edit')) {
            abort(403);
        }

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'uuid|exists:ai_category_prompts,id',
            'action' => 'required|in:activate,deactivate',
        ]);

        try {
            $prompts = AiCategoryPrompt::whereIn('id', $request->ids)
                ->where('is_system', false) // Cannot bulk-modify system prompts
                ->get();

            $action = $request->action;
            $isActive = $action === 'activate';
            $updated = 0;

            DB::transaction(function () use ($prompts, $user, $isActive, $action, &$updated) {
                foreach ($prompts as $prompt) {
                    $prompt->update([
                        'is_active' => $isActive,
                        'updated_by' => $user->id,
                    ]);
                    AiPromptHistory::recordChange($prompt, $action.'d', $user->id);
                    $updated++;
                }
            });

            // Clear cache
            $this->promptService->clearCache($organization?->id);

            return redirect()->route('admin.ai-prompts.index')
                ->with('success', ucfirst($action)."d {$updated} prompts.");

        } catch (\Exception $e) {
            Log::error('Failed to bulk update AI prompts', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return back()->withErrors(['error' => 'Failed to update prompts. Please try again.']);
        }
    }
}
