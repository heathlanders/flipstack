<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DownloadController extends Controller
{
    public function index()
    {
        $apkPath = 'downloads/flipstack.apk';
        $apkExists = Storage::disk('public')->exists($apkPath);

        $fileSize = null;
        $lastModified = null;

        if ($apkExists) {
            $fileSize = Storage::disk('public')->size($apkPath);
            $lastModified = Storage::disk('public')->lastModified($apkPath);
        }

        return Inertia::render('Download', [
            'apkAvailable' => $apkExists,
            'apkUrl' => $apkExists ? asset('storage/downloads/flipstack.apk') : null,
            'fileSize' => $fileSize ? $this->formatFileSize($fileSize) : null,
            'lastUpdated' => $lastModified ? date('M j, Y', $lastModified) : null,
            'version' => '1.0.0',
        ]);
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }
}
