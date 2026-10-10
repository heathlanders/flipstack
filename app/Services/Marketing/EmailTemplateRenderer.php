<?php

namespace App\Services\Marketing;

use App\Models\Fulfillment\Order;
use App\Models\Marketing\EmailCampaign;

class EmailTemplateRenderer
{
    /**
     * Replace {{variable}} placeholders in a template string.
     */
    public function render(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{'.$key.'}}', (string) $value, $template);
        }

        return $template;
    }

    /**
     * Return list of supported template variables with descriptions.
     */
    public function getAvailableVariables(): array
    {
        return [
            'customer_name' => 'Full name of the customer',
            'customer_first_name' => 'First name of the customer',
            'customer_email' => 'Email address of the customer',
            'order_number' => 'Order reference number',
            'order_total' => 'Order total amount',
            'order_date' => 'Date the order was placed',
            'product_name' => 'Name of the primary product in the order',
            'product_sku' => 'SKU of the primary product',
            'tracking_url' => 'Shipment tracking URL',
            'tracking_number' => 'Shipment tracking number',
            'store_name' => 'Your store/organization name',
            'unsubscribe_url' => 'Link for the customer to unsubscribe',
        ];
    }

    /**
     * Extract template variables from an order.
     */
    public function buildVariablesForOrder(Order $order): array
    {
        $customer = $order->customer;
        $nameParts = $customer ? explode(' ', $customer->name ?? '', 2) : ['Customer'];

        $primaryItem = $order->items()->first();

        return [
            'customer_name' => $customer->name ?? 'Valued Customer',
            'customer_first_name' => $nameParts[0] ?? 'Valued Customer',
            'customer_email' => $customer->email ?? '',
            'order_number' => $order->order_number ?? $order->marketplace_order_id ?? $order->id,
            'order_total' => number_format((float) ($order->total ?? 0), 2),
            'order_date' => $order->created_at?->format('M j, Y') ?? '',
            'product_name' => $primaryItem?->product?->name ?? $primaryItem?->title ?? 'your item',
            'product_sku' => $primaryItem?->product?->sku ?? $primaryItem?->sku ?? '',
            'tracking_url' => $order->tracking_url ?? '#',
            'tracking_number' => $order->tracking_number ?? '',
            'store_name' => $order->organization?->name ?? 'Our Store',
            'unsubscribe_url' => '#unsubscribe',
        ];
    }

    /**
     * Return rendered subject and body for preview purposes.
     */
    public function preview(EmailCampaign $campaign, ?Order $sampleOrder = null): array
    {
        if ($sampleOrder) {
            $variables = $this->buildVariablesForOrder($sampleOrder);
        } else {
            // Use placeholder values for preview
            $variables = [];
            foreach ($this->getAvailableVariables() as $key => $description) {
                $variables[$key] = '['.$description.']';
            }
        }

        return [
            'subject' => $this->render($campaign->subject_template, $variables),
            'body' => $this->render($campaign->body_template, $variables),
            'variables_used' => $variables,
        ];
    }
}
