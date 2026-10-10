<?php

namespace App\Services;

use App\Models\Printing\PrintJob;

class PrinterConnectionService
{
    /**
     * Send print job to printer
     */
    public function sendToPrinter(PrintJob $printJob): bool
    {
        $printer = $printJob->printer;

        switch ($printer->driver) {
            case 'zebra_zpl':
                return $this->printZebraZpl($printJob);
            case 'dymo':
                return $this->printDymo($printJob);
            case 'pdf':
                return $this->generatePdf($printJob);
            default:
                return $this->printGeneric($printJob);
        }
    }

    /**
     * Print to Zebra printer using ZPL
     */
    protected function printZebraZpl(PrintJob $printJob): bool
    {
        $zpl = $this->generateZpl($printJob);

        if ($printJob->printer->printer_type === 'network') {
            return $this->sendToNetworkPrinter($printJob->printer->connection_string, $zpl);
        }

        return $this->sendToLocalPrinter($printJob->printer->connection_string, $zpl);
    }

    /**
     * Generate ZPL code for label
     */
    protected function generateZpl(PrintJob $printJob): string
    {
        $data = $printJob->label_data;
        $template = $printJob->template;

        $zpl = "^XA\n"; // Start format

        // Add QR Code
        if (! empty($data['qr_code'])) {
            $zpl .= "^FO50,50^BQN,2,4^FDQA,{$data['barcode_data']}^FS\n";
        }

        // Add SKU
        $zpl .= "^FO200,50^A0N,30,30^FD{$data['sku']}^FS\n";

        // Add Product Name
        $productName = substr($data['product_name'], 0, 20); // Truncate if too long
        $zpl .= "^FO200,100^A0N,20,20^FD{$productName}^FS\n";

        // Add Location if available
        if (! empty($data['location'])) {
            $zpl .= "^FO200,130^A0N,15,15^FDLoc: {$data['location']}^FS\n";
        }

        // Add Date
        $zpl .= "^FO200,160^A0N,15,15^FD{$data['date_created']}^FS\n";

        $zpl .= "^XZ\n"; // End format

        return $zpl;
    }

    /**
     * Send to network printer
     */
    protected function sendToNetworkPrinter(string $printerIp, string $data): bool
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if (! $socket || ! socket_connect($socket, $printerIp, 9100)) {
            return false;
        }

        $result = socket_write($socket, $data);
        socket_close($socket);

        return $result !== false;
    }

    /**
     * Send to local printer (USB/Serial)
     */
    protected function sendToLocalPrinter(string $devicePath, string $data): bool
    {
        // For USB printers on Linux
        return file_put_contents($devicePath, $data) !== false;
    }

    /**
     * Generate PDF for manual printing
     */
    protected function generatePdf(PrintJob $printJob): bool
    {
        // Use Laravel PDF generation (DomPDF/mPDF)
        $pdf = app('dompdf.wrapper');
        $html = view('labels.pdf-template', [
            'data' => $printJob->label_data,
            'template' => $printJob->template,
            'quantity' => $printJob->quantity,
        ])->render();

        $pdf->loadHTML($html);
        $filename = storage_path("app/print-jobs/job-{$printJob->id}.pdf");
        $pdf->save($filename);

        // Could also automatically open PDF or send to default printer
        return true;
    }
}
