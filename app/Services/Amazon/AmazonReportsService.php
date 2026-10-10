<?php

namespace App\Services\Amazon;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmazonReportsService extends AmazonBaseService
{
    public function requestReport(string $reportType): string
    {
        $response = $this->post('/reports/2021-06-30/reports', [
            'reportType' => $reportType,
            'marketplaceIds' => [$this->marketplaceId],
        ]);

        return $response['reportId'] ?? '';
    }

    public function getReportStatus(string $reportId): array
    {
        return $this->get("/reports/2021-06-30/reports/{$reportId}");
    }

    public function downloadReport(string $reportDocumentId): ?array
    {
        $response = $this->get("/reports/2021-06-30/documents/{$reportDocumentId}");
        $downloadUrl = $response['url'] ?? null;

        if (! $downloadUrl) {
            return null;
        }

        try {
            $content = Http::get($downloadUrl)->body();

            // Parse TSV content
            $lines = explode("\n", trim($content));
            $headers = str_getcsv(array_shift($lines), "\t");
            $data = [];

            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }
                $row = str_getcsv($line, "\t");
                $data[] = array_combine($headers, $row);
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Failed to download Amazon report', [
                'document_id' => $reportDocumentId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getActiveListingsReport(): ?array
    {
        $reportId = $this->requestReport('GET_MERCHANT_LISTINGS_ALL_DATA');

        // Poll for completion (max 10 attempts, 30s apart)
        for ($i = 0; $i < 10; $i++) {
            sleep(30);
            $status = $this->getReportStatus($reportId);

            if (($status['processingStatus'] ?? '') === 'DONE') {
                $docId = $status['reportDocumentId'] ?? null;

                return $docId ? $this->downloadReport($docId) : null;
            }

            if (in_array($status['processingStatus'] ?? '', ['CANCELLED', 'FATAL'])) {
                return null;
            }
        }

        return null;
    }
}
