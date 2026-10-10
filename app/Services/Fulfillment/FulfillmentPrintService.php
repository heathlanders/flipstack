<?php

namespace App\Services\Fulfillment;

use App\Models\Fulfillment\FulfillmentLog;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\Shipment;
use App\Models\Inventory\InventoryLocation;
use App\Models\User\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FulfillmentPrintService
{
    protected FulfillmentAuditService $auditService;

    public function __construct(FulfillmentAuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Generate and optionally auto-print a packing slip for an order.
     */
    public function generatePackingSlip(Order $order, User $actor, bool $autoPrint = true): array
    {
        $order->load(['items', 'shipments']);

        $html = $this->renderPackingSlipHtml($order);
        $filename = "packing-slip-{$order->marketplace_order_id}-".now()->format('Ymd-His').'.pdf';
        $path = "fulfillment/packing-slips/{$filename}";

        $pdf = app('dompdf.wrapper');
        $pdf->loadHTML($html);
        $pdf->setPaper([0, 0, 612, 792]); // Letter size

        Storage::disk('local')->put($path, $pdf->output());

        // Auto-print if location has a packing slip printer configured
        if ($autoPrint && $order->location) {
            $this->autoPrintDocument($order->location, 'packing_slip', $path);
        }

        $this->auditService->log(
            FulfillmentLog::ACTION_PACKING_SLIP_PRINTED,
            $order,
            $actor,
            ['filename' => $filename]
        );

        return [
            'path' => $path,
            'filename' => $filename,
            'url' => route('fulfillment.documents.download', ['type' => 'packing-slip', 'filename' => $filename]),
        ];
    }

    /**
     * Generate and optionally auto-print a pick list for a location.
     */
    public function generatePickList(InventoryLocation $location, Collection $orders, User $actor, bool $autoPrint = true): array
    {
        $html = $this->renderPickListHtml($location, $orders);
        $filename = "pick-list-{$location->code}-".now()->format('Ymd-His').'.pdf';
        $path = "fulfillment/pick-lists/{$filename}";

        $pdf = app('dompdf.wrapper');
        $pdf->loadHTML($html);
        $pdf->setPaper([0, 0, 612, 792]); // Letter size

        Storage::disk('local')->put($path, $pdf->output());

        if ($autoPrint) {
            $this->autoPrintDocument($location, 'pick_list', $path);
        }

        $this->auditService->log(
            FulfillmentLog::ACTION_PICK_LIST_PRINTED,
            $location,
            $actor,
            ['filename' => $filename, 'order_count' => $orders->count()],
            null
        );

        return [
            'path' => $path,
            'filename' => $filename,
            'url' => route('fulfillment.documents.download', ['type' => 'pick-list', 'filename' => $filename]),
        ];
    }

    /**
     * Auto-print a shipping label PDF to the location's configured printer.
     */
    public function autoPrintShippingLabel(Shipment $shipment, User $actor): bool
    {
        $location = $shipment->location;

        if (! $location || ! $location->auto_print_shipping_label || ! $location->shipping_label_printer_id) {
            return false;
        }

        if (! $shipment->label_url) {
            return false;
        }

        $printed = $this->sendToPrinter($location->shipping_label_printer_id, $shipment->label_url, 'shipping_label');

        if ($printed) {
            $this->auditService->log(
                FulfillmentLog::ACTION_LABEL_PRINTED,
                $shipment,
                $actor,
                ['printer_id' => $location->shipping_label_printer_id, 'tracking' => $shipment->tracking_number],
                $shipment->order_id
            );
        }

        return $printed;
    }

    /**
     * Batch print shipping labels for multiple shipments at a location.
     */
    public function batchPrintShippingLabels(Collection $shipments, User $actor): array
    {
        $results = ['printed' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($shipments as $shipment) {
            if (! $shipment->label_url) {
                $results['skipped']++;

                continue;
            }

            $printed = $this->autoPrintShippingLabel($shipment, $actor);
            $printed ? $results['printed']++ : $results['failed']++;
        }

        return $results;
    }

    protected function autoPrintDocument(InventoryLocation $location, string $documentType, string $filePath): bool
    {
        $printerField = match ($documentType) {
            'packing_slip' => 'packing_slip_printer_id',
            'pick_list' => 'pick_list_printer_id',
            'shipping_label' => 'shipping_label_printer_id',
            default => null,
        };

        $autoField = match ($documentType) {
            'packing_slip' => 'auto_print_packing_slip',
            'pick_list' => 'auto_print_pick_list',
            'shipping_label' => 'auto_print_shipping_label',
            default => null,
        };

        if (! $printerField || ! $autoField) {
            return false;
        }

        if (! $location->$autoField || ! $location->$printerField) {
            return false;
        }

        $fullPath = Storage::disk('local')->path($filePath);

        return $this->sendToPrinter($location->$printerField, $fullPath, $documentType);
    }

    protected function sendToPrinter(int $printerId, string $filePath, string $jobType): bool
    {
        try {
            // Use the existing printer infrastructure via the OrganizationPrinter model
            $printer = \App\Models\Printing\OrganizationPrinter::find($printerId);

            if (! $printer || ! $printer->is_active) {
                return false;
            }

            // For PDF-compatible printers, download/read the file and send
            $connectionService = new \App\Services\PrinterConnectionService;

            // Create a synthetic print job for the connection service
            $printJob = new \stdClass;
            $printJob->printer = $printer;
            $printJob->label_data = [
                'file_path' => $filePath,
                'job_type' => $jobType,
            ];

            // For thermal label printers with shipping labels, send the PDF directly
            if ($printer->printer_type === 'network' && $printer->connection_string) {
                $content = is_file($filePath) ? file_get_contents($filePath) : $this->downloadFile($filePath);
                if ($content) {
                    return $this->sendToNetworkPrinter($printer->connection_string, $content);
                }
            }

            return false;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Auto-print failed', [
                'printer_id' => $printerId,
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function sendToNetworkPrinter(string $connectionString, string $data): bool
    {
        try {
            [$host, $port] = explode(':', $connectionString.':9100');
            $socket = @fsockopen($host, (int) $port, $errno, $errstr, 5);

            if (! $socket) {
                return false;
            }

            fwrite($socket, $data);
            fclose($socket);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function downloadFile(string $url): ?string
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function renderPackingSlipHtml(Order $order): string
    {
        $items = $order->items->where('pick_status', '!=', 'short');
        $organization = $order->organization;

        $itemsHtml = '';
        foreach ($items as $index => $item) {
            $itemsHtml .= "<tr>
                <td style='padding:6px 8px;border-bottom:1px solid #e5e7eb;'>".($index + 1)."</td>
                <td style='padding:6px 8px;border-bottom:1px solid #e5e7eb;'>".e($item->sku ?? 'N/A')."</td>
                <td style='padding:6px 8px;border-bottom:1px solid #e5e7eb;'>".e($item->title)."</td>
                <td style='padding:6px 8px;border-bottom:1px solid #e5e7eb;text-align:center;'>".$item->quantity."</td>
                <td style='padding:6px 8px;border-bottom:1px solid #e5e7eb;'>".e($item->condition ?? '').'</td>
            </tr>';
        }

        return "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'><title>Packing Slip</title></head>
<body style='font-family:Arial,sans-serif;font-size:12px;margin:20px;'>
    <div style='display:flex;justify-content:space-between;margin-bottom:20px;'>
        <div>
            <h2 style='margin:0;font-size:18px;'>".e($organization->name)."</h2>
            <p style='margin:4px 0;color:#6b7280;'>Packing Slip</p>
        </div>
        <div style='text-align:right;'>
            <p style='margin:2px 0;'><strong>Order:</strong> ".e($order->marketplace_order_id)."</p>
            <p style='margin:2px 0;'><strong>Date:</strong> ".$order->created_at->format('M d, Y')."</p>
            <p style='margin:2px 0;'><strong>Items:</strong> ".$items->sum('quantity')."</p>
        </div>
    </div>

    <div style='margin-bottom:20px;padding:10px;background:#f9fafb;border:1px solid #e5e7eb;'>
        <strong>Ship To:</strong><br>
        ".e($order->shipping_address_name).'<br>
        '.e($order->shipping_address_line1).'<br>
        '.($order->shipping_address_line2 ? e($order->shipping_address_line2).'<br>' : '').'
        '.e($order->shipping_city).', '.e($order->shipping_state).' '.e($order->shipping_postal_code).'<br>
        '.($order->shipping_country !== 'US' ? e($order->shipping_country) : '')."
    </div>

    <table style='width:100%;border-collapse:collapse;border:1px solid #e5e7eb;'>
        <thead>
            <tr style='background:#f3f4f6;'>
                <th style='padding:8px;text-align:left;border-bottom:2px solid #d1d5db;width:30px;'>#</th>
                <th style='padding:8px;text-align:left;border-bottom:2px solid #d1d5db;'>SKU</th>
                <th style='padding:8px;text-align:left;border-bottom:2px solid #d1d5db;'>Item</th>
                <th style='padding:8px;text-align:center;border-bottom:2px solid #d1d5db;width:50px;'>Qty</th>
                <th style='padding:8px;text-align:left;border-bottom:2px solid #d1d5db;'>Condition</th>
            </tr>
        </thead>
        <tbody>{$itemsHtml}</tbody>
    </table>

    ".($order->order_notes ? "<div style='margin-top:15px;padding:10px;background:#fffbeb;border:1px solid #fcd34d;'><strong>Notes:</strong> ".e($order->order_notes).'</div>' : '')."

    <div style='margin-top:30px;text-align:center;color:#9ca3af;font-size:10px;'>
        Thank you for your purchase!
    </div>
</body>
</html>";
    }

    protected function renderPickListHtml(InventoryLocation $location, Collection $orders): string
    {
        $allItems = collect();
        foreach ($orders as $order) {
            foreach ($order->items->where('pick_status', 'pending') as $item) {
                $allItems->push([
                    'order_id' => $order->marketplace_order_id,
                    'priority' => $order->priority,
                    'sku' => $item->sku ?? 'N/A',
                    'title' => $item->title,
                    'quantity' => $item->quantity,
                    'location_code' => $this->resolvePickBinLabel($item, $order->location_id) ?? 'Unassigned',
                    'order_item_id' => $item->id,
                ]);
            }
        }

        // Sort by location code for efficient picking
        $allItems = $allItems->sortBy('location_code');

        $itemsHtml = '';
        foreach ($allItems as $index => $item) {
            $priorityBadge = match ($item['priority']) {
                'urgent' => '<span style="color:#dc2626;font-weight:bold;">[URGENT]</span> ',
                'high' => '<span style="color:#f59e0b;font-weight:bold;">[HIGH]</span> ',
                default => '',
            };
            $itemsHtml .= "<tr>
                <td style='padding:6px 8px;border-bottom:1px solid #e5e7eb;width:20px;'>
                    <span style='display:inline-block;width:14px;height:14px;border:1px solid #6b7280;'></span>
                </td>
                <td style='padding:6px 8px;border-bottom:1px solid #e5e7eb;font-family:monospace;'>".e($item['location_code'])."</td>
                <td style='padding:6px 8px;border-bottom:1px solid #e5e7eb;font-family:monospace;'>".e($item['sku'])."</td>
                <td style='padding:6px 8px;border-bottom:1px solid #e5e7eb;'>{$priorityBadge}".e(Str::limit($item['title'], 40))."</td>
                <td style='padding:6px 8px;border-bottom:1px solid #e5e7eb;text-align:center;font-weight:bold;'>".$item['quantity']."</td>
                <td style='padding:6px 8px;border-bottom:1px solid #e5e7eb;font-family:monospace;font-size:10px;'>".e(Str::limit($item['order_id'], 20)).'</td>
            </tr>';
        }

        return "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'><title>Pick List</title></head>
<body style='font-family:Arial,sans-serif;font-size:11px;margin:20px;'>
    <div style='display:flex;justify-content:space-between;margin-bottom:15px;'>
        <div>
            <h2 style='margin:0;font-size:16px;'>Pick List</h2>
            <p style='margin:4px 0;color:#6b7280;'>".e($location->name).' ('.e($location->code).")</p>
        </div>
        <div style='text-align:right;'>
            <p style='margin:2px 0;'><strong>Generated:</strong> ".now()->format('M d, Y g:i A')."</p>
            <p style='margin:2px 0;'><strong>Orders:</strong> ".$orders->count()."</p>
            <p style='margin:2px 0;'><strong>Items:</strong> ".$allItems->sum('quantity')."</p>
        </div>
    </div>

    <table style='width:100%;border-collapse:collapse;border:1px solid #e5e7eb;'>
        <thead>
            <tr style='background:#f3f4f6;'>
                <th style='padding:6px 8px;text-align:left;border-bottom:2px solid #d1d5db;width:20px;'></th>
                <th style='padding:6px 8px;text-align:left;border-bottom:2px solid #d1d5db;'>Bin</th>
                <th style='padding:6px 8px;text-align:left;border-bottom:2px solid #d1d5db;'>SKU</th>
                <th style='padding:6px 8px;text-align:left;border-bottom:2px solid #d1d5db;'>Item</th>
                <th style='padding:6px 8px;text-align:center;border-bottom:2px solid #d1d5db;width:40px;'>Qty</th>
                <th style='padding:6px 8px;text-align:left;border-bottom:2px solid #d1d5db;'>Order</th>
            </tr>
        </thead>
        <tbody>{$itemsHtml}</tbody>
    </table>

    <div style='margin-top:20px;'>
        <p style='font-size:10px;color:#6b7280;'>Picker: _________________ &nbsp;&nbsp; Start: _________ &nbsp;&nbsp; End: _________</p>
    </div>
</body>
</html>";
    }

    /**
     * Resolve the bin label for a pick line item.
     * Source of truth: inventory_levels.bin_id at the order's chosen location.
     * Falls back to the InventoryLevel's location code, then to null.
     */
    protected function resolvePickBinLabel($orderItem, ?string $orderLocationId): ?string
    {
        if (! $orderItem->product_id) {
            return null;
        }

        $level = \App\Models\Inventory\InventoryLevel::with('bin.zone', 'location')
            ->where('product_id', $orderItem->product_id)
            ->when($orderLocationId, fn ($q) => $q->where('location_id', $orderLocationId))
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
}
