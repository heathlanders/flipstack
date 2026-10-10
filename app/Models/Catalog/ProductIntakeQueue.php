<?php

namespace App\Models\Catalog;

use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\WarehouseBin;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductIntakeQueue extends Model
{
    use HasFactory;

    protected $table = 'product_intake_queue';

    protected $fillable = [
        'organization_id',
        'team_id',
        'captured_by',
        'captured_at',
        'capture_method',
        'barcode',
        'manufacturer_part_number',
        'product_number',
        'model_number',
        'series',
        'supplier_id',
        'photos',
        'user_notes',
        'quick_quantity',
        'quick_location',
        'quick_location_id',
        'quick_bin_id',
        'ai_analysis_status',
        'ai_analysis_id',
        'ai_started_at',
        'ai_completed_at',
        'ai_confidence_score',
        'enriched_data',
        'processing_status',
        'processed_by',
        'processed_at',
        'product_id',
        'priority',
        'batch_id',
        'brand',
        'keywords',
        'quality_score',
        'quality_breakdown',
        'validation_errors',
        'missing_fields',
        'data_sources',
        'primary_source',
        'last_processed_at',
        'processing_attempts',
        'captured_via',
        'condition',
        'condition_description',
        'ebay_listing_id',
        'weight_lbs',
        'length_inches',
        'width_inches',
        'height_inches',
        'completeness_data',
        'missing_items_notes',
        'lookup_attempted',
        'existing_product_data',
        'completeness_score',
        'confidence_breakdown',
        'suggested_shipping_class',
        'estimated_shipping_cost',
        'supplier_priority_boost',
        'product_category',
        'product_type',
    ];

    protected $casts = [
        'photos' => 'array',
        'keywords' => 'array',
        'enriched_data' => 'array',
        'quality_breakdown' => 'array',
        'validation_errors' => 'array',
        'missing_fields' => 'array',
        'data_sources' => 'array',
        'captured_at' => 'datetime',
        'ai_started_at' => 'datetime',
        'ai_completed_at' => 'datetime',
        'processed_at' => 'datetime',
        'last_processed_at' => 'datetime',
        'completeness_data' => 'array',
        'existing_product_data' => 'array',
        'confidence_breakdown' => 'array',
        'lookup_attempted' => 'boolean',
        'supplier_priority_boost' => 'boolean',
        'weight_lbs' => 'decimal:2',
        'length_inches' => 'decimal:2',
        'width_inches' => 'decimal:2',
        'height_inches' => 'decimal:2',
        'estimated_shipping_cost' => 'decimal:2',
        'completeness_score' => 'decimal:1',
    ];

    /**
     * Available product categories for classification
     */
    public const PRODUCT_CATEGORIES = [
        'pos_equipment' => 'POS Equipment',
        'network_equipment' => 'Network Equipment',
        'computer_hardware' => 'Computer Hardware',
        'server_equipment' => 'Server Equipment',
        'security_equipment' => 'Security Equipment',
        'peripherals' => 'Peripherals & Accessories',
        'telecom_equipment' => 'Telecom Equipment',
        'industrial_equipment' => 'Industrial Equipment',
        'consumer_electronics' => 'Consumer Electronics',
        'medical_equipment' => 'Medical Equipment',
        'automotive_parts' => 'Automotive Parts',
        'test_measurement' => 'Test & Measurement Equipment',
        'electrical_supplies' => 'Electrical Equipment & Supplies',
        'hydraulics_pneumatics' => 'Hydraulics & Pneumatics',
        'cnc_metalworking' => 'CNC & Metalworking',
        'hvac_refrigeration' => 'HVAC & Refrigeration',
        'restaurant_food_service' => 'Restaurant & Food Service',
        'material_handling' => 'Material Handling',
        'heavy_equipment' => 'Heavy Equipment',
        'printing_graphic_arts' => 'Printing & Graphic Arts',
        'agriculture_forestry' => 'Agriculture & Forestry',
        'video_games_consoles' => 'Video Games & Consoles',
        'sporting_goods_fitness' => 'Sporting Goods & Fitness',
        'toys_collectibles' => 'Toys & Collectibles',
        'crafts_hobby' => 'Crafts & Hobby',
        'books_magazines' => 'Books & Magazines',
        'art_antiques' => 'Art & Antiques',
        'cell_phones_accessories' => 'Cell Phones & Accessories',
        'home_garden' => 'Home & Garden',
        'health_beauty' => 'Health & Beauty',
        'pet_supplies' => 'Pet Supplies',
        'other' => 'Other/Unknown',
    ];

    /**
     * Available product types for classification
     */
    public const PRODUCT_TYPES = [
        'complete_unit' => 'Complete Unit/System',
        'replacement_part' => 'Replacement Part',
        'component' => 'Internal Component',
        'accessory' => 'Accessory/Add-on',
        'consumable' => 'Consumable Item',
        'upgrade_module' => 'Upgrade/Expansion Module',
        'cable_connector' => 'Cable/Connector',
        'software_media' => 'Software/Media',
        'documentation' => 'Documentation/Manual',
        'packaging_only' => 'Packaging Only',
    ];

    /**
     * Relationships
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function aiAnalysis(): BelongsTo
    {
        return $this->belongsTo(ProductAiAnalysis::class, 'ai_analysis_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function quickLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'quick_location_id');
    }

    public function quickBin(): BelongsTo
    {
        return $this->belongsTo(WarehouseBin::class, 'quick_bin_id');
    }

    /**
     * Scopes
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('processing_status', 'pending');
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query->where('processing_status', 'ready');
    }

    public function scopeValidated(Builder $query): Builder
    {
        return $query->where('processing_status', 'validated');
    }

    public function scopeEnriched(Builder $query): Builder
    {
        return $query->whereIn('processing_status', ['ready', 'validated']);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('processing_status', 'in_progress');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('processing_status', 'completed');
    }

    public function scopeListedOnEbay(Builder $query): Builder
    {
        return $query->where('processing_status', 'listed_on_ebay');
    }

    public function scopeListingFailed(Builder $query): Builder
    {
        return $query->whereIn('processing_status', ['failed', 'listing_failed']);
    }

    public function scopeNeedsReview(Builder $query): Builder
    {
        return $query->where('processing_status', 'needs_review');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('processing_status', 'archived');
    }

    public function scopeHighConfidence(Builder $query): Builder
    {
        return $query->where('ai_confidence_score', '>=', 0.85);
    }

    public function scopeMediumConfidence(Builder $query): Builder
    {
        return $query->whereBetween('ai_confidence_score', [0.60, 0.84]);
    }

    public function scopeLowConfidence(Builder $query): Builder
    {
        return $query->where('ai_confidence_score', '<', 0.60)
            ->orWhereNull('ai_confidence_score');
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeOrderByPriority(Builder $query): Builder
    {
        return $query->orderBy('product_intake_queue.priority', 'desc')
            ->orderBy('product_intake_queue.created_at', 'desc');
    }

    /**
     * Helper Methods
     */
    public const CONDITIONS = [
        'NEW' => 'Brand New',
        'NEW_OTHER' => 'New (Other)',
        'NEW_WITH_DEFECTS' => 'New with Defects',
        'MANUFACTURER_REFURBISHED' => 'Manufacturer Refurbished',
        'CERTIFIED_REFURBISHED' => 'Certified Refurbished',
        'USED_EXCELLENT' => 'Used - Excellent',
        'USED_VERY_GOOD' => 'Used - Very Good',
        'USED_GOOD' => 'Used - Good',
        'USED_ACCEPTABLE' => 'Used - Acceptable',
        'FOR_PARTS_OR_NOT_WORKING' => 'For Parts or Not Working',
    ];

    public function getConditionLabelAttribute(): string
    {
        return self::CONDITIONS[$this->condition] ?? $this->condition;
    }

    public function getConfidenceLevelAttribute(): string
    {
        if ($this->ai_confidence_score === null) {
            return 'unknown';
        }

        if ($this->ai_confidence_score >= 0.85) {
            return 'high';
        }

        if ($this->ai_confidence_score >= 0.60) {
            return 'medium';
        }

        return 'low';
    }

    public function getConfidenceColorAttribute(): string
    {
        return match ($this->confidence_level) {
            'high' => 'green',
            'medium' => 'yellow',
            'low' => 'red',
            default => 'gray',
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->processing_status) {
            'ready' => 'Ready for Listing',
            'validated' => 'Validated',
            'needs_review' => 'Needs Review',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'listed_on_ebay' => 'Listed on eBay',
            'listing_failed' => 'Listing Failed',
            'failed' => 'Failed',
            'archived' => 'Archived',
            default => 'Unknown',
        };
    }

    public function hasPhotos(): bool
    {
        return ! empty($this->photos) && count($this->photos) > 0;
    }

    public function hasBarcode(): bool
    {
        return ! empty($this->barcode);
    }

    public function hasAiAnalysis(): bool
    {
        return $this->ai_analysis_status === 'complete' && $this->ai_analysis_id !== null;
    }

    public function isReadyForProcessing(): bool
    {
        return $this->processing_status === 'pending'
               && $this->ai_analysis_status === 'complete';
    }

    public function isReadyForListing(): bool
    {
        return in_array($this->processing_status, ['ready', 'validated']);
    }

    public function markAsInProgress(User $user): void
    {
        $this->update([
            'processing_status' => 'in_progress',
            'processed_by' => $user->id,
        ]);
    }

    public function markAsCompleted(Product $product, User $user): void
    {
        $this->update([
            'processing_status' => 'completed',
            'processed_by' => $user->id,
            'processed_at' => now(),
            'product_id' => $product->id,
        ]);
    }

    public function archive(): void
    {
        $this->update([
            'processing_status' => 'archived',
        ]);
    }

    public function getPrimaryPhotoAttribute(): ?string
    {
        if (! $this->hasPhotos()) {
            return null;
        }

        $photo = $this->photos[0] ?? null;

        if ($photo && ! str_starts_with($photo, 'http')) {
            return asset('storage/'.$photo);
        }

        return $photo;
    }

    public function getPhotoUrlsAttribute(): array
    {
        if (! $this->hasPhotos()) {
            return [];
        }

        return array_map(function ($photo) {
            if (! str_starts_with($photo, 'http')) {
                return asset('storage/'.$photo);
            }

            return $photo;
        }, $this->photos);
    }

    /**
     * Get human-readable product category label
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::PRODUCT_CATEGORIES[$this->product_category] ?? 'Unknown';
    }

    /**
     * Get human-readable product type label
     */
    public function getTypeLabelAttribute(): string
    {
        return self::PRODUCT_TYPES[$this->product_type] ?? 'Unknown';
    }

    /**
     * Get classification context for AI research
     */
    public function getClassificationContextAttribute(): array
    {
        return [
            'category' => $this->product_category,
            'category_label' => $this->category_label,
            'type' => $this->product_type,
            'type_label' => $this->type_label,
            'classification_available' => ! empty($this->product_category) && ! empty($this->product_type),
            'ai_research_hint' => $this->getAiResearchHint(),
        ];
    }

    /**
     * Get AI research hint based on classification
     */
    public function getAiResearchHint(): string
    {
        if (empty($this->product_category) || empty($this->product_type)) {
            return 'Use general product research approach';
        }

        $hints = [
            'pos_equipment' => [
                'complete_unit' => 'Focus on POS terminal specifications and retail industry databases',
                'replacement_part' => 'Search for POS component compatibility and service manuals',
                'accessory' => 'Look for POS peripheral compatibility and mounting specifications',
            ],
            'network_equipment' => [
                'complete_unit' => 'Prioritize enterprise networking documentation and technical specifications',
                'replacement_part' => 'Focus on network component compatibility and installation guides',
                'component' => 'Search for internal network module specifications and compatibility matrices',
            ],
            'computer_hardware' => [
                'complete_unit' => 'Use consumer and business computer databases for full system specs',
                'replacement_part' => 'Focus on hardware compatibility and installation requirements',
                'component' => 'Search for internal component specifications and compatibility',
            ],
            'industrial_equipment' => [
                'complete_unit' => 'Prioritize industrial automation and manufacturing databases',
                'replacement_part' => 'Focus on industrial component compatibility and service documentation',
                'component' => 'Search for automation component specifications and system integration',
            ],
        ];

        return $hints[$this->product_category][$this->product_type] ??
               "Search for {$this->type_label} in {$this->category_label} databases";
    }

    /**
     * Scope to filter by product category
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('product_category', $category);
    }

    /**
     * Scope to filter by product type
     */
    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('product_type', $type);
    }

    /**
     * Scope to filter by classification combination
     */
    public function scopeClassification(Builder $query, string $category, string $type): Builder
    {
        return $query->where('product_category', $category)
            ->where('product_type', $type);
    }

    /**
     * Get items that have classification data
     */
    public function scopeClassified(Builder $query): Builder
    {
        return $query->whereNotNull('product_category')
            ->whereNotNull('product_type');
    }

    /**
     * Get items that need classification
     */
    public function scopeUnclassified(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('product_category')
                ->orWhereNull('product_type');
        });
    }
}
