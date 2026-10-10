<?php

namespace App\Http\Controllers\Printing;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Services\LabelPrintingService;
use Illuminate\Http\Request;

class LabelPrintingController extends Controller
{
    public function __construct(
        private LabelPrintingService $labelService
    ) {}

    /**
     * Print label for single product
     */
    public function printSingle(Request $request, Product $product)
    {
        $request->validate([
            'quantity' => 'integer|min:1|max:100',
            'template_id' => 'nullable|exists:label_templates,id',
            'printer_id' => 'nullable|exists:organization_printers,id',
        ]);

        $success = $this->labelService->printOnDemand(
            $product,
            $request->get('quantity', 1),
            $request->get('template_id'),
            $request->get('printer_id')
        );

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Label print job queued' : 'Failed to queue print job',
        ]);
    }

    /**
     * Batch print labels
     */
    public function printBatch(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'quantity' => 'integer|min:1|max:100',
            'template_id' => 'nullable|exists:label_templates,id',
            'printer_id' => 'nullable|exists:organization_printers,id',
        ]);

        $results = $this->labelService->printBatch(
            $request->product_ids,
            $request->get('quantity', 1),
            $request->get('template_id'),
            $request->get('printer_id')
        );

        return response()->json([
            'success' => ! empty(array_filter($results)),
            'total_jobs' => count($results),
            'successful_jobs' => count(array_filter($results)),
        ]);
    }
}
