<?php

namespace App\Models\AI;

use App\Models\Organization\Organization;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiCategoryPrompt extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'ai_category_prompts';

    protected $fillable = [
        'organization_id',
        'category_slug',
        'prompt_type',
        'name',
        'description',
        'prompt_content',
        'variable_definitions',
        'is_active',
        'is_system',
        'priority',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'variable_definitions' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Valid prompt types
     */
    public const PROMPT_TYPES = [
        'stage1_identification' => 'Stage 1: Initial Identification',
        'stage2_research' => 'Stage 2: Detailed Research',
        'stage2_ebay_enrichment' => 'Stage 2: eBay Enrichment',
    ];

    /**
     * Standard variables available for prompts
     */
    public const STANDARD_VARIABLES = [
        'brand' => 'Product brand/manufacturer name',
        'manufacturer_part_number' => 'Manufacturer part number (MPN)',
        'product_number' => 'Product number or SKU',
        'model_number' => 'Model number',
        'user_notes' => 'Additional notes from user',
        'keywords' => 'Search keywords (comma-separated)',
        'condition' => 'Product condition',
        'product_category' => 'User-selected category',
        'product_type' => 'Product type (complete_unit/replacement_part/accessory)',
        'user_type_description' => 'Human-readable product type description',
    ];

    protected static function newFactory()
    {
        return \Database\Factories\AI\AiCategoryPromptFactory::new();
    }

    /**
     * Relationships
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(AiPromptHistory::class, 'prompt_id')->orderByDesc('created_at');
    }

    /**
     * Scopes
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeForOrganization(Builder $query, ?int $organizationId): Builder
    {
        return $query->where(function ($q) use ($organizationId) {
            $q->whereNull('organization_id');
            if ($organizationId) {
                $q->orWhere('organization_id', $organizationId);
            }
        });
    }

    public function scopeByCategory(Builder $query, string $categorySlug): Builder
    {
        return $query->where('category_slug', $categorySlug);
    }

    public function scopeByType(Builder $query, string $promptType): Builder
    {
        return $query->where('prompt_type', $promptType);
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('is_system', false);
    }

    public function scopeOrganizationOnly(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderByDesc('priority');
    }

    /**
     * Render the prompt with variable substitution
     */
    public function renderPrompt(array $variables): string
    {
        $content = $this->prompt_content;

        foreach ($variables as $key => $value) {
            $content = str_replace('{'.$key.'}', (string) $value, $content);
        }

        return $content;
    }

    /**
     * Get available variables with their definitions
     */
    public function getAvailableVariables(): array
    {
        return array_merge(
            self::STANDARD_VARIABLES,
            $this->variable_definitions ?? []
        );
    }

    /**
     * Get variables used in the prompt content
     */
    public function getUsedVariables(): array
    {
        preg_match_all('/\{([a-zA-Z_]+)\}/', $this->prompt_content, $matches);

        return array_unique($matches[1] ?? []);
    }

    /**
     * Validate that all used variables are defined
     */
    public function validateVariables(): array
    {
        $used = $this->getUsedVariables();
        $available = array_keys($this->getAvailableVariables());
        $undefined = array_diff($used, $available);

        return $undefined;
    }

    /**
     * Check if this prompt can be edited
     */
    public function isEditable(): bool
    {
        return ! $this->is_system;
    }

    /**
     * Check if this prompt can be deleted
     */
    public function isDeletable(): bool
    {
        return ! $this->is_system;
    }

    /**
     * Get display name for prompt type
     */
    public function getPromptTypeLabel(): string
    {
        return self::PROMPT_TYPES[$this->prompt_type] ?? $this->prompt_type;
    }

    /**
     * Get character count for prompt content
     */
    public function getCharacterCount(): int
    {
        return strlen($this->prompt_content);
    }

    /**
     * Duplicate prompt for organization
     */
    public function duplicateFor(?int $organizationId, ?int $userId = null): self
    {
        $duplicate = $this->replicate([
            'id',
            'is_system',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);

        $duplicate->organization_id = $organizationId;
        $duplicate->is_system = false;
        $duplicate->name = $this->name.' (Copy)';
        $duplicate->created_by = $userId;
        $duplicate->updated_by = $userId;
        $duplicate->save();

        return $duplicate;
    }

    /**
     * Get all category slugs used in the system
     */
    public static function getAllCategorySlugs(): array
    {
        return self::distinct('category_slug')
            ->pluck('category_slug')
            ->toArray();
    }

    /**
     * Get all prompt types
     */
    public static function getPromptTypes(): array
    {
        return self::PROMPT_TYPES;
    }

    /**
     * Get standard variables
     */
    public static function getStandardVariables(): array
    {
        return self::STANDARD_VARIABLES;
    }
}
