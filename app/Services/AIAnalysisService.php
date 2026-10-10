<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AIAnalysisService
{
    private ?LocalVisionService $localVision;

    private ?CloudVisionService $cloudVision;

    private string $provider;

    public function __construct()
    {
        // Determine which provider to use based on configuration
        $this->provider = config('services.vision.provider', 'local'); // 'local' or 'cloud'

        if ($this->provider === 'local') {
            $this->localVision = app(LocalVisionService::class);
        } else {
            $this->cloudVision = app(CloudVisionService::class);
        }
    }

    /**
     * Analyze product images using configured provider
     */
    public function analyzeProductImages(array $imagePaths): array
    {
        Log::info('Analyzing images with '.$this->provider.' vision provider', [
            'image_count' => count($imagePaths),
        ]);

        try {
            if ($this->provider === 'local') {
                return $this->localVision->analyzeProductImages($imagePaths);
            } else {
                return $this->cloudVision->analyzeProductImages($imagePaths);
            }
        } catch (\Exception $e) {
            Log::error('Vision analysis failed', [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
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
     * Test API/service connection
     */
    public function testConnection(): array
    {
        if ($this->provider === 'local') {
            return $this->localVision->testConnection();
        } else {
            return $this->cloudVision->testConnection();
        }
    }

    /**
     * Get current provider name
     */
    public function getProvider(): string
    {
        return $this->provider;
    }
}
