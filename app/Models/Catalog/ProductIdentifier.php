<?php

namespace App\Models\Catalog;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductIdentifier extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'type',
        'value',
        'is_primary',
        'verified',
        'source',
        'confidence_score',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'verified' => 'boolean',
        'metadata' => 'array',
        'confidence_score' => 'integer',
    ];

    // Relationships
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verified', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeBySource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    public function scopeHighConfidence(Builder $query, int $threshold = 80): Builder
    {
        return $query->where('confidence_score', '>=', $threshold);
    }

    // Methods
    public function getFormattedTypeAttribute(): string
    {
        return match ($this->type) {
            'upc' => 'UPC',
            'ean' => 'EAN',
            'asin' => 'Amazon ASIN',
            'mpn' => 'Manufacturer Part #',
            'isbn' => 'ISBN',
            'serial' => 'Serial Number',
            'model' => 'Model Number',
            'ebay_item' => 'eBay Item #',
            'fnsku' => 'Amazon FNSKU',
            'vin' => 'VIN',
            'imei' => 'IMEI',
            default => ucwords(str_replace('_', ' ', $this->type))
        };
    }

    public function getIsScannableAttribute(): bool
    {
        return in_array($this->type, [
            'upc', 'ean', 'isbn', 'serial', 'vin', 'imei', 'qr_code',
        ]);
    }

    public function getConfidenceLevelAttribute(): string
    {
        if ($this->confidence_score === null) {
            return 'unknown';
        }

        return match (true) {
            $this->confidence_score >= 95 => 'very_high',
            $this->confidence_score >= 85 => 'high',
            $this->confidence_score >= 70 => 'medium',
            $this->confidence_score >= 50 => 'low',
            default => 'very_low'
        };
    }

    // Static methods for identifier detection
    public static function detectIdentifierType(string $value): string
    {
        // Remove any spaces or dashes for analysis
        $cleanValue = preg_replace('/[\s\-]/', '', $value);

        // UPC (12 digits) or EAN (13 digits)
        if (preg_match('/^\d{12,14}$/', $cleanValue)) {
            return strlen($cleanValue) === 12 ? 'upc' : 'ean';
        }

        // Amazon ASIN (B0 + 8 alphanumeric)
        if (preg_match('/^B0[A-Z0-9]{8}$/', $cleanValue)) {
            return 'asin';
        }

        // ISBN (10 or 13 digits, may have X)
        if (preg_match('/^\d{9}[\dX]$/', $cleanValue) || preg_match('/^978\d{10}$/', $cleanValue)) {
            return 'isbn';
        }

        // VIN (17 alphanumeric)
        if (preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $cleanValue)) {
            return 'vin';
        }

        // IMEI (15 digits)
        if (preg_match('/^\d{15}$/', $cleanValue)) {
            return 'imei';
        }

        // If contains dashes or underscores, likely MPN or part number
        if (preg_match('/[\-_]/', $value)) {
            return 'mpn';
        }

        // Default to unknown - let user categorize
        return 'unknown';
    }

    public static function getValidIdentifierTypes(): array
    {
        return [
            'upc' => 'UPC (Universal Product Code)',
            'ean' => 'EAN (European Article Number)',
            'asin' => 'Amazon ASIN',
            'mpn' => 'Manufacturer Part Number',
            'isbn' => 'ISBN (Books)',
            'serial' => 'Serial Number',
            'model' => 'Model Number',
            'ebay_item' => 'eBay Item Number',
            'fnsku' => 'Amazon FNSKU',
            'vin' => 'Vehicle VIN',
            'imei' => 'IMEI (Mobile Devices)',
            'mac_address' => 'MAC Address',
            'part_number' => 'Part Number',
            'oem_part' => 'OEM Part Number',
            'batch_number' => 'Batch/Lot Number',
            'asset_tag' => 'Asset Tag',
            'qr_code' => 'QR Code',
            'custom' => 'Custom Identifier',
        ];
    }
}
