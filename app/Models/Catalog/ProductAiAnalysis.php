<?php

namespace App\Models\Catalog;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAiAnalysis extends Model
{
    use HasFactory;

    protected $table = 'product_ai_analyses';

    protected $fillable = [
        'product_id',
        'queue_item_id',
        'analysis_type',
        'input_images',
        'ai_provider',
        'model_version',
        'extracted_text',
        'detected_category',
        'detected_brand',
        'detected_model',
        'ai_description',
        'ai_specifications',
        'user_description',
        'user_corrections',
        'confidence_score',
        'verification_status',
        'reviewer_notes',
        'reviewed_by',
        'reviewed_at',
        'raw_response',
    ];

    protected $casts = [
        'input_images' => 'array',
        'extracted_text' => 'array',
        'ai_specifications' => 'array',
        'user_corrections' => 'array',
        'raw_response' => 'array',
        'confidence_score' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function queueItem(): BelongsTo
    {
        return $this->belongsTo(ProductIntakeQueue::class, 'queue_item_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Helper Methods
     */
    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isRejected(): bool
    {
        return $this->verification_status === 'rejected';
    }

    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }

    public function verify(User $user, ?string $notes = null): void
    {
        $this->update([
            'verification_status' => 'verified',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'reviewer_notes' => $notes,
        ]);
    }

    public function reject(User $user, string $reason): void
    {
        $this->update([
            'verification_status' => 'rejected',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'reviewer_notes' => $reason,
        ]);
    }

    /**
     * Get confidence level as string
     */
    public function getConfidenceLevelAttribute(): string
    {
        if ($this->confidence_score === null) {
            return 'unknown';
        }

        if ($this->confidence_score >= 0.85) {
            return 'high';
        }

        if ($this->confidence_score >= 0.60) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get all extracted text as a single string
     */
    public function getAllExtractedText(): string
    {
        if (empty($this->extracted_text)) {
            return '';
        }

        if (is_array($this->extracted_text)) {
            return implode(' ', $this->extracted_text);
        }

        return (string) $this->extracted_text;
    }

    /**
     * Check if analysis has useful data
     */
    public function hasUsefulData(): bool
    {
        return ! empty($this->detected_category)
               || ! empty($this->detected_brand)
               || ! empty($this->detected_model)
               || ! empty($this->ai_description)
               || ! empty($this->extracted_text);
    }
}
