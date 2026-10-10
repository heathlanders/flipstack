<?php

namespace App\Services;

use App\Models\Organization\Organization;
use App\Models\Printing\OrganizationPrinter;
use App\Services\Printing\Drivers\BasePrinterDriver;
use App\Services\Printing\Drivers\BrotherPtouchDriver;
use App\Services\Printing\Drivers\DymoLabelDriver;
use App\Services\Printing\Drivers\GenericEscPDriver;
use App\Services\Printing\Drivers\PdfDriver;
use App\Services\Printing\Drivers\ZebraZplDriver;

class PrinterManagementService
{
    private $drivers = [
        'zebra_zpl' => ZebraZplDriver::class,
        'dymo_label' => DymoLabelDriver::class,
        'brother_ptouch' => BrotherPtouchDriver::class,
        'generic_escp' => GenericEscPDriver::class,
        'pdf' => PdfDriver::class,
    ];

    public function getDriverInstance(OrganizationPrinter $printer): BasePrinterDriver
    {
        $driverClass = $this->drivers[$printer->driver] ?? GenericEscPDriver::class;

        return new $driverClass($printer, $printer->print_settings);
    }

    public function discoverPrinters(): array
    {
        $discoveryService = new PrinterDiscoveryService;

        $networkPrinters = $discoveryService->discoverNetworkPrinters();
        $usbPrinters = $discoveryService->discoverUsbPrinters();

        return array_merge($networkPrinters, $usbPrinters);
    }

    public function testPrinter(OrganizationPrinter $printer): bool
    {
        $driver = $this->getDriverInstance($printer);

        return $driver->testConnection();
    }

    public function addDiscoveredPrinter(Organization $organization, array $printerData): OrganizationPrinter
    {
        return OrganizationPrinter::create([
            'organization_id' => $organization->id,
            'name' => $printerData['name'],
            'printer_type' => $printerData['type'],
            'connection_string' => $printerData['connection_string'],
            'driver' => $printerData['driver'],
            'print_settings' => [
                'dpi' => 203,
                'speed' => 4,
                'darkness' => 10,
            ],
        ]);
    }
}
