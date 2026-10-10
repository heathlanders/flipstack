<?php

namespace App\Services;

use App\Models\Catalog\ProductIntakeQueue;

class ProductEnrichmentService
{
    private BarcodeValidationService $barcodeValidator;

    private AIAnalysisService $aiAnalysis;

    public function __construct(
        BarcodeValidationService $barcodeValidator,
        AIAnalysisService $aiAnalysis
    ) {
        $this->barcodeValidator = $barcodeValidator;
        $this->aiAnalysis = $aiAnalysis;
    }

    /**
     * Enrich queue item with data from all sources
     */
    public function enrichQueueItem(ProductIntakeQueue $queueItem): array
    {
        $enrichedData = [
            'sources_used' => [],
            'confidence_score' => 0,
            'product_data' => [],
            'warnings' => [],
        ];

        // 1. Validate barcode if present
        if ($queueItem->hasBarcode()) {
            $barcodeData = $this->processBarcode($queueItem->barcode);
            $enrichedData['sources_used'][] = 'barcode_validation';
            $enrichedData['product_data']['barcode_data'] = $barcodeData;
        }

        // 2. Analyze photos with AI if present
        if ($queueItem->hasPhotos()) {
            $aiData = $this->processPhotos($queueItem->photos);
            $enrichedData['sources_used'][] = 'ai_vision';
            $enrichedData['product_data']['ai_data'] = $aiData;
        }

        // 3. Merge all data sources
        $mergedData = $this->mergeDataSources($enrichedData['product_data']);
        $enrichedData['merged_product_fields'] = $mergedData;

        // 4. Calculate overall confidence
        $enrichedData['confidence_score'] = $this->calculateOverallConfidence($enrichedData);

        // 5. Add recommendations
        $enrichedData['recommendations'] = $this->generateRecommendations($enrichedData);

        return $enrichedData;
    }

    /**
     * Process barcode data
     */
    private function processBarcode(string $barcode): array
    {
        $validation = $this->barcodeValidator->validate($barcode);

        return [
            'original' => $barcode,
            'valid' => $validation['valid'],
            'type' => $validation['type'],
            'formatted' => $validation['formatted'],
            'errors' => $validation['errors'],
        ];
    }

    /**
     * Process photos with AI
     */
    private function processPhotos(array $photos): array
    {
        // Convert photo URLs to paths if needed
        $photoPaths = array_map(function ($photo) {
            if (str_starts_with($photo, 'http')) {
                return $photo;
            }

            // Assume stored in public storage
            return storage_path('app/public/'.$photo);
        }, $photos);

        return $this->aiAnalysis->analyzeProductImages($photoPaths);
    }

    /**
     * Merge data from all sources with conflict resolution
     */
    private function mergeDataSources(array $sources): array
    {
        $merged = [
            // Identifiers
            'upc' => null,
            'ean' => null,
            'model' => null,

            // Basic info
            'brand' => null,
            'name' => null,
            'description' => null,
            'category' => null,

            // Specifications
            'specifications' => [],

            // Condition
            'condition' => null,
            'condition_notes' => null,

            // Metadata
            'data_sources' => [],
        ];

        // Priority: Barcode data > AI data > User notes

        // From barcode
        if (isset($sources['barcode_data'])) {
            $barcodeData = $sources['barcode_data'];
            if ($barcodeData['valid']) {
                if ($barcodeData['type'] === 'UPC-A' || $barcodeData['type'] === 'UPC-E') {
                    $merged['upc'] = $barcodeData['formatted'];
                    $merged['data_sources'][] = 'barcode:upc';
                } elseif ($barcodeData['type'] === 'EAN-13' || $barcodeData['type'] === 'EAN-8') {
                    $merged['ean'] = $barcodeData['formatted'];
                    $merged['data_sources'][] = 'barcode:ean';
                }
            }
        }

        // From AI analysis
        if (isset($sources['ai_data']['extracted_data'])) {
            $aiData = $sources['ai_data']['extracted_data'];

            if (! empty($aiData['brand'])) {
                $merged['brand'] = $aiData['brand'];
                $merged['data_sources'][] = 'ai:brand';
            }

            if (! empty($aiData['model'])) {
                $merged['model'] = $aiData['model'];
                $merged['data_sources'][] = 'ai:model';
            }

            if (! empty($aiData['category'])) {
                $merged['category'] = $aiData['category'];
                $merged['data_sources'][] = 'ai:category';
            }

            if (! empty($aiData['description'])) {
                $merged['description'] = $aiData['description'];
                $merged['data_sources'][] = 'ai:description';
            }

            if (! empty($aiData['specifications'])) {
                $merged['specifications'] = $aiData['specifications'];
                $merged['data_sources'][] = 'ai:specifications';
            }

            if (! empty($aiData['condition_assessment'])) {
                $merged['condition_notes'] = $aiData['condition_assessment'];
                $merged['data_sources'][] = 'ai:condition';
            }

            // Generate product name from available data
            if (! $merged['name']) {
                $merged['name'] = $this->generateProductName($merged);
            }
        }

        return $merged;
    }

