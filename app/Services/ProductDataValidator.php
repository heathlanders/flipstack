<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Product Data Validator
 *
 * Validates product data completeness and accuracy for eBay listing creation
 * Implements quality scoring and missing field detection
 */
class ProductDataValidator
{
    // Required fields for eBay listing
    private const REQUIRED_FIELDS = [
        'title' => 80,           // Max length
        'category' => null,
        'condition' => null,
        'price' => null,
        'description' => 32000,  // Max length
    ];

    // Recommended fields for quality listings
    private const RECOMMENDED_FIELDS = [
        'brand' => null,
        'model' => null,
        'mpn' => null,
        'upc' => null,
        'images' => 1,  // Minimum count
        'specifications' => 3, // Minimum count
        'shipping_weight' => null,
        'shipping_dimensions' => null,
    ];

    /**
     * Validate product data completeness
     */
    public function validate(array $productData): ValidationResult
    {
        $result = new ValidationResult;

        // Check required fields
        foreach (self::REQUIRED_FIELDS as $field => $maxLength) {
            if (! $this->hasField($productData, $field)) {
                $result->addMissing($field, 'required');
            } elseif ($maxLength && $this->exceedsLength($productData, $field, $maxLength)) {
                $result->addError($field, "Exceeds maximum length of {$maxLength} characters");
            }
        }

        // Check recommended fields
        foreach (self::RECOMMENDED_FIELDS as $field => $minCount) {
            if (! $this->hasField($productData, $field)) {
                $result->addMissing($field, 'recommended');
            } elseif ($minCount && ! $this->meetsMinimum($productData, $field, $minCount)) {
                $result->addWarning($field, "Should have at least {$minCount} item(s)");
            }
        }

        // Calculate completeness score
        $result->calculateScore(
            count(self::REQUIRED_FIELDS),
            count(self::RECOMMENDED_FIELDS)
        );

        Log::info('Product data validation complete', [
            'score' => $result->score,
            'is_complete' => $result->isComplete(),
            'missing_required' => count($result->getMissingRequired()),
            'missing_recommended' => count($result->getMissingRecommended()),
        ]);

        return $result;
    }

    /**
     * Validate specifically for eBay listing requirements
     */
    public function validateForEbay(array $productData): ValidationResult
    {
        $result = $this->validate($productData);

        // Additional eBay-specific validations

        // Title validation
        if ($this->hasField($productData, 'title')) {
            $title = $this->getField($productData, 'title');

            if (strlen($title) < 10) {
                $result->addError('title', 'Title too short (minimum 10 characters)');
            }

            if (preg_match('/[<>]/', $title)) {
                $result->addError('title', 'Title contains HTML tags (not allowed)');
            }
        }

        // Image validation
        if ($this->hasField($productData, 'images')) {
            $images = $this->getField($productData, 'images');

            if (count($images) < 1) {
                $result->addError('images', 'At least 1 image required');
            } elseif (count($images) > 24) {
                $result->addWarning('images', 'eBay allows maximum 24 images');
            }
        }

        // Price validation
        if ($this->hasField($productData, 'price')) {
            $price = $this->getField($productData, 'price');

            if ($price <= 0) {
                $result->addError('price', 'Price must be greater than zero');
            }
        }

        // Condition validation
        if ($this->hasField($productData, 'condition')) {
            $condition = strtolower($this->getField($productData, 'condition'));
            $validConditions = ['new', 'used', 'refurbished', 'for parts or not working'];

            if (! in_array($condition, $validConditions)) {
                $result->addWarning('condition', 'Should be one of: '.implode(', ', $validConditions));
            }
        }

        return $result;
    }

    /**
     * Calculate quality score (0-100)
     */
    public function calculateQualityScore(array $productData): int
    {
        $score = 0;
        $maxScore = 100;

        // Title quality (20 points)
        $score += $this->scoreTitleQuality($productData);

        // Description quality (20 points)
        $score += $this->scoreDescriptionQuality($productData);

        // Image quality (30 points) - UPDATED from 25
        $score += $this->scoreImageQuality($productData);

        // Product specifics (20 points)
        $score += $this->scoreSpecificsQuality($productData);

        // Pricing data (10 points) - UPDATED from 15
        $score += $this->scorePricingQuality($productData);

        return min($score, $maxScore);
    }

