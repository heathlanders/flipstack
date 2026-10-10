<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ExtensionTokenController extends Controller
{
    /**
     * Generate a Sanctum token for the Chrome extension.
     */
    public function generateToken(Request $request)
    {
        $user = $request->user();

        // Revoke any existing extension tokens first
        $user->tokens()->where('name', 'like', 'extension-%')->delete();

        $token = $user->createToken('extension-'.Str::random(8), ['extension:research']);

        return response()->json([
            'token' => $token->plainTextToken,
        ]);
    }

    /**
     * Revoke all extension tokens for the user.
     */
    public function revokeTokens(Request $request)
    {
        $user = $request->user();
        $deleted = $user->tokens()->where('name', 'like', 'extension-%')->delete();

        return response()->json([
            'revoked' => $deleted,
        ]);
    }

    /**
     * Download the Chrome extension as a zip file.
     */
    public function download(): StreamedResponse
    {
        $extensionPath = base_path('ebay-research-data-extractor');

        return response()->streamDownload(function () use ($extensionPath) {
            $zip = new ZipArchive;
            $tmpFile = tempnam(sys_get_temp_dir(), 'ext_');
            $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($extensionPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if ($file->isFile()) {
                    $relativePath = substr($file->getRealPath(), strlen($extensionPath) + 1);
                    $zip->addFile($file->getRealPath(), 'ebay-research-data-extractor/'.$relativePath);
                }
            }

            $zip->close();
            readfile($tmpFile);
            unlink($tmpFile);
        }, 'ebay-research-chrome-extension.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }
}