    /**
     * Generate product name from available data
     */
    private function generateProductName(array $data): ?string
    {
        $parts = [];

        if (! empty($data['brand'])) {
            $parts[] = $data['brand'];
        }

        if (! empty($data['model'])) {
            $parts[] = $data['model'];
        }

        if (! empty($data['category']) && count($parts) < 2) {
            $parts[] = $data['category'];
        }

        return ! empty($parts) ? implode(' ', $parts) : null;
    }

    /**
     * Calculate overall confidence score
     */
    private function calculateOverallConfidence(array $enrichedData): float
    {
        $totalWeight = 0;
        $weightedScore = 0;

        // Barcode validation (30% weight if present)
        if (in_array('barcode_validation', $enrichedData['sources_used'])) {
            $totalWeight += 0.30;
            if ($enrichedData['product_data']['barcode_data']['valid']) {
                $weightedScore += 0.30;
            }
        }

        // AI analysis (70% weight if present)
        if (in_array('ai_vision', $enrichedData['sources_used'])) {
            $totalWeight += 0.70;
            $aiConfidence = $enrichedData['product_data']['ai_data']['confidence_score'] ?? 0;
            $weightedScore += $aiConfidence * 0.70;
        }

        // If no sources, return 0
        if ($totalWeight === 0) {
            return 0.0;
        }

        // Normalize to 0-1 range
        return round($weightedScore / $totalWeight, 2);
    }

    /**
     * Generate recommendations for processing
     */
    private function generateRecommendations(array $enrichedData): array
    {
        $recommendations = [];
        $confidence = $enrichedData['confidence_score'];
        $merged = $enrichedData['merged_product_fields'];

        // Confidence-based recommendations
        if ($confidence >= 0.85) {
            $recommendations[] = [
                'type' => 'success',
                'message' => 'High confidence - Ready for quick approval',
                'action' => 'Can be batch-approved with minimal review',
            ];
        } elseif ($confidence >= 0.60) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Medium confidence - Review recommended',
                'action' => 'Verify extracted data before publishing',
            ];
        } else {
            $recommendations[] = [
                'type' => 'error',
                'message' => 'Low confidence - Manual entry required',
                'action' => 'Most fields will need manual input',
            ];
        }

        // Missing data recommendations
        if (empty($merged['brand'])) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Brand not detected',
                'action' => 'Please enter brand manually',
            ];
        }

        if (empty($merged['category'])) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Category not detected',
                'action' => 'Please select category manually',
            ];
        }

        if (empty($merged['upc']) && empty($merged['ean'])) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'No barcode found',
                'action' => 'Consider adding barcode if available',
            ];
        }

        return $recommendations;
    }
}
