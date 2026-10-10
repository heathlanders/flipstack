<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Cloud-based vision analysis using Anthropic Claude
 * This is a PAID service - requires API key
 */
class CloudVisionService
{
    private string $apiKey;

    private string $apiUrl = 'https://api.anthropic.com/v1/messages';

    private string $model = 'claude-sonnet-4-20250514';

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key');
    }

    /**
     * Analyze product images using Claude Vision API
     */
    public function analyzeProductImages(array $imagePaths): array
    {
        try {
            $imageContents = [];

            // Convert images to base64
            foreach ($imagePaths as $path) {
                $imageData = $this->getImageAsBase64($path);
                if ($imageData) {
                    $imageContents[] = [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'media_type' => $imageData['mime_type'],
                            'data' => $imageData['base64'],
                        ],
                    ];
                }
            }

            if (empty($imageContents)) {
                throw new \Exception('No valid images to analyze');
            }

            // Build the prompt
            $prompt = $this->buildProductAnalysisPrompt();

            // Make API request
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post($this->apiUrl, [
                'model' => $this->model,
                'max_tokens' => 2000,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => array_merge($imageContents, [
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                        ]),
                    ],
                ],
            ]);

            if (! $response->successful()) {
                Log::error('Claude API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('AI analysis failed: '.$response->body());
            }

            $result = $response->json();

            // Parse the AI response
            return $this->parseAIResponse($result, $imagePaths);

        } catch (\Exception $e) {
            Log::error('AI Analysis Exception', [
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
     * Build the analysis prompt
     */
    private function buildProductAnalysisPrompt(): string
    {
        return <<<'PROMPT'
Analyze these product images and extract all relevant information. Please provide your response in JSON format with the following structure:

{
  "extracted_text": ["all visible text from the images"],
  "category": "primary product category",
  "brand": "manufacturer or brand name",
  "model": "model number or identifier",
  "description": "detailed product description",
  "specifications": {
    "key_spec_1": "value",
    "key_spec_2": "value"
  },
  "condition_assessment": "assessment of product condition if visible",
  "visible_barcodes": ["any barcodes visible in images"],
  "confidence_notes": "explanation of confidence level and any uncertainties"
}

Focus on:
1. All visible text (labels, boxes, product markings)
2. Brand names and logos
3. Model numbers and part numbers
4. Product category (electronics, tools, clothing, etc.)
5. Physical condition (new, used, damaged, packaging condition)
6. Technical specifications visible
7. Any barcodes (UPC, EAN) visible in images

Be thorough but honest about uncertainty. If something is unclear, note it in confidence_notes.
PROMPT;
    }

    /**
     * Parse AI response into structured data
     */
    private function parseAIResponse(array $response, array $imagePaths): array
    {
        try {
            $content = $response['content'][0]['text'] ?? '';

            // Extract JSON from response (Claude sometimes wraps it in markdown)
            $jsonString = $this->extractJSON($content);
            $extractedData = json_decode($jsonString, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse AI response as JSON');
            }

            // Calculate confidence score
            $confidence = $this->calculateConfidence($extractedData);

            return [
                'success' => true,
                'extracted_data' => $extractedData,
                'confidence_score' => $confidence,
                'raw_response' => $response,
                'analyzed_images' => $imagePaths,
            ];

        } catch (\Exception $e) {
            Log::error('AI Response Parsing Error', [
                'error' => $e->getMessage(),
                'response' => $response,
            ]);

            return [
                'success' => false,
                'error' => 'Failed to parse AI response',
                'extracted_data' => [],
                'confidence_score' => 0,
                'raw_response' => $response,
            ];
        }
    }

    /**
     * Extract JSON from text (handles markdown code blocks)
     */
    private function extractJSON(string $text): string
    {
        // Remove markdown code blocks if present
        $text = preg_replace('/```json\s*/', '', $text);
        $text = preg_replace('/```\s*$/', '', $text);
        $text = trim($text);

        // If starts with { or [, assume it's JSON
        if (str_starts_with($text, '{') || str_starts_with($text, '[')) {
            return $text;
        }

        // Try to find JSON in the text
        if (preg_match('/{.*}/s', $text, $matches)) {
            return $matches[0];
        }

        return $text;
    }

    /**
     * Calculate confidence score based on extracted data
     */
    private function calculateConfidence(array $data): float
    {
        $score = 0.0;

        // Brand found (20 points)
        if (! empty($data['brand'])) {
            $score += 0.20;
        }

        // Category found (20 points)
        if (! empty($data['category'])) {
            $score += 0.20;
        }

        // Model/identifier found (15 points)
        if (! empty($data['model'])) {
            $score += 0.15;
        }

        // Description found (15 points)
        if (! empty($data['description']) && strlen($data['description']) > 20) {
            $score += 0.15;
        }

        // Extracted text (10 points)
        if (! empty($data['extracted_text']) && count($data['extracted_text']) > 0) {
            $score += 0.10;
        }

        // Specifications found (10 points)
        if (! empty($data['specifications']) && count($data['specifications']) > 0) {
            $score += 0.10;
        }

        // Barcode found (10 points bonus)
        if (! empty($data['visible_barcodes']) && count($data['visible_barcodes']) > 0) {
            $score += 0.10;
        }

        return round($score, 2);
    }

    /**
     * Get image as base64 encoded string
     */
    private function getImageAsBase64(string $path): ?array
    {
        try {
            // Handle different path types
            if (str_starts_with($path, 'http')) {
                $contents = file_get_contents($path);
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->buffer($contents);
            } elseif (Storage::exists($path)) {
                $contents = Storage::get($path);
                $mimeType = Storage::mimeType($path);
            } elseif (file_exists($path)) {
                $contents = file_get_contents($path);
                $mimeType = mime_content_type($path);
            } else {
                $publicPath = storage_path('app/public/'.$path);
                if (file_exists($publicPath)) {
                    $contents = file_get_contents($publicPath);
                    $mimeType = mime_content_type($publicPath);
                } else {
                    Log::warning('Image file not found', ['path' => $path]);

                    return null;
                }
            }

            // Validate mime type
            if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                Log::warning('Invalid image mime type', ['mime' => $mimeType, 'path' => $path]);

                return null;
            }

            return [
                'base64' => base64_encode($contents),
                'mime_type' => $mimeType,
            ];

        } catch (\Exception $e) {
            Log::error('Error encoding image', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Test API connection
     */
    public function testConnection(): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(10)->post($this->apiUrl, [
                'model' => $this->model,
                'max_tokens' => 50,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Hello, respond with OK if you can read this.',
                    ],
                ],
            ]);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful() ? 'Claude API connection successful' : 'API connection failed',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: '.$e->getMessage(),
            ];
        }
    }
}
