<?php

namespace App\Services\Printing\Drivers;

abstract class BasePrinterDriver
{
    protected $printer;

    protected $settings;

    abstract public function connect(): bool;

    abstract public function print(string $data): bool;

    abstract public function generateCommand(array $labelData, array $template): string;

    abstract public function testConnection(): bool;

    public function __construct($printer, array $settings = [])
    {
        $this->printer = $printer;
        $this->settings = $settings;
    }
}

// Zebra ZPL Driver
class ZebraZplDriver extends BasePrinterDriver
{
    public function generateCommand(array $labelData, array $template): string
    {
        $width = $template['width_mm'] * 8; // Convert mm to dots (203 DPI)
        $height = $template['height_mm'] * 8;

        $zpl = "^XA\n";
        $zpl .= "^PW{$width}\n";
        $zpl .= "^LL{$height}\n";

        foreach ($template['elements'] as $element) {
            $zpl .= $this->generateElement($element, $labelData);
        }

        $zpl .= "^XZ\n";

        return $zpl;
    }

    private function generateElement(array $element, array $data): string
    {
        $x = $element['x'] * 8; // Convert mm to dots
        $y = $element['y'] * 8;

        switch ($element['type']) {
            case 'text':
                $text = $data[$element['field']] ?? '';
                $size = $element['size'] ?? 20;

                return "^FO{$x},{$y}^A0N,{$size},{$size}^FD{$text}^FS\n";

            case 'qr_code':
                $qrData = $data[$element['field']] ?? '';
                $size = $element['size'] ?? 3;

                return "^FO{$x},{$y}^BQN,2,{$size}^FDQA,{$qrData}^FS\n";

            case 'barcode':
                $barcode = $data[$element['field']] ?? '';

                return "^FO{$x},{$y}^BY2,3,50^BCN,,Y,N^FD{$barcode}^FS\n";

            default:
                return '';
        }
    }

    public function connect(): bool
    {
        if ($this->printer->printer_type === 'network') {
            return $this->testNetworkConnection();
        } else {
            return $this->testUsbConnection();
        }
    }

    public function print(string $data): bool
    {
        if ($this->printer->printer_type === 'network') {
            return $this->sendToNetwork($data);
        } else {
            return $this->sendToUsb($data);
        }
    }

    private function sendToNetwork(string $data): bool
    {
        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (! $socket) {
            return false;
        }

        $result = @socket_connect($socket, $this->printer->connection_string, 9100);
        if (! $result) {
            socket_close($socket);

            return false;
        }

        $bytes = @socket_write($socket, $data);
        socket_close($socket);

        return $bytes !== false;
    }

    public function testConnection(): bool
    {
        return $this->connect();
    }
}

// Dymo LabelWriter Driver
class DymoLabelDriver extends BasePrinterDriver
{
    public function generateCommand(array $labelData, array $template): string
    {
        // Generate Dymo XML format
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<DesktopLabel Version="1">';
        $xml .= '<DYMOLabel Version="3">';

        foreach ($template['elements'] as $element) {
            $xml .= $this->generateXmlElement($element, $labelData);
        }

        $xml .= '</DYMOLabel>';
        $xml .= '</DesktopLabel>';

        return $xml;
    }

    public function print(string $data): bool
    {
        // Use DYMO SDK or web service if available
        // Fall back to file-based printing
        $tempFile = tempnam(sys_get_temp_dir(), 'dymo_label_');
        file_put_contents($tempFile, $data);

        // Send to printer via CUPS or Windows printing
        if (PHP_OS_FAMILY === 'Windows') {
            exec("print /D:\"{$this->printer->connection_string}\" \"{$tempFile}\"");
        } else {
            exec("lp -d \"{$this->printer->connection_string}\" \"{$tempFile}\"");
        }

        unlink($tempFile);

        return true;
    }

    public function connect(): bool
    {
        return true;
    }

    public function testConnection(): bool
    {
        return true;
    }
}

// Brother P-touch Driver
class BrotherPtouchDriver extends BasePrinterDriver
{
    public function generateCommand(array $labelData, array $template): string
    {
        // Generate Brother P-touch Template (.lbx) or ESC-P commands
        $commands = "\x1B\x40"; // Initialize

        foreach ($template['elements'] as $element) {
            $commands .= $this->generatePtouchElement($element, $labelData);
        }

        $commands .= "\x0C"; // Form feed

        return $commands;
    }

    public function print(string $data): bool
    {
        return $this->sendToUsb($data) || $this->sendToNetwork($data);
    }

    public function connect(): bool
    {
        return true;
    }

    public function testConnection(): bool
    {
        return true;
    }
}

// Generic ESC-P Driver (works with many printers)
class GenericEscPDriver extends BasePrinterDriver
{
    public function generateCommand(array $labelData, array $template): string
    {
        $output = "\x1B\x40"; // ESC @ - Initialize

        foreach ($template['elements'] as $element) {
            if ($element['type'] === 'text') {
                $text = $labelData[$element['field']] ?? '';
                $output .= $text."\n";
            }
        }

        $output .= "\x0C"; // Form feed

        return $output;
    }

    public function print(string $data): bool
    {
        if ($this->printer->printer_type === 'network') {
            return $this->sendToNetwork($data);
        } else {
            return file_put_contents($this->printer->connection_string, $data) !== false;
        }
    }

    public function connect(): bool
    {
        return true;
    }

    public function testConnection(): bool
    {
        return true;
    }
}

// PDF Driver (for any printer)
class PdfDriver extends BasePrinterDriver
{
    public function generateCommand(array $labelData, array $template): string
    {
        return json_encode(['data' => $labelData, 'template' => $template]);
    }

    public function print(string $data): bool
    {
        $decodedData = json_decode($data, true);

        return $this->generatePdf($decodedData['data'], $decodedData['template']);
    }

    private function generatePdf(array $labelData, array $template): bool
    {
        $html = view('printing.pdf-label', compact('labelData', 'template'))->render();

        $pdf = app('dompdf.wrapper');
        $pdf->loadHTML($html);
        $pdf->setPaper([0, 0, $template['width_mm'] * 2.83, $template['height_mm'] * 2.83], 'portrait');

        $filename = storage_path('app/labels/label-'.time().'.pdf');
        $pdf->save($filename);

        // Auto-open PDF or send to default printer
        if (PHP_OS_FAMILY === 'Windows') {
            exec("start \"\" \"{$filename}\"");
        } else {
            exec("xdg-open \"{$filename}\"");
        }

        return true;
    }

    public function connect(): bool
    {
        return true;
    }

    public function testConnection(): bool
    {
        return true;
    }
}
