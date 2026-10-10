<?php

namespace App\Services\Import;

use App\Models\Catalog\Product;

class ProductImportService
{
    protected array $requiredColumns = ['sku', 'name', 'selling_price'];

    protected array $validColumns = ['sku', 'name', 'description', 'short_description', 'category', 'brand', 'condition', 'cost_price', 'selling_price', 'msrp', 'stock_quantity', 'low_stock_threshold', 'weight', 'length', 'width', 'height', 'barcode', 'status'];

    public function validateFile(string $filePath): array
    {
        $result = ['valid' => true, 'row_count' => 0, 'columns' => [], 'preview' => [], 'errors' => [], 'requires_mapping' => false];
        if (! file_exists($filePath)) {
            $result['valid'] = false;
            $result['errors'][] = 'File not found';

            return $result;
        }

        $handle = fopen($filePath, 'r');
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        $header = fgetcsv($handle);
        if (! $header) {
            $result['valid'] = false;
            $result['errors'][] = 'Cannot read header';
            fclose($handle);

            return $result;
        }

        $header = array_map(fn ($h) => trim(strtolower($h)), $header);
        $result['columns'] = $header;
        $matched = array_intersect($header, $this->validColumns);
        if (count($matched) < 2) {
            $result['requires_mapping'] = true;
        }

        $rowCount = 0;
        $preview = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rowCount++;
            if ($rowCount <= 5) {
                $previewRow = [];
                foreach ($header as $i => $col) {
                    $previewRow[$col] = $row[$i] ?? '';
                }
                $preview[] = $previewRow;
            }
        }
        $result['row_count'] = $rowCount;
        $result['preview'] = $preview;
        fclose($handle);

        return $result;
    }

    public function validateWithMapping(string $filePath, array $columnMapping, int $organizationId): array
    {
        $result = ['valid' => true, 'total_rows' => 0, 'valid_rows' => 0, 'error_rows' => 0, 'errors' => [], 'warnings' => [], 'new_count' => 0, 'existing_count' => 0];
        foreach (['sku', 'name', 'selling_price'] as $req) {
            if (empty($columnMapping[$req])) {
                $result['valid'] = false;
                $result['errors'][] = ['row' => 0, 'message' => "Required: $req"];
            }
        }
        if (! $result['valid']) {
            return $result;
        }

        $existingSkus = Product::where('organization_id', $organizationId)->pluck('id', 'sku')->toArray();
        $handle = fopen($filePath, 'r');
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        $header = fgetcsv($handle);
        $columnIndices = [];
        foreach ($columnMapping as $target => $source) {
            $idx = array_search($source, $header);
            if ($idx !== false) {
                $columnIndices[$target] = $idx;
            }
        }

        $rowNum = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            $result['total_rows']++;
            $sku = isset($columnIndices['sku']) ? trim($row[$columnIndices['sku']] ?? '') : '';
            if (empty($sku)) {
                $result['error_rows']++;
                if (count($result['errors']) < 20) {
                    $result['errors'][] = ['row' => $rowNum, 'message' => 'SKU empty'];
                }

                continue;
            }
            $result['valid_rows']++;
            isset($existingSkus[$sku]) ? $result['existing_count']++ : $result['new_count']++;
            if ($rowNum > 1000) {
                break;
            }
        }
        fclose($handle);
        if ($result['error_rows'] > $result['valid_rows']) {
            $result['valid'] = false;
        }

        return $result;
    }

    public function suggestMapping(array $fileColumns): array
    {
        $aliases = [
            'sku' => ['sku', 'product_sku', 'item_sku', 'code'], 'name' => ['name', 'title', 'product_name'],
            'description' => ['description', 'desc'], 'category' => ['category', 'category_name'],
            'brand' => ['brand', 'brand_name', 'manufacturer'], 'cost_price' => ['cost', 'cost_price'],
            'selling_price' => ['price', 'selling_price', 'sale_price'], 'stock_quantity' => ['stock', 'quantity', 'qty'],
            'barcode' => ['barcode', 'upc', 'ean'], 'status' => ['status'],
        ];
        $suggestions = [];
        foreach ($aliases as $target => $names) {
            foreach ($fileColumns as $col) {
                if (in_array(strtolower(trim($col)), $names)) {
                    $suggestions[$target] = $col;
                    break;
                }
            }
        }

        return $suggestions;
    }
}
