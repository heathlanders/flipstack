<?php

namespace App\Http\Controllers\Printing;

use App\Http\Controllers\Controller;
use App\Models\Printing\OrganizationPrinter;
use App\Services\LabelTemplateService;
use App\Services\PrinterManagementService;
use Illuminate\Http\Request;

class PrinterManagementController extends Controller
{
    public function discover(PrinterManagementService $printerService)
    {
        $discovered = $printerService->discoverPrinters();

        return response()->json([
            'printers' => $discovered,
            'count' => count($discovered),
        ]);
    }

    public function addDiscovered(Request $request, PrinterManagementService $printerService)
    {
        $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:network,usb,cups',
            'connection_string' => 'required|string',
            'driver' => 'required|string',
        ]);

        $printer = $printerService->addDiscoveredPrinter(
            auth()->user()->organization,
            $request->only(['name', 'type', 'connection_string', 'driver'])
        );

        return response()->json(['printer' => $printer]);
    }

    public function test(OrganizationPrinter $printer, PrinterManagementService $printerService)
    {
        $success = $printerService->testPrinter($printer);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Printer connection successful' : 'Printer connection failed',
        ]);
    }

    public function createDefaultTemplates(LabelTemplateService $templateService)
    {
        $templateService->createDefaultTemplatesForOrganization(auth()->user()->organization);

        return response()->json(['message' => 'Default templates created successfully']);
    }
}
