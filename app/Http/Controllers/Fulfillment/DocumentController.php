<?php

namespace App\Http\Controllers\Fulfillment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function download(Request $request, string $type, string $filename)
    {
        $allowedTypes = ['packing-slip', 'pick-list'];

        if (! in_array($type, $allowedTypes)) {
            abort(404);
        }

        $directory = match ($type) {
            'packing-slip' => 'fulfillment/packing-slips',
            'pick-list' => 'fulfillment/pick-lists',
        };

        $path = "{$directory}/{$filename}";

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->download(
            Storage::disk('local')->path($path),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}
