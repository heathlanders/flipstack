<?php

namespace App\Services;

class PrinterDiscoveryService
{
    /**
     * Discover network printers
     */
    public function discoverNetworkPrinters(string $subnet = '192.168.1'): array
    {
        $printers = [];

        // Common printer ports to check
        $ports = [9100, 515, 631, 9101, 9102];

        for ($i = 1; $i <= 254; $i++) {
            $ip = "{$subnet}.{$i}";

            foreach ($ports as $port) {
                if ($this->isPortOpen($ip, $port, 1)) {
                    $printerInfo = $this->identifyPrinter($ip, $port);
                    if ($printerInfo) {
                        $printers[] = $printerInfo;
                        break; // Found printer on this IP
                    }
                }
            }
        }

        return $printers;
    }

    /**
     * Discover USB printers
     */
    public function discoverUsbPrinters(): array
    {
        $printers = [];

        if (PHP_OS_FAMILY === 'Linux') {
            // Check /dev/usb/lp* and /dev/ttyUSB*
            $devices = array_merge(
                glob('/dev/usb/lp*'),
                glob('/dev/ttyUSB*'),
                glob('/dev/ttyACM*')
            );

            foreach ($devices as $device) {
                if (is_writable($device)) {
                    $printers[] = [
                        'name' => basename($device),
                        'connection_string' => $device,
                        'type' => 'usb',
                        'driver' => 'generic_escp',
                    ];
                }
            }
        }

        // Also check CUPS printers
        $printers = array_merge($printers, $this->discoverCupsPrinters());

        return $printers;
    }

    /**
     * Get CUPS printers
     */
    private function discoverCupsPrinters(): array
    {
        $printers = [];

        $output = shell_exec('lpstat -p 2>/dev/null');
        if ($output) {
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                if (preg_match('/printer (\S+)/', $line, $matches)) {
                    $printers[] = [
                        'name' => $matches[1],
                        'connection_string' => $matches[1],
                        'type' => 'cups',
                        'driver' => 'generic_escp',
                    ];
                }
            }
        }

        return $printers;
    }

    /**
     * Check if port is open
     */
    private function isPortOpen(string $host, int $port, int $timeout = 3): bool
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($connection) {
            fclose($connection);

            return true;
        }

        return false;
    }

    /**
     * Identify printer brand/model
     */
    private function identifyPrinter(string $ip, int $port): ?array
    {
        $socket = @fsockopen($ip, $port, $errno, $errstr, 2);
        if (! $socket) {
            return null;
        }

        // Send printer status query
        fwrite($socket, "\x1B\x2A\x73\x50\x52\x4E"); // ESC *sPRN
        $response = fread($socket, 1024);
        fclose($socket);

        $name = "Network Printer ({$ip})";
        $driver = 'generic_escp';

        // Detect printer brand from response
        if (stripos($response, 'zebra') !== false) {
            $name = "Zebra Printer ({$ip})";
            $driver = 'zebra_zpl';
        } elseif (stripos($response, 'dymo') !== false) {
            $name = "Dymo Printer ({$ip})";
            $driver = 'dymo_label';
        } elseif (stripos($response, 'brother') !== false) {
            $name = "Brother Printer ({$ip})";
            $driver = 'brother_ptouch';
        }

        return [
            'name' => $name,
            'connection_string' => $ip,
            'type' => 'network',
            'driver' => $driver,
            'port' => $port,
        ];
    }
}
