<?php

namespace App\Services\Inventory;

use App\Models\Catalog\Product;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductExportService
{
    /**
     * Export products to CSV or Excel.
     */
    public function exportProducts(array $productIds, string $format = 'csv', array $columns = []): StreamedResponse|array
    {
        $products = Product::whereIn('id', $productIds)
            ->with(['category', 'brand'])
            ->get();

        if ($format === 'csv') {
            return $this->exportToCsv($products, $columns);
        }

        // For xlsx, return data that can be processed by frontend
        return [
            'format' => 'xlsx',
            'data' => $this->prepareExportData($products, $columns),
            'filename' => $this->generateFilename('xlsx'),
        ];
    }

    /**
     * Export to CSV.
     */
    protected function exportToCsv($products, array $columns = []): StreamedResponse
    {
        $filename = $this->generateFilename('csv');
        $headers = $this->getHeaders($columns);
        $exportData = $this->prepareExportData($products, $columns);

        return Response::streamDownload(function () use ($headers, $exportData) {
            $handle = fopen('php://output', 'w');

            // Add BOM for Excel UTF-8 compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write headers
            fputcsv($handle, $headers);

            // Write data rows
            foreach ($exportData as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Prepare export data.
     */
    protected function prepareExportData($products, array $columns = []): array
    {
        $data = [];
        $useColumns = ! empty($columns) ? $columns : $this->getDefaultColumns();

        foreach ($products as $product) {
            $row = [];

            foreach ($useColumns as $column) {
                $row[] = $this->getColumnValue($product, $column);
            }

            $data[] = $row;
        }

        return $data;
    }

    /**
     * Get column value for a product.
     */
    protected function getColumnValue(Product $product, string $column): mixed
    {
        return match ($column) {
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'status' => $product->status,
            'category' => $product->category?->name ?? '',
            'brand' => $product->brand?->name ?? '',
            'condition' => $this->formatCondition($product->condition),
            'cost_price' => $product->cost_price,
            'selling_price' => $product->selling_price,
            'msrp' => $product->msrp,
            'stock_quantity' => $product->stock_quantity ?? 0,
            'low_stock_threshold' => $product->low_stock_threshold ?? 5,
            'manage_stock' => $product->manage_stock ? 'Yes' : 'No',
            'weight' => $product->weight,
            'dimensions' => $this->formatDimensions($product),
            'short_description' => $product->short_description,
            'description' => strip_tags($product->description ?? ''),
            'created_at' => $product->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $product->updated_at?->format('Y-m-d H:i:s'),
            'total_sales' => $product->total_sales ?? 0,
            'view_count' => $product->view_count ?? 0,
            default => '',
        };
    }

    /**
     * Get headers for export.
     */
    protected function getHeaders(array $columns = []): array
    {
        $useColumns = ! empty($columns) ? $columns : $this->getDefaultColumns();

        $headerLabels = [
            'id' => 'ID',
            'name' => 'Product Name',
            'sku' => 'SKU',
            'barcode' => 'Barcode',
            'status' => 'Status',
            'category' => 'Category',
            'brand' => 'Brand',
            'condition' => 'Condition',
            'cost_price' => 'Cost Price',
            'selling_price' => 'Selling Price',
            'msrp' => 'MSRP',
            'stock_quantity' => 'Stock Quantity',
            'low_stock_threshold' => 'Low Stock Threshold',
            'manage_stock' => 'Manage Stock',
            'weight' => 'Weight',
            'dimensions' => 'Dimensions (L×W×H)',
            'short_description' => 'Short Description',
            'description' => 'Description',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'total_sales' => 'Total Sales',
            'view_count' => 'View Count',
        ];

        return array_map(fn ($col) => $headerLabels[$col] ?? ucfirst($col), $useColumns);
    }

    /**
     * Get default columns for export.
     */
    protected function getDefaultColumns(): array
    {
        return [
            'sku',
            'name',
            'category',
            'brand',
            'condition',
            'cost_price',
            'selling_price',
            'stock_quantity',
            'status',
            'created_at',
        ];
    }

    /**
     * Format condition for display.
     */
    protected function formatCondition(?string $condition): string
    {
        if (! $condition) {
            return '';
        }

        return match ($condition) {
            'new' => 'New',
            'like_new' => 'Like New',
            'excellent' => 'Excellent',
            'very_good' => 'Very Good',
            'good' => 'Good',
            'acceptable' => 'Acceptable',
            'for_parts' => 'For Parts',
            'refurbished' => 'Refurbished',
            default => ucfirst(str_replace('_', ' ', $condition)),
        };
    }

    /**
     * Format dimensions.
     */
    protected function formatDimensions(Product $product): string
    {
        $parts = array_filter([
            $product->length,
            $product->width,
            $product->height,
        ]);

        if (empty($parts)) {
            return '';
        }

        return implode(' × ', $parts);
    }

    /**
     * Generate filename.
     */
    protected function generateFilename(string $extension): string
    {
        $date = now()->format('Y-m-d_His');

        return "products_export_{$date}.{$extension}";
    }
}
