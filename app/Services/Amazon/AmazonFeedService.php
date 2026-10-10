<?php

namespace App\Services\Amazon;

use App\Models\Amazon\AmazonFeed;
use App\Models\Amazon\AmazonSyncLog;

class AmazonFeedService extends AmazonBaseService
{
    public function submitFeed(string $feedType, array $feedData, int $teamId, ?int $userId = null): AmazonFeed
    {
        $syncLog = $this->createSyncLog(AmazonSyncLog::OP_SUBMIT_FEED);

        // Create feed document
        $docResponse = $this->post('/feeds/2021-06-30/documents', [
            'contentType' => 'application/json',
        ]);

        $documentId = $docResponse['feedDocumentId'] ?? null;
        $uploadUrl = $docResponse['url'] ?? null;

        if (! $uploadUrl) {
            throw new AmazonApiException('Failed to create feed document');
        }

        // Upload feed content
        \Illuminate\Support\Facades\Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->put($uploadUrl, $feedData);

        // Submit feed
        $feedResponse = $this->post('/feeds/2021-06-30/feeds', [
            'feedType' => $feedType,
            'marketplaceIds' => [$this->marketplaceId],
            'inputFeedDocumentId' => $documentId,
        ], $syncLog);

        $feedId = $feedResponse['feedId'] ?? '';

        return AmazonFeed::create([
            'integration_credential_id' => $this->credential->id,
            'team_id' => $teamId,
            'feed_id' => $feedId,
            'feed_type' => $feedType,
            'status' => AmazonFeed::STATUS_SUBMITTED,
            'total_records' => count($feedData['messages'] ?? $feedData),
            'initiated_by' => $userId,
            'submitted_at' => now(),
        ]);
    }

    public function pollFeedStatus(AmazonFeed $feed): AmazonFeed
    {
        $syncLog = $this->createSyncLog(AmazonSyncLog::OP_POLL_FEED, [
            'feed_id' => $feed->feed_id,
        ]);

        $response = $this->get("/feeds/2021-06-30/feeds/{$feed->feed_id}", [], $syncLog);

        $status = $response['processingStatus'] ?? $feed->status;
        $resultDocId = $response['resultFeedDocumentId'] ?? null;

        $mappedStatus = match ($status) {
            'IN_QUEUE', 'IN_PROGRESS' => AmazonFeed::STATUS_IN_PROGRESS,
            'DONE' => AmazonFeed::STATUS_DONE,
            'CANCELLED' => AmazonFeed::STATUS_CANCELLED,
            'FATAL' => AmazonFeed::STATUS_FATAL,
            default => $feed->status,
        };

        $updateData = ['status' => $mappedStatus];

        if ($resultDocId) {
            $updateData['result_document_id'] = $resultDocId;
        }

        if (in_array($mappedStatus, [AmazonFeed::STATUS_DONE, AmazonFeed::STATUS_CANCELLED, AmazonFeed::STATUS_FATAL])) {
            $updateData['completed_at'] = now();
        }

        $feed->update($updateData);

        return $feed->fresh();
    }

    public function getFeedResult(AmazonFeed $feed): ?array
    {
        if (! $feed->result_document_id) {
            return null;
        }

        $response = $this->get("/feeds/2021-06-30/documents/{$feed->result_document_id}");
        $downloadUrl = $response['url'] ?? null;

        if (! $downloadUrl) {
            return null;
        }

        $result = \Illuminate\Support\Facades\Http::get($downloadUrl)->json();

        // Update feed with results
        $successful = 0;
        $failed = 0;
        $errors = [];

        foreach ($result as $item) {
            if (($item['status'] ?? '') === 'SUCCESS') {
                $successful++;
            } else {
                $failed++;
                $errors[] = [
                    'sku' => $item['sku'] ?? null,
                    'code' => $item['code'] ?? null,
                    'message' => $item['message'] ?? 'Unknown error',
                ];
            }
        }

        $feed->markDone($successful, $failed, $errors, $feed->result_document_id);

        return $result;
    }
}
