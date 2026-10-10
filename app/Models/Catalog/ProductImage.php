<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'url',
        'thumbnail_url',
        'alt_text',
        'sort_order',
        'is_primary',
        'metadata',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'metadata' => 'array',
    ];

    // Relationships
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Scopes
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Whether a pristine original is tracked so the image can be reverted.
     */
    public function hasOriginal(): bool
    {
        return ! empty($this->metadata['original_url'] ?? null);
    }

    /**
     * The storage-relative path that background removal should run against.
     * Prefers the tracked pristine original so re-running never re-removes from
     * an already-processed (white-background) image. For legacy rows that have
     * no tracked original, derive it from the processed basename: intake saved
     * processed files under the SAME basename in queue-photos/, so a missing
     * queue-photos-processed/X has its original at queue-photos/X.
     */
    public function sourcePathForProcessing(): string
    {
        if (! empty($this->metadata['original_url'])) {
            return $this->metadata['original_url'];
        }

        if (str_starts_with($this->url, 'queue-photos-processed/')) {
            return 'queue-photos/'.basename($this->url);
        }

        return $this->url;
    }

    /**
     * Point this image at a freshly processed asset and record provenance.
     * Captures the current url as the pristine original on first removal.
     */
    public function recordProcessing(string $processedPath, array $info = []): void
    {
        $meta = $this->metadata ?? [];

        if (empty($meta['original_url'])) {
            // Capture the ACTUAL source used (handles legacy rows whose current
            // url is a now-missing processed file), not the pre-reprocess url.
            $meta['original_url'] = $this->sourcePathForProcessing();
        }

        $meta['bg_removed'] = true;
        $meta['processed_url'] = $processedPath;
        $meta['processing'] = $info;
        $meta['history'][] = array_merge(
            ['path' => $processedPath, 'at' => now()->toISOString()],
            $info,
        );

        $this->metadata = $meta;
        $this->url = $processedPath;
        $this->save();
    }

    /**
     * Swap the displayed asset back to the pristine original. Returns false when
     * no original is tracked (e.g. images created before original tracking).
     */
    public function revertToOriginal(): bool
    {
        $meta = $this->metadata ?? [];

        if (empty($meta['original_url'])) {
            return false;
        }

        $this->url = $meta['original_url'];
        $meta['bg_removed'] = false;
        $meta['history'][] = [
            'action' => 'revert',
            'to' => $meta['original_url'],
            'at' => now()->toISOString(),
        ];

        $this->metadata = $meta;
        $this->save();

        return true;
    }
}
