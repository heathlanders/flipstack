<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * ShippingClassService
 *
 * Analyzes physical product measurements to automatically classify shipping requirements
 * and estimate shipping costs for eBay listing optimization
 */
class ShippingClassService
{
    // Shipping class thresholds (based on common carrier standards)
    protected array $classificationRules = [
        'small_letter' => [
            'max_weight' => 0.25, // 0.25 lbs
            'max_length' => 9.5,
            'max_width' => 4.125,
            'max_height' => 0.75,
            'max_combined' => 14.375, // L+W+H
        ],
        'large_letter' => [
            'max_weight' => 0.75, // 0.75 lbs
            'max_length' => 15,
            'max_width' => 12,
            'max_height' => 0.75,
            'max_combined' => 27.75,
        ],
        'small_package' => [
            'max_weight' => 15.99, // Under 16 lbs
            'max_length' => 12,
            'max_width' => 12,
            'max_height' => 12,
            'max_combined' => 36,
        ],
        'medium_package' => [
            'max_weight' => 70, // Under 70 lbs
            'max_length' => 24,
            'max_width' => 18,
            'max_height' => 18,
            'max_combined' => 84,
        ],
        'large_package' => [
            'max_weight' => 150, // Under 150 lbs
            'max_length' => 48,
            'max_width' => 36,
            'max_height' => 36,
            'max_combined' => 165,
        ],
        'oversized' => [
            'min_weight' => 150.01, // Over 150 lbs or large dimensions
            'min_length' => 48.01,
            'min_width' => 36.01,
            'min_height' => 36.01,
            'min_combined' => 165.01,
        ],
    ];

    // Estimated shipping cost ranges (USD) - will be replaced with real API calls later
    protected array $estimatedCosts = [
        'small_letter' => ['min' => 1.50, 'max' => 4.50, 'typical' => 3.00],
        'large_letter' => ['min' => 3.50, 'max' => 8.50, 'typical' => 6.00],
        'small_package' => ['min' => 8.50, 'max' => 15.00, 'typical' => 12.00],
        'medium_package' => ['min' => 15.00, 'max' => 35.00, 'typical' => 25.00],
        'large_package' => ['min' => 35.00, 'max' => 85.00, 'typical' => 55.00],
        'oversized' => ['min' => 85.00, 'max' => 300.00, 'typical' => 150.00],
    ];

    /**
     * Classify shipping requirements based on physical measurements
     */
    public function classify(array $dimensions): string
    {
        $weight = $dimensions['weight'] ?? 0;
        $length = $dimensions['length'] ?? 0;
        $width = $dimensions['width'] ?? 0;
        $height = $dimensions['height'] ?? 0;
        $combined = $length + $width + $height;

        Log::info('Classifying shipping requirements', [
            'weight' => $weight,
            'dimensions' => "{$length}x{$width}x{$height}",
            'combined' => $combined,
        ]);

        // Check oversized first (any dimension over threshold)
        if ($this->isOversized($weight, $length, $width, $height, $combined)) {
            return 'oversized';
        }

        // Check each class from smallest to largest
        foreach (['small_letter', 'large_letter', 'small_package', 'medium_package', 'large_package'] as $class) {
            if ($this->fitsClass($class, $weight, $length, $width, $height, $combined)) {
                return $class;
            }
        }

        // Default to large_package if no other class fits
        return 'large_package';
    }

    /**
     * Check if dimensions are oversized
     */
    protected function isOversized(float $weight, float $length, float $width, float $height, float $combined): bool
    {
        $rules = $this->classificationRules['oversized'];

        return $weight > ($rules['min_weight'] ?? 150) ||
               $length > ($rules['min_length'] ?? 48) ||
               $width > ($rules['min_width'] ?? 36) ||
               $height > ($rules['min_height'] ?? 36) ||
               $combined > ($rules['min_combined'] ?? 165);
    }