    /**
     * Score title quality (max 20 points)
     */
    public function scoreTitleQuality(array $data): int
    {
        if (! $this->hasField($data, 'title')) {
            return 0;
        }

        $title = $this->getField($data, 'title');
        $score = 0;

        // Length check (0-10 points)
        $length = strlen($title);
        if ($length >= 60) {
            $score += 10; // Optimal length
        } elseif ($length >= 40) {
            $score += 7;
        } elseif ($length >= 20) {
            $score += 5;
        } elseif ($length >= 10) {
            $score += 3;
        }

        // Keyword richness (0-10 points)
        $hasModel = $this->hasField($data, 'model') && str_contains($title, $this->getField($data, 'model'));
        $hasBrand = $this->hasField($data, 'brand') && str_contains($title, $this->getField($data, 'brand'));
        $hasCondition = preg_match('/\b(new|used|refurbished)\b/i', $title);

        if ($hasBrand) {
            $score += 4;
        }
        if ($hasModel) {
            $score += 4;
        }
        if ($hasCondition) {
            $score += 2;
        }

        return min($score, 20);
    }

    /**
     * Score description quality (max 20 points)
     */
    public function scoreDescriptionQuality(array $data): int
    {
        if (! $this->hasField($data, 'description')) {
            return 0;
        }

        $description = $this->getField($data, 'description');
        $score = 0;

        // Length check (0-10 points)
        $length = strlen($description);
        if ($length >= 500) {
            $score += 10; // Comprehensive
        } elseif ($length >= 200) {
            $score += 7;
        } elseif ($length >= 100) {
            $score += 4;
        } elseif ($length >= 50) {
            $score += 2;
        }

        // Content quality (0-10 points)
        $hasFeatures = str_contains(strtolower($description), 'feature');
        $hasCondition = preg_match('/\b(condition|like new|excellent|good)\b/i', $description);
        $hasSpecifications = preg_match('/\b(specification|spec|dimension|weight)\b/i', $description);

        if ($hasFeatures) {
            $score += 3;
        }
        if ($hasCondition) {
            $score += 4;
        }
        if ($hasSpecifications) {
            $score += 3;
        }

        return min($score, 20);
    }

    /**
     * Score image quality (max 30 points)
     *
     * Prioritizes user-uploaded photos over eBay images
     */
    public function scoreImageQuality(array $data): int
    {
        // Check for user-uploaded photos FIRST (these are what matters!)
        if ($this->hasField($data, 'images')) {
            $images = $this->getField($data, 'images');
            $count = is_array($images) ? count($images) : 0;

            if ($count > 0) {
                // User has uploaded photos - this is what we want!
                if ($count >= 8) {
                    return 30; // Excellent (8-12 photos)
                } elseif ($count >= 6) {
                    return 25; // Very good (6-7 photos)
                } elseif ($count >= 4) {
                    return 20; // Good (4-5 photos)
                } elseif ($count >= 2) {
                    return 15; // Adequate (2-3 photos)
                } elseif ($count >= 1) {
                    return 10; // Minimum (1 photo)
                }
            }
        }

        // Fallback: Check eBay images (for reference/research only)
        // This gives partial credit if no user photos yet
        if ($this->hasField($data, 'ebay_images')) {
            $ebayImages = $this->getField($data, 'ebay_images');
            $count = is_array($ebayImages) ? count($ebayImages) : 0;

            if ($count >= 8) {
                return 25; // Good reference images available
            } elseif ($count >= 4) {
                return 20;
            } elseif ($count >= 2) {
                return 15;
            } elseif ($count >= 1) {
                return 10;
            }
        }

        return 0; // No images at all
    }

    /**
     * Score product specifics quality (max 20 points)
     */
    public function scoreSpecificsQuality(array $data): int
    {
        $score = 0;

        // Check for key identifiers (0-10 points)
        if ($this->hasField($data, 'brand')) {
            $score += 3;
        }
        if ($this->hasField($data, 'model')) {
            $score += 3;
        }
        if ($this->hasField($data, 'mpn')) {
            $score += 2;
        }
        if ($this->hasField($data, 'upc') || $this->hasField($data, 'ean')) {
            $score += 2;
        }

        // Check for specifications (0-10 points)
        if ($this->hasField($data, 'specifications')) {
            $specs = $this->getField($data, 'specifications');
            $specCount = is_array($specs) ? count($specs) : 0;

            if ($specCount >= 5) {
                $score += 10;
            } elseif ($specCount >= 3) {
                $score += 7;
            } elseif ($specCount >= 1) {
                $score += 4;
            }
        }

        return min($score, 20);
    }

