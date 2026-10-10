<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class LocalVisionService
{
    /**
     * Analyze product images using local OCR (Tesseract)
     * This is a FREE alternative to Claude Vision API
     */
    public function analyzeProductImages(array $imagePaths): array
    {
        try {
            $extractedData = [
                'extracted_text' => [],
                'category' => null,
                'brand' => null,
                'model' => null,
                'description' => null,
                'specifications' => [],
                'visible_barcodes' => [],
                'confidence_notes' => 'Local OCR processing',
            ];

            $allText = [];

            // Process each image with Tesseract OCR
            foreach ($imagePaths as $path) {
                $text = $this->extractTextFromImage($path);
                if (! empty($text)) {
                    $allText[] = $text;
                }
            }

            $extractedData['extracted_text'] = $allText;

            // Combine all text for analysis
            $combinedText = implode(' ', $allText);

            // Use pattern matching and heuristics to extract data
            $extractedData['brand'] = $this->detectBrand($combinedText);
            $extractedData['model'] = $this->detectModel($combinedText);
            $extractedData['category'] = $this->detectCategory($combinedText);
            $extractedData['visible_barcodes'] = $this->detectBarcodes($combinedText);
            $extractedData['specifications'] = $this->extractSpecifications($combinedText);
            $extractedData['description'] = $this->generateDescription($extractedData);

            // Calculate confidence based on data extracted
            $confidence = $this->calculateLocalConfidence($extractedData);

            return [
                'success' => true,
                'extracted_data' => $extractedData,
                'confidence_score' => $confidence,
                'raw_response' => [
                    'provider' => 'local_ocr',
                    'combined_text' => $combinedText,
                ],
                'analyzed_images' => $imagePaths,
            ];

        } catch (\Exception $e) {
            Log::error('Local Vision Analysis Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'extracted_data' => [],
                'confidence_score' => 0,
            ];
        }
    }

    /**
     * Extract text from image using Tesseract OCR
     */
    private function extractTextFromImage(string $path): string
    {
        try {
            // Get actual file path
            $realPath = $this->getRealPath($path);

            if (! file_exists($realPath)) {
                Log::warning('Image file not found for OCR', ['path' => $realPath]);

                return '';
            }

            // Preprocess image for better OCR results
            $preprocessedPath = $this->preprocessImage($realPath);

            // Run Tesseract OCR
            $result = Process::run("tesseract {$preprocessedPath} stdout");

            // Clean up preprocessed file if different
            if ($preprocessedPath !== $realPath && file_exists($preprocessedPath)) {
                @unlink($preprocessedPath);
            }

            if ($result->successful()) {
                $text = $result->output();

                // Clean up the extracted text
                return $this->cleanExtractedText($text);
            }

            Log::warning('Tesseract OCR failed', [
                'path' => $realPath,
                'error' => $result->errorOutput(),
            ]);

            return '';

        } catch (\Exception $e) {
            Log::error('Error extracting text from image', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Preprocess image for better OCR results
     * Can integrate with your existing U2Net background removal
     */
    private function preprocessImage(string $imagePath): string
    {
        try {
            // Create temp file for processed image
            $tempPath = sys_get_temp_dir().'/ocr_'.basename($imagePath);

            // Use ImageMagick to enhance image for OCR
            // Increase contrast, convert to grayscale, remove noise
            $commands = [
                "convert {$imagePath}",
                '-colorspace Gray',           // Convert to grayscale
                '-contrast -contrast',        // Increase contrast
                '-sharpen 0x1',               // Sharpen slightly
                '-density 300',               // Increase DPI
                "{$tempPath}",
            ];

            $result = Process::run(implode(' ', $commands));

            if ($result->successful() && file_exists($tempPath)) {
                return $tempPath;
            }

            // If preprocessing fails, use original
            return $imagePath;

        } catch (\Exception $e) {
            Log::warning('Image preprocessing failed, using original', [
                'error' => $e->getMessage(),
            ]);

            return $imagePath;
        }
    }

    /**
     * Clean extracted text
     */
    private function cleanExtractedText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Remove special characters that are clearly OCR errors
        $text = preg_replace('/[^\x20-\x7E]/', '', $text);

        // Trim
        $text = trim($text);

        return $text;
    }

    /**
     * Detect brand from extracted text using common patterns
     */
    private function detectBrand(string $text): ?string
    {
        // Common brand keywords/patterns
        $brandPatterns = [
            // Electronics
            '/\b(Sony|Samsung|LG|Apple|Dell|HP|Lenovo|Asus|Acer|Microsoft|Intel|AMD)\b/i',
            // Tools
            '/\b(DeWalt|Makita|Milwaukee|Bosch|Ryobi|Craftsman|Black\+Decker)\b/i',
            // General
            '/\b(Nike|Adidas|Canon|Nikon|Panasonic|Philips|GE|Whirlpool)\b/i',
            // Look for "by Brand" or "Brand®" patterns
            '/(?:by|from|made by)\s+([A-Z][a-zA-Z]{2,})/i',
            '/([A-Z][a-zA-Z]{2,})(?:®|™|©)/i',
        ];

        foreach ($brandPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Detect model number from extracted text
     */
    private function detectModel(string $text): ?string
    {
        // Common model number patterns
        $modelPatterns = [
            // Alphanumeric with dashes: ABC-123, X900-PRO
            '/\b([A-Z]{1,3}[-_]?\d{3,}(?:[-_]?[A-Z0-9]+)?)\b/',
            // Model: followed by identifier
            '/Model[:\s]+([A-Z0-9][-A-Z0-9]{3,})/i',
            // Part numbers
            '/(?:P\/N|Part\s*#)[:\s]+([A-Z0-9][-A-Z0-9]{4,})/i',
            // SKU patterns
            '/SKU[:\s]+([A-Z0-9][-A-Z0-9]{4,})/i',
        ];

        foreach ($modelPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Detect category from extracted text using keywords
     */
    private function detectCategory(string $text): ?string
    {
        $categoryKeywords = [
            'Electronics' => ['laptop', 'computer', 'monitor', 'keyboard', 'mouse', 'tablet', 'phone', 'camera', 'headphones'],
            'Tools' => ['drill', 'saw', 'wrench', 'hammer', 'screwdriver', 'power tool', 'hand tool'],
            'Clothing' => ['shirt', 'pants', 'dress', 'shoes', 'jacket', 'sweater', 'hat'],
            'Home & Garden' => ['furniture', 'lamp', 'table', 'chair', 'decor', 'plant'],
            'Toys & Games' => ['toy', 'game', 'puzzle', 'doll', 'action figure', 'board game'],
            'Sports' => ['ball', 'bat', 'racket', 'weights', 'fitness', 'exercise'],
            'Automotive' => ['car', 'auto', 'vehicle', 'tire', 'battery', 'engine'],
        ];

        $textLower = strtolower($text);
        $categoryScores = [];

        foreach ($categoryKeywords as $category => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (stripos($textLower, $keyword) !== false) {
                    $score++;
                }
            }
            if ($score > 0) {
                $categoryScores[$category] = $score;
            }
        }

        if (! empty($categoryScores)) {
            arsort($categoryScores);

            return array_key_first($categoryScores);
        }

        return null;
    }

    /**
     * Detect barcodes in text (OCR may pick them up)
     */
    private function detectBarcodes(string $text): array
    {
        $barcodes = [];

        // UPC-A (12 digits)
        if (preg_match_all('/\b(\d{12})\b/', $text, $matches)) {
            $barcodes = array_merge($barcodes, $matches[1]);
        }

        // EAN-13 (13 digits)
        if (preg_match_all('/\b(\d{13})\b/', $text, $matches)) {
            $barcodes = array_merge($barcodes, $matches[1]);
        }

        return array_unique($barcodes);
    }

    /**
     * Extract specifications from text
     */
    private function extractSpecifications(string $text): array
    {
        $specs = [];

        // Dimensions: 10" x 5" x 3"
        if (preg_match('/(\d+(?:\.\d+)?)\s*["\']?\s*x\s*(\d+(?:\.\d+)?)\s*["\']?\s*x\s*(\d+(?:\.\d+)?)\s*["\']?/i', $text, $matches)) {
            $specs['dimensions'] = $matches[0];
        }

        // Weight: 5 lbs, 2.3 kg
        if (preg_match('/(\d+(?:\.\d+)?)\s*(lbs?|pounds?|kg|kilograms?|oz|ounces?)/i', $text, $matches)) {
            $specs['weight'] = $matches[0];
        }

        // Voltage: 120V, 12V DC
        if (preg_match('/(\d+)\s*V(?:olts?)?\s*(?:AC|DC)?/i', $text, $matches)) {
            $specs['voltage'] = $matches[0];
        }

        // Wattage: 100W, 1500 watts
        if (preg_match('/(\d+)\s*W(?:atts?)?/i', $text, $matches)) {
            $specs['wattage'] = $matches[0];
        }

        // Capacity: 500GB, 1TB, 16GB RAM
        if (preg_match('/(\d+)\s*(GB|TB|MB)(?:\s+RAM)?/i', $text, $matches)) {
            $specs['capacity'] = $matches[0];
        }

        // Color
        $colors = ['black', 'white', 'red', 'blue', 'green', 'yellow', 'gray', 'silver', 'gold'];
        foreach ($colors as $color) {
            if (stripos($text, $color) !== false) {
                $specs['color'] = ucfirst($color);
                break;
            }
        }

        return $specs;
    }

    /**
     * Generate a basic description from extracted data
     */
    private function generateDescription(array $data): ?string
    {
        $parts = [];

        if (! empty($data['brand'])) {
            $parts[] = $data['brand'];
        }

        if (! empty($data['model'])) {
            $parts[] = 'Model '.$data['model'];
        }

        if (! empty($data['category'])) {
            $parts[] = strtolower($data['category']);
        }

        if (! empty($data['specifications']['color'])) {
            $parts[] = 'in '.$data['specifications']['color'];
        }

        if (empty($parts)) {
            return null;
        }

        return implode(' ', $parts);
    }

    /**
     * Calculate confidence for local OCR
     */
    private function calculateLocalConfidence(array $data): float
    {
        $score = 0.0;

        // Text extracted (30%)
        if (! empty($data['extracted_text']) && count($data['extracted_text']) > 0) {
            $totalTextLength = array_sum(array_map('strlen', $data['extracted_text']));
            if ($totalTextLength > 50) {
                $score += 0.30;
            } elseif ($totalTextLength > 20) {
                $score += 0.20;
            } elseif ($totalTextLength > 5) {
                $score += 0.10;
            }
        }

        // Brand detected (20%)
        if (! empty($data['brand'])) {
            $score += 0.20;
        }

        // Model detected (15%)
        if (! empty($data['model'])) {
            $score += 0.15;
        }

        // Category detected (15%)
        if (! empty($data['category'])) {
            $score += 0.15;
        }

        // Specifications (10%)
        if (! empty($data['specifications']) && count($data['specifications']) > 0) {
            $score += 0.10;
        }

        // Barcode detected (10%)
        if (! empty($data['visible_barcodes']) && count($data['visible_barcodes']) > 0) {
            $score += 0.10;
        }

        return round(min($score, 1.0), 2);
    }

    /**
     * Get real file path
     */
    private function getRealPath(string $path): string
    {
        if (str_starts_with($path, 'http')) {
            // Download temporarily
            $tempPath = sys_get_temp_dir().'/'.basename($path);
            file_put_contents($tempPath, file_get_contents($path));

            return $tempPath;
        } elseif (Storage::exists($path)) {
            return Storage::path($path);
        } elseif (file_exists($path)) {
            return $path;
        } else {
            $publicPath = storage_path('app/public/'.$path);
            if (file_exists($publicPath)) {
                return $publicPath;
            }
        }

        throw new \Exception("File not found: {$path}");
    }

    /**
     * Test if Tesseract is installed
     */
    public function testConnection(): array
    {
        try {
            $result = Process::run('tesseract --version');

            return [
                'success' => $result->successful(),
                'message' => $result->successful() ? 'Tesseract OCR is available' : 'Tesseract not found',
                'version' => $result->successful() ? trim(explode("\n", $result->output())[0]) : null,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Tesseract not installed: '.$e->getMessage(),
            ];
        }
    }
}
