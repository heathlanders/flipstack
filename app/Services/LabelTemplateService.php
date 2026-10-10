<?php

namespace App\Services;

class LabelTemplateService
{
    /**
     * Get predefined templates
     */
    public function getDefaultTemplates(): array
    {
        return [
            'small_product' => [
                'name' => 'Small Product Label (50x30mm)',
                'description' => 'Perfect for small items',
                'width_mm' => 50,
                'height_mm' => 30,
                'elements' => [
                    ['type' => 'qr_code', 'field' => 'qr_data', 'x' => 2, 'y' => 2, 'size' => 3],
                    ['type' => 'text', 'field' => 'sku', 'x' => 20, 'y' => 2, 'size' => 12, 'font' => 'bold'],
                    ['type' => 'text', 'field' => 'product_name', 'x' => 20, 'y' => 12, 'size' => 8],
                    ['type' => 'text', 'field' => 'location', 'x' => 20, 'y' => 20, 'size' => 6],
                ],
            ],

            'medium_product' => [
                'name' => 'Medium Product Label (75x50mm)',
                'description' => 'Standard product label with barcode',
                'width_mm' => 75,
                'height_mm' => 50,
                'elements' => [
                    ['type' => 'qr_code', 'field' => 'qr_data', 'x' => 2, 'y' => 2, 'size' => 4],
                    ['type' => 'text', 'field' => 'sku', 'x' => 25, 'y' => 2, 'size' => 14, 'font' => 'bold'],
                    ['type' => 'text', 'field' => 'product_name', 'x' => 25, 'y' => 15, 'size' => 10],
                    ['type' => 'text', 'field' => 'organization', 'x' => 25, 'y' => 25, 'size' => 8],
                    ['type' => 'text', 'field' => 'location', 'x' => 25, 'y' => 32, 'size' => 8],
                    ['type' => 'text', 'field' => 'date_created', 'x' => 25, 'y' => 40, 'size' => 6],
                ],
            ],

            'warehouse_shelf' => [
                'name' => 'Warehouse Shelf Label (100x25mm)',
                'description' => 'For labeling warehouse locations',
                'width_mm' => 100,
                'height_mm' => 25,
                'elements' => [
                    ['type' => 'text', 'field' => 'location', 'x' => 2, 'y' => 2, 'size' => 20, 'font' => 'bold'],
                    ['type' => 'qr_code', 'field' => 'location_qr', 'x' => 75, 'y' => 2, 'size' => 3],
                ],
            ],

            'shipping_label' => [
                'name' => 'Large Shipping Label (100x75mm)',
                'description' => 'Comprehensive product info for shipping',
                'width_mm' => 100,
                'height_mm' => 75,
                'elements' => [
                    ['type' => 'qr_code', 'field' => 'qr_data', 'x' => 2, 'y' => 2, 'size' => 5],
                    ['type' => 'text', 'field' => 'sku', 'x' => 35, 'y' => 2, 'size' => 16, 'font' => 'bold'],
                    ['type' => 'text', 'field' => 'product_name', 'x' => 35, 'y' => 18, 'size' => 12],
                    ['type' => 'text', 'field' => 'organization', 'x' => 35, 'y' => 30, 'size' => 10],
                    ['type' => 'barcode', 'field' => 'sku', 'x' => 35, 'y' => 40, 'height' => 20],
                    ['type' => 'text', 'field' => 'location', 'x' => 2, 'y' => 65, 'size' => 8],
                    ['type' => 'text', 'field' => 'date_created', 'x' => 65, 'y' => 65, 'size' => 8],
                ],
            ],
        ];
    }

    /**
     * Create templates for organization
     */
    public function createDefaultTemplatesForOrganization(Organization $organization): void
    {
        foreach ($this->getDefaultTemplates() as $key => $template) {
            LabelTemplate::create([
                'organization_id' => $organization->id,
                'name' => $template['name'],
                'description' => $template['description'],
                'width_mm' => $template['width_mm'],
                'height_mm' => $template['height_mm'],
                'template_data' => $template,
                'is_active' => true,
                'is_default' => $key === 'medium_product', // Set medium as default
            ]);
        }
    }
}
