<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackgroundRemovalService
{
    private ?string $baseUrl;

    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.background_removal.url');
        $this->apiKey = config('services.background_removal.api_key');
    }

    /**
     * Remove background from a single image via HTTP API
     */
    public function removeBackground(string $imagePath): ?string
    {
        if (! $this->baseUrl) {
            return $imagePath;
        }

        try {
            $realPath = $this->getRealPath($imagePath);

            if (! file_exists($realPath)) {
                Log::warning('Image not found for background removal', ['path' => $imagePath]);

                return $imagePath;
            }

            $request = Http::timeout(30);
            if ($this->apiKey) {
                $request = $request->withHeaders(['X-API-Key' => $this->apiKey]);
            }
            $response = $request
                ->attach('file', file_get_contents($realPath), basename($realPath))
                ->post("{$this->baseUrl}/api/v1/process?format=base64");

            if (! $response->successful()) {
                Log::warning('Background removal API returned error', [
                    'path' => $imagePath,
                    'status' => $response->status(),
                ]);

                return $imagePath;
            }

            $data = $response->json();
            $imageData = $data['image'] ?? $data['result'] ?? null;

            if (! $imageData) {
                Log::warning('Background removal API returned no image data', ['path' => $imagePath]);

                return $imagePath;
            }

            // Save processed image
            $filename = basename($imagePath);
            $outputDir = 'queue-photos-processed';
            Storage::disk('public')->makeDirectory($outputDir);

            $outputPath = "{$outputDir}/{$filename}";
            Storage::disk('public')->put($outputPath, base64_decode($imageData));

            return 'queue-photos-processed/'.$filename;

        } catch (\Exception $e) {
            Log::error('Background removal exception', [
                'path' => $imagePath,
                'error' => $e->getMessage(),
            ]);

            return $imagePath;
        }
    }

    /**
     * Process multiple images (up to 24 for eBay)
     */
    public function processMultiple(array $imagePaths): array
    {
        if (! $this->baseUrl) {
            return $imagePaths;
        }

        // Try batch endpoint first
        try {
            $http = Http::timeout(120);
            if ($this->apiKey) {
                $http = $http->withHeaders(['X-API-Key' => $this->apiKey]);
            }
            $multipart = $http->asMultipart();

            foreach ($imagePaths as $index => $imagePath) {
                $realPath = $this->getRealPath($imagePath);
                if (file_exists($realPath)) {
                    $multipart = $multipart->attach("files[{$index}]", file_get_contents($realPath), basename($realPath));
                }
            }

            $response = $multipart->post("{$this->baseUrl}/api/v1/batch?format=base64");

            if ($response->successful()) {
                $results = $response->json('images', []);
                $processedPaths = [];
                $outputDir = 'queue-photos-processed';
                Storage::disk('public')->makeDirectory($outputDir);

                foreach ($imagePaths as $index => $imagePath) {
                    $imageData = $results[$index]['image'] ?? $results[$index]['result'] ?? null;
                    if ($imageData) {
                        $filename = basename($imagePath);
                        $outputPath = "{$outputDir}/{$filename}";
                        Storage::disk('public')->put($outputPath, base64_decode($imageData));
                        $processedPaths[] = 'queue-photos-processed/'.$filename;
                    } else {
                        $processedPaths[] = $imagePath;
                    }
                }

                return $processedPaths;
            }
        } catch (\Exception $e) {
            Log::warning('Batch background removal failed, falling back to individual', [
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback: process individually
        $processedPaths = [];
        foreach ($imagePaths as $imagePath) {
            $processedPaths[] = $this->removeBackground($imagePath);
        }

        return $processedPaths;
    }

    /**
     * Remove background from a single image with explicit tuning options, saving
     * the result to a NEW versioned filename (so revert/cache never collide).
     *
     * Options (all optional): strength, model, alpha_matting, fg_threshold,
     * bg_threshold, erode_size, post_process_mask, fill_holes, keep_largest,
     * padding_pct, center, bg_color, remove_bg.
     *
     * @return array{path: string, diagnostics: array, success: bool}
     */
    public function removeBackgroundWithOptions(string $imagePath, array $options = []): array
    {
        $fallback = ['path' => $imagePath, 'diagnostics' => [], 'success' => false];

        if (! $this->baseUrl) {
            return $fallback;
        }

        try {
            $realPath = $this->getRealPath($imagePath);

            if (! file_exists($realPath)) {
                Log::warning('Image not found for background removal', ['path' => $imagePath]);

                return $fallback;
            }

            $query = $this->buildQueryString($options);

            $request = Http::timeout(60);
            if ($this->apiKey) {
                $request = $request->withHeaders(['X-API-Key' => $this->apiKey]);
            }
            $response = $request
                ->attach('file', file_get_contents($realPath), basename($realPath))
                ->post("{$this->baseUrl}/api/v1/process?format=base64&{$query}");

            if (! $response->successful()) {
                Log::warning('Background removal API returned error', [
                    'path' => $imagePath,
                    'status' => $response->status(),
                ]);

                return $fallback;
            }

            $data = $response->json();
            $imageData = $data['image'] ?? $data['result'] ?? null;

            if (! $imageData) {
                Log::warning('Background removal API returned no image data', ['path' => $imagePath]);

                return $fallback;
            }

            $outputDir = 'queue-photos-processed';
            Storage::disk('public')->makeDirectory($outputDir);

            $base = pathinfo(basename($imagePath), PATHINFO_FILENAME);
            $outputPath = "{$outputDir}/{$base}__v".time().'.png';
            Storage::disk('public')->put($outputPath, base64_decode($imageData));

            return [
                'path' => $outputPath,
                'diagnostics' => $data['diagnostics'] ?? [],
                'success' => true,
            ];
        } catch (\Exception $e) {
            Log::error('Background removal exception', [
                'path' => $imagePath,
                'error' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }

    /**
     * Process multiple images with a condition-based cropping preset.
     * Presets map to padding only; all use the balanced strength profile.
     */
    public function processMultipleWithCropping(array $imagePaths, string $preset = 'default'): array
    {
        $padding = match ($preset) {
            'tight' => 5,
            'padded' => 40,
            default => 20,
        };

        $options = ['strength' => 'balanced', 'padding_pct' => $padding];

        $processed = [];
        foreach ($imagePaths as $imagePath) {
            $result = $this->removeBackgroundWithOptions($imagePath, $options);
            $processed[] = $result['success'] ? $result['path'] : $imagePath;
        }

        return $processed;
    }

    /**
     * Build the query string for the process endpoint from an options array.
     * Booleans are serialized as true/false; only whitelisted keys are sent.
     */
    private function buildQueryString(array $options): string
    {
        $allowed = [
            'strength', 'model', 'alpha_matting', 'fg_threshold', 'bg_threshold',
            'erode_size', 'post_process_mask', 'fill_holes', 'keep_largest',
            'padding_pct', 'center', 'bg_color', 'remove_bg',
        ];

        $params = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $options) || $options[$key] === null) {
                continue;
            }
            $value = $options[$key];
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $params[$key] = $value;
        }

        return http_build_query($params);
    }

    /**
     * Get real file system path
     */
    private function getRealPath(string $path): string
    {
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->path($path);
        }

        if (file_exists($path)) {
            return $path;
        }

        throw new \Exception("Image file not found: {$path}");
    }

    /**
     * Check if background removal service is available
     */
    public function isAvailable(): bool
    {
        if (! $this->baseUrl) {
            return false;
        }

        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get processing statistics
     */
    public function getStats(array $originalPaths, array $processedPaths): array
    {
        $totalSize = 0;
        $processedSize = 0;

        foreach ($originalPaths as $path) {
            try {
                $realPath = $this->getRealPath($path);
                $totalSize += filesize($realPath);
            } catch (\Exception $e) {
                // Skip missing files
            }
        }

        foreach ($processedPaths as $path) {
            try {
                $realPath = $this->getRealPath($path);
                $processedSize += filesize($realPath);
            } catch (\Exception $e) {
                // Skip missing files
            }
        }

        return [
            'original_count' => count($originalPaths),
            'processed_count' => count($processedPaths),
            'original_size_mb' => round($totalSize / 1048576, 2),
            'processed_size_mb' => round($processedSize / 1048576, 2),
            'size_reduction_percent' => $totalSize > 0
                ? round((1 - $processedSize / $totalSize) * 100, 1)
                : 0,
        ];
    }
}