    /**
     * Check if dimensions fit within a shipping class
     */
    protected function fitsClass(string $class, float $weight, float $length, float $width, float $height, float $combined): bool
    {
        $rules = $this->classificationRules[$class] ?? [];

        if (empty($rules)) {
            return false;
        }

        return $weight <= ($rules['max_weight'] ?? PHP_FLOAT_MAX) &&
               $length <= ($rules['max_length'] ?? PHP_FLOAT_MAX) &&
               $width <= ($rules['max_width'] ?? PHP_FLOAT_MAX) &&
               $height <= ($rules['max_height'] ?? PHP_FLOAT_MAX) &&
               $combined <= ($rules['max_combined'] ?? PHP_FLOAT_MAX);
    }

    /**
     * Estimate shipping cost for a given class
     */
    public function estimateCost(array $dimensions, string $shippingClass): float
    {
        $costs = $this->estimatedCosts[$shippingClass] ?? $this->estimatedCosts['medium_package'];

        // For now, return typical cost. Later integrate with real shipping APIs
        $estimatedCost = $costs['typical'];

        // Apply weight-based adjustment for packages
        if (in_array($shippingClass, ['small_package', 'medium_package', 'large_package', 'oversized'])) {
            $weight = $dimensions['weight'] ?? 0;

            // Add cost for heavier items
            if ($weight > 10) {
                $extraWeight = $weight - 10;
                $estimatedCost += $extraWeight * 1.50; // $1.50 per extra lb (rough estimate)
            }
        }

        // Apply dimension-based adjustment for oversized
        if ($shippingClass === 'oversized') {
            $combined = ($dimensions['length'] ?? 0) + ($dimensions['width'] ?? 0) + ($dimensions['height'] ?? 0);

            if ($combined > 200) {
                $estimatedCost += ($combined - 200) * 2.00; // Extra cost for very large items
            }
        }

        return round($estimatedCost, 2);
    }

    /**
     * Get detailed shipping analysis
     */
    public function analyzeShipping(array $dimensions): array
    {
        $shippingClass = $this->classify($dimensions);
        $estimatedCost = $this->estimateCost($dimensions, $shippingClass);

        $analysis = [
            'shipping_class' => $shippingClass,
            'estimated_cost' => $estimatedCost,
            'cost_range' => $this->estimatedCosts[$shippingClass] ?? [],
            'dimensions' => $dimensions,
            'combined_size' => ($dimensions['length'] ?? 0) + ($dimensions['width'] ?? 0) + ($dimensions['height'] ?? 0),
            'warnings' => [],
            'recommendations' => [],
        ];

        // Add warnings and recommendations
        $analysis['warnings'] = $this->getShippingWarnings($shippingClass, $dimensions);
        $analysis['recommendations'] = $this->getShippingRecommendations($shippingClass, $dimensions);

        // Calculate shipping efficiency score (lower shipping cost relative to item size = better)
        $analysis['efficiency_score'] = $this->calculateShippingEfficiency($dimensions, $estimatedCost);

        return $analysis;
    }

    /**
     * Get shipping warnings for potential issues
     */
    protected function getShippingWarnings(string $shippingClass, array $dimensions): array
    {
        $warnings = [];
        $weight = $dimensions['weight'] ?? 0;

        if ($shippingClass === 'oversized') {
            $warnings[] = [
                'type' => 'oversized',
                'message' => 'Item qualifies as oversized shipping - significantly higher costs',
                'impact' => 'high',
            ];
        }

        if ($weight > 50 && $shippingClass !== 'oversized') {
            $warnings[] = [
                'type' => 'heavy_item',
                'message' => 'Heavy item may require special handling',
                'impact' => 'medium',
            ];
        }

        if ($weight < 0.1) {
            $warnings[] = [
                'type' => 'very_light',
                'message' => 'Very light weight may indicate missing weight measurement',
                'impact' => 'low',
            ];
        }

        // Check for unusual dimensions
        $length = $dimensions['length'] ?? 0;
        $width = $dimensions['width'] ?? 0;
        $height = $dimensions['height'] ?? 0;

        if ($length > 36 || $width > 24 || $height > 24) {
            $warnings[] = [
                'type' => 'large_dimensions',
                'message' => 'Large dimensions may limit shipping options',
                'impact' => 'medium',
            ];
        }

        return $warnings;
    }