    /**
     * Score pricing quality (max 10 points)
     */
    public function scorePricingQuality(array $data): int
    {
        $score = 0;

        // Has price (0-3 points)
        if ($this->hasField($data, 'price')) {
            $price = $this->getField($data, 'price');
            if ($price > 0) {
                $score += 3;
            }
        }

        // Has market data (0-7 points)
        if ($this->hasField($data, 'market_pricing')) {
            $pricing = $this->getField($data, 'market_pricing');

            if (! empty($pricing['ebay_sold_listings'])) {
                $score += 4; // Has sold comparables
            }
            if (! empty($pricing['ebay_active_listings'])) {
                $score += 2; // Has active comparables
            }
            if (! empty($pricing['recommended_list_price']) && $pricing['recommended_list_price'] > 0) {
                $score += 1; // Has pricing recommendation
            }
        }

        return min($score, 10);
    }

    /**
     * Helper: Check if field exists and has value
     */
    private function hasField(array $data, string $field): bool
    {
        // Support nested fields like 'product_identity.brand'
        $keys = explode('.', $field);
        $current = $data;

        foreach ($keys as $key) {
            if (! isset($current[$key])) {
                return false;
            }
            $current = $current[$key];
        }

        // Check if value is not empty
        if (is_string($current)) {
            return ! empty(trim($current));
        }

        if (is_array($current)) {
            return ! empty($current);
        }

        return ! empty($current);
    }

    /**
     * Helper: Get field value
     */
    private function getField(array $data, string $field)
    {
        $keys = explode('.', $field);
        $current = $data;

        foreach ($keys as $key) {
            if (! isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Helper: Check if field exceeds maximum length
     */
    private function exceedsLength(array $data, string $field, int $maxLength): bool
    {
        if (! $this->hasField($data, $field)) {
            return false;
        }

        $value = $this->getField($data, $field);

        if (is_string($value)) {
            return strlen($value) > $maxLength;
        }

        return false;
    }

    /**
     * Helper: Check if field meets minimum count
     */
    private function meetsMinimum(array $data, string $field, int $minCount): bool
    {
        if (! $this->hasField($data, $field)) {
            return false;
        }

        $value = $this->getField($data, $field);

        if (is_array($value)) {
            return count($value) >= $minCount;
        }

        return false;
    }
}

/**
 * Validation Result Object
 */
class ValidationResult
{
    public array $missing = [];

    public array $errors = [];

    public array $warnings = [];

    public int $score = 0;

    public function addMissing(string $field, string $priority): void
    {
        $this->missing[] = [
            'field' => $field,
            'priority' => $priority,
        ];
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[] = [
            'field' => $field,
            'message' => $message,
        ];
    }

    public function addWarning(string $field, string $message): void
    {
        $this->warnings[] = [
            'field' => $field,
            'message' => $message,
        ];
    }

    public function calculateScore(int $requiredCount, int $recommendedCount): void
    {
        $requiredWeight = 70; // 70% of score
        $recommendedWeight = 30; // 30% of score

        $missingRequired = count($this->getMissingRequired());
        $missingRecommended = count($this->getMissingRecommended());

        $requiredScore = max(0, ($requiredCount - $missingRequired) / $requiredCount * $requiredWeight);
        $recommendedScore = max(0, ($recommendedCount - $missingRecommended) / $recommendedCount * $recommendedWeight);

        $this->score = (int) round($requiredScore + $recommendedScore);
    }

    public function isComplete(): bool
    {
        return empty($this->getMissingRequired()) && empty($this->errors);
    }

    public function getMissingRequired(): array
    {
        return array_filter($this->missing, fn ($item) => $item['priority'] === 'required');
    }

    public function getMissingRecommended(): array
    {
        return array_filter($this->missing, fn ($item) => $item['priority'] === 'recommended');
    }

    public function toArray(): array
    {
        return [
            'is_complete' => $this->isComplete(),
            'score' => $this->score,
            'missing_required' => array_column($this->getMissingRequired(), 'field'),
            'missing_recommended' => array_column($this->getMissingRecommended(), 'field'),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
