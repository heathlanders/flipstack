<?php

namespace App\Services;

use App\Jobs\ProcessPrintJob;
use App\Models\Catalog\Product;
use App\Models\Printing\LabelTemplate;
use App\Models\Printing\OrganizationPrinter;
use App\Models\Printing\PrintJob;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class LabelPrintingService
{
    /**
     * Automatically print label when SKU is created
     */
    public function printOnSkuCreation(Product $product): bool
    {
        if (! $product->auto_print_labels || ! $product->organization->hasActivePrinter()) {
            return false;
        }

        $template = $product->organization->getDefaultLabelTemplate();
        $printer = $product->organization->getDefaultPrinter();

        if (! $template || ! $printer) {
            return false;
        }

        return $this->queuePrintJob([
            'organization_id' => $product->organization_id,
            'printer_id' => $printer->id,
            'template_id' => $template->id,
            'product_id' => $product->id,
            'job_type' => 'auto_sku',
            'quantity' => 1,
            'label_data' => $this->generateLabelData($product),
        ]);
    }

    /**
     * Print label on demand
     */
    public function printOnDemand(Product $product, int $quantity = 1, ?int $templateId = null, ?int $printerId = null): bool
    {
        $template = $templateId ? LabelTemplate::find($templateId) : $product->organization->getDefaultLabelTemplate();
        $printer = $printerId ? OrganizationPrinter::find($printerId) : $product->organization->getDefaultPrinter();

        return $this->queuePrintJob([
            'organization_id' => $product->organization_id,
            'printer_id' => $printer->id,
            'template_id' => $template->id,
            'product_id' => $product->id,
            'job_type' => 'on_demand',
            'quantity' => $quantity,
            'label_data' => $this->generateLabelData($product),
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Batch print multiple products
     */
    public function printBatch(array $productIds, int $quantity = 1, ?int $templateId = null, ?int $printerId = null): array
    {
        $results = [];

        foreach ($productIds as $productId) {
            $product = Product::find($productId);
            if ($product) {
                $results[] = $this->printOnDemand($product, $quantity, $templateId, $printerId);
            }
        }

        return $results;
    }

    /**
     * Generate label data for product
     */
    protected function generateLabelData(Product $product): array
    {
        return [
            'sku' => $product->internal_sku ?: $product->sku,
            'product_name' => $product->name,
            'qr_code' => $this->generateQrCode($product),
            'location' => $this->resolveProductLocationLabel($product),
            'organization' => $product->organization->name,
            'date_created' => $product->created_at->format('Y-m-d'),
            'barcode_data' => $product->internal_sku ?: $product->sku,
        ];
    }

    /**
     * Resolve a human-readable bin/location label from inventory_levels (the source of truth).
     */
    protected function resolveProductLocationLabel(Product $product): ?string
    {
        $level = \App\Models\Inventory\InventoryLevel::with('bin.zone', 'location')
            ->where('product_id', $product->id)
            ->orderByDesc('quantity_on_hand')
            ->first();

        if (! $level) {
            return null;
        }

        if ($level->bin) {
            $zoneCode = $level->bin->zone?->code;

            return $zoneCode ? $zoneCode.'-'.$level->bin->code : $level->bin->code;
        }

        return $level->location?->code;
    }

    /**
     * Generate QR code for product
     */
    protected function generateQrCode(Product $product): string
    {
        $qrData = [
            'sku' => $product->internal_sku ?: $product->sku,
            'id' => $product->id,
            'org' => $product->organization_id,
            'url' => route('products.show', $product->id),
        ];

        return QrCode::size(100)
            ->format('svg')
            ->generate(json_encode($qrData));
    }

    /**
     * Queue a print job
     */
    protected function queuePrintJob(array $data): bool
    {
        $printJob = PrintJob::create($data);
        ProcessPrintJob::dispatch($printJob);

        return true;
    }
}