    /**
     * Get shipping optimization recommendations
     */
    protected function getShippingRecommendations(string $shippingClass, array $dimensions): array
    {
        $recommendations = [];

        if ($shippingClass === 'oversized') {
            $recommendations[] = [
                'type' => 'pricing_strategy',
                'message' => 'Consider freight shipping or local pickup to reduce shipping costs',
                'action' => 'Add "Local Pickup Available" option to listing',
            ];

            $recommendations[] = [
                'type' => 'packaging',
                'message' => 'Verify if item can be partially disassembled for smaller shipping box',
                'action' => 'Check if components can be separated safely',
            ];
        }

        if (in_array($shippingClass, ['medium_package', 'large_package'])) {
            $recommendations[] = [
                'type' => 'shipping_service',
                'message' => 'Consider using FedEx or UPS for better rates on larger packages',
                'action' => 'Compare shipping rates across carriers',
            ];
        }

        if ($shippingClass === 'small_letter' || $shippingClass === 'large_letter') {
            $recommendations[] = [
                'type' => 'fast_shipping',
                'message' => 'Item qualifies for economical first-class shipping',
                'action' => 'Offer fast, affordable shipping as selling point',
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate shipping efficiency score (0-100, higher is better)
     */
    protected function calculateShippingEfficiency(array $dimensions, float $estimatedCost): float
    {
        $weight = $dimensions['weight'] ?? 0.1;
        $volume = ($dimensions['length'] ?? 1) * ($dimensions['width'] ?? 1) * ($dimensions['height'] ?? 1);

        // Cost per pound
        $costPerPound = $estimatedCost / max(0.1, $weight);

        // Cost per cubic inch
        $costPerCubicInch = $estimatedCost / max(1, $volume);

        // Lower costs = higher efficiency
        // Score inversely related to cost ratios
        $weightEfficiency = max(0, 100 - ($costPerPound * 5)); // Scale based on typical ranges
        $sizeEfficiency = max(0, 100 - ($costPerCubicInch * 1000)); // Scale based on typical ranges

        // Average the two efficiency measures
        $overallEfficiency = ($weightEfficiency + $sizeEfficiency) / 2;

        return round(min(100, max(0, $overallEfficiency)), 1);
    }

    /**
     * Get shipping recommendations for eBay listing optimization
     */
    public function getEbayShippingRecommendations(array $dimensions): array
    {
        $analysis = $this->analyzeShipping($dimensions);
        $shippingClass = $analysis['shipping_class'];
        $estimatedCost = $analysis['estimated_cost'];

        $recommendations = [
            'calculated_shipping' => $this->shouldUseCalculatedShipping($shippingClass),
            'free_shipping' => $this->canOfferFreeShipping($estimatedCost),
            'handling_time' => $this->getRecommendedHandlingTime($shippingClass),
            'packaging_type' => $this->getRecommendedPackaging($dimensions),
            'shipping_services' => $this->getRecommendedServices($shippingClass),
            'international_shipping' => $this->canShipInternational($shippingClass, $dimensions),
        ];

        return $recommendations;
    }

    /**
     * Determine if calculated shipping should be used
     */
    protected function shouldUseCalculatedShipping(string $shippingClass): bool
    {
        // Use calculated shipping for larger/heavier items where shipping varies significantly by location
        return in_array($shippingClass, ['large_package', 'oversized']);
    }

    /**
     * Determine if free shipping can be offered (absorbed in item price)
     */
    protected function canOfferFreeShipping(float $estimatedCost): array
    {
        return [
            'recommended' => $estimatedCost < 15.00, // Only for lower shipping costs
            'cost_to_absorb' => $estimatedCost,
            'price_increase_needed' => $estimatedCost * 1.1, // Add 10% buffer
        ];
    }

    /**
     * Get recommended handling time based on complexity
     */
    protected function getRecommendedHandlingTime(string $shippingClass): int
    {
        return match ($shippingClass) {
            'small_letter', 'large_letter' => 1, // 1 business day
            'small_package', 'medium_package' => 2, // 2 business days
            'large_package', 'oversized' => 3, // 3 business days for complex packing
            default => 2,
        };
    }

    /**
     * Get recommended packaging type
     */
    protected function getRecommendedPackaging(array $dimensions): string
    {
        $weight = $dimensions['weight'] ?? 0;
        $isFragile = $this->detectFragileItem($dimensions);

        if ($weight > 50) {
            return 'heavy_duty_box';
        }

        if ($isFragile) {
            return 'bubble_wrap_box';
        }

        if ($weight < 1) {
            return 'padded_envelope';
        }

        return 'standard_box';
    }

    /**
     * Get recommended shipping services for eBay
     */
    protected function getRecommendedServices(string $shippingClass): array
    {
        $services = [
            'small_letter' => [
                'USPS First Class Mail' => 'primary',
                'USPS Priority Mail' => 'upgrade',
            ],
            'large_letter' => [
                'USPS First Class Mail' => 'primary',
                'USPS Priority Mail' => 'upgrade',
            ],
            'small_package' => [
                'USPS Priority Mail' => 'primary',
                'UPS Ground' => 'alternative',
                'FedEx Ground' => 'alternative',
            ],
            'medium_package' => [
                'USPS Priority Mail' => 'primary',
                'UPS Ground' => 'upgrade',
                'FedEx Ground' => 'upgrade',
            ],
            'large_package' => [
                'UPS Ground' => 'primary',
                'FedEx Ground' => 'primary',
                'USPS Priority Mail' => 'if_under_70lbs',
            ],
            'oversized' => [
                'FedEx Freight' => 'primary',
                'UPS Freight' => 'alternative',
                'Local Pickup' => 'recommended',
            ],
        ];

        return $services[$shippingClass] ?? $services['medium_package'];
    }

    /**
     * Determine if international shipping is feasible
     */
    protected function canShipInternational(string $shippingClass, array $dimensions): array
    {
        $weight = $dimensions['weight'] ?? 0;

        $canShip = $shippingClass !== 'oversized' && $weight < 66; // USPS international limit

        return [
            'feasible' => $canShip,
            'recommended_services' => $canShip ? [
                'USPS Priority Mail International',
                'USPS Priority Mail Express International',
            ] : [],
            'restrictions' => $canShip ? [] : [
                'Too large or heavy for standard international shipping',
            ],
        ];
    }

    /**
     * Detect if item might be fragile based on dimensions
     */
    protected function detectFragileItem(array $dimensions): bool
    {
        $weight = $dimensions['weight'] ?? 0;
        $volume = ($dimensions['length'] ?? 1) * ($dimensions['width'] ?? 1) * ($dimensions['height'] ?? 1);

        // Low weight relative to volume might indicate fragile/hollow item
        $density = $volume > 0 ? $weight / $volume : 0;

        return $density < 0.1; // Very low density suggests fragile item
    }

    /**
     * Get shipping class statistics for dashboard
     */
    public function getShippingStatistics(array $itemDimensions): array
    {
        $classDistribution = [];
        $costDistribution = [];

        foreach ($itemDimensions as $dimensions) {
            $class = $this->classify($dimensions);
            $cost = $this->estimateCost($dimensions, $class);

            $classDistribution[$class] = ($classDistribution[$class] ?? 0) + 1;
            $costDistribution[] = $cost;
        }

        return [
            'class_distribution' => $classDistribution,
            'average_shipping_cost' => ! empty($costDistribution) ? round(array_sum($costDistribution) / count($costDistribution), 2) : 0,
            'median_shipping_cost' => ! empty($costDistribution) ? $this->calculateMedian($costDistribution) : 0,
            'total_items_analyzed' => count($itemDimensions),
        ];
    }

    /**
     * Calculate median from array of numbers
     */
    protected function calculateMedian(array $numbers): float
    {
        sort($numbers);
        $count = count($numbers);
        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            return ($numbers[$middle - 1] + $numbers[$middle]) / 2;
        } else {
            return $numbers[$middle];
        }
    }
}
