<?php

namespace App\Services\Image;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class EnhancedBackgroundRemovalService
{
    /**
     * Remove background and optionally crop to product
     */
    public function removeBackgroundAndCrop(
        string $imagePath,
        array $options = []
    ): ?string {
        try {
            $realPath = $this->getRealPath($imagePath);

            if (! file_exists($realPath)) {
                Log::warning('Image not found for background removal', ['path' => $imagePath]);

                return $imagePath;
            }

            $filename = basename($imagePath);
            $outputPath = storage_path('app/public/queue-photos-processed/'.$filename);

            // Ensure directory exists
            $outputDir = dirname($outputPath);
            if (! is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Build U2Net command with cropping options
            $command = $this->buildU2NetCommand($realPath, $outputPath, $options);

            $result = Process::run($command);

            if ($result->successful() && file_exists($outputPath)) {
                // Post-process for additional cropping if needed
                if (isset($options['smart_crop']) && $options['smart_crop']) {
                    $this->applySmartCrop($outputPath, $options);
                }

                return 'queue-photos-processed/'.$filename;
            }

            Log::warning('Background removal failed', [
                'path' => $imagePath,
                'command' => $command,
                'error' => $result->errorOutput(),
            ]);

            return $imagePath;

        } catch (\Exception $e) {
            Log::error('Background removal exception', [
                'path' => $imagePath,
                'error' => $e->getMessage(),
            ]);

            return $imagePath;
        }
    }

    /**
     * Build U2Net command with cropping parameters
     */
    private function buildU2NetCommand(string $inputPath, string $outputPath, array $options): string
    {
        $command = "python3 /path/to/u2net/remove_bg.py --input {$inputPath} --output {$outputPath}";

        // Cropping options
        if (isset($options['crop_to_object']) && $options['crop_to_object']) {
            $command .= ' --crop-to-object';
        }

        if (isset($options['padding'])) {
            $command .= " --padding {$options['padding']}";
        }

        if (isset($options['min_width']) && isset($options['min_height'])) {
            $command .= " --min-size {$options['min_width']}x{$options['min_height']}";
        }

        if (isset($options['aspect_ratio'])) {
            $command .= " --aspect-ratio {$options['aspect_ratio']}";
        }

        // Square crop for platforms like Instagram
        if (isset($options['square_crop']) && $options['square_crop']) {
            $command .= ' --square-crop';
        }

        return $command;
    }

    /**
     * Apply additional smart cropping using ImageMagick
     */
    private function applySmartCrop(string $imagePath, array $options): void
    {
        try {
            if (isset($options['trim_whitespace']) && $options['trim_whitespace']) {
                // Trim transparent/white areas
                $command = "magick {$imagePath} -trim +repage {$imagePath}";
                Process::run($command);
            }

            if (isset($options['add_border']) && $options['add_border']) {
                $borderSize = $options['border_size'] ?? 20;
                $borderColor = $options['border_color'] ?? 'white';
                $command = "magick {$imagePath} -bordercolor {$borderColor} -border {$borderSize} {$imagePath}";
                Process::run($command);
            }

        } catch (\Exception $e) {
            Log::warning('Smart crop failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Process images with different crop presets for different platforms
     */
    public function processForPlatforms(string $imagePath): array
    {
        $results = [];

        // eBay: Clean background, moderate crop
        $results['ebay'] = $this->removeBackgroundAndCrop($imagePath, [
            'crop_to_object' => true,
            'padding' => 30,
            'min_width' => 500,
            'min_height' => 500,
            'trim_whitespace' => true,
        ]);

        // Amazon: Tight crop, square format
        $results['amazon'] = $this->removeBackgroundAndCrop($imagePath, [
            'crop_to_object' => true,
            'square_crop' => true,
            'padding' => 10,
            'min_width' => 1000,
            'min_height' => 1000,
        ]);

        // Instagram: Square with more padding
        $results['instagram'] = $this->removeBackgroundAndCrop($imagePath, [
            'crop_to_object' => true,
            'square_crop' => true,
            'padding' => 50,
            'add_border' => true,
            'border_size' => 20,
        ]);

        // General web: Clean and optimized
        $results['web'] = $this->removeBackgroundAndCrop($imagePath, [
            'crop_to_object' => true,
            'padding' => 25,
            'smart_crop' => true,
            'trim_whitespace' => true,
        ]);

        return $results;
    }

    /**
     * Analyze image composition and suggest optimal cropping
     */
    public function analyzeCropping(string $imagePath): array
    {
        try {
            $realPath = $this->getRealPath($imagePath);

            // Get image dimensions and object bounds
            $result = Process::run("python3 /path/to/u2net/analyze_bounds.py --input {$realPath}");

            if ($result->successful()) {
                $analysis = json_decode($result->output(), true);

                return [
                    'original_size' => $analysis['original_size'],
                    'object_bounds' => $analysis['object_bounds'],
                    'whitespace_percentage' => $analysis['whitespace_percentage'],
                    'suggested_crop' => $analysis['suggested_crop'],
                    'optimal_padding' => $analysis['optimal_padding'],
                    'aspect_ratio' => $analysis['aspect_ratio'],
                ];
            }

        } catch (\Exception $e) {
            Log::error('Cropping analysis failed', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Get real file system path
     */
    private function getRealPath(string $path): string
    {
        if (Storage::exists($path)) {
            return Storage::path($path);
        }

        if (file_exists($path)) {
            return $path;
        }

        $publicPath = storage_path('app/public/'.$path);
        if (file_exists($publicPath)) {
            return $publicPath;
        }

        throw new \Exception("Image file not found: {$path}");
    }

    /**
     * Process multiple images with batch optimization
     */
    public function processMultipleWithCropping(
        array $imagePaths,
        string $preset = 'default'
    ): array {
        $processedPaths = [];

        $presets = [
            'default' => [
                'crop_to_object' => true,
                'padding' => 20,
                'trim_whitespace' => true,
            ],
            'tight' => [
                'crop_to_object' => true,
                'padding' => 5,
                'trim_whitespace' => true,
            ],
            'padded' => [
                'crop_to_object' => true,
                'padding' => 40,
                'add_border' => true,
                'border_size' => 10,
            ],
            'square' => [
                'crop_to_object' => true,
                'square_crop' => true,
                'padding' => 25,
            ],
        ];

        $options = $presets[$preset] ?? $presets['default'];

        foreach ($imagePaths as $index => $imagePath) {
            Log::info("Processing image {$index} with cropping", [
                'path' => $imagePath,
                'preset' => $preset,
            ]);

            $processedPath = $this->removeBackgroundAndCrop($imagePath, $options);
            $processedPaths[] = $processedPath;

            // Add delay for system stability
            if ($index < count($imagePaths) - 1) {
                usleep(150000); // 150ms delay
            }
        }

        return $processedPaths;
    }

    /**
     * Check if U2Net is available
     */
    public function isAvailable(): bool
    {
        try {
            // Test if Python and U2Net script exist
            $result = Process::run('python3 --version');

            return $result->successful();
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
                // Skip if file not found
                continue;
            }
        }

        foreach ($processedPaths as $path) {
            try {
                $realPath = $this->getRealPath($path);
                $processedSize += filesize($realPath);
            } catch (\Exception $e) {
                // Skip if file not found
                continue;
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
