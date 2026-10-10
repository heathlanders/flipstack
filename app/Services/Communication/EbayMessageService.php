<?php

namespace App\Services\Communication;

use App\Models\Communication\MarketplaceMessage;
use App\Models\Communication\MarketplaceMessageReply;
use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use App\Services\EbayOAuthService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbayMessageService
{
    protected string $tradingApiUrl = 'https://api.ebay.com/ws/api.dll';

    protected int $compatibilityLevel = 967;

    protected int $siteId = 0;

    protected IntegrationCredential $credential;

    protected ?OAuthToken $token = null;

    public function __construct(IntegrationCredential $credential)
    {
        $this->credential = $credential;

        if ($credential->environment === 'sandbox') {
            $this->tradingApiUrl = 'https://api.sandbox.ebay.com/ws/api.dll';
        }
    }

    /**
     * Fetch messages from eBay Trading API (GetMyMessages).
     * Two-phase: headers first (to get IDs), then full messages in batches.
     */
    public function fetchMessages(?Carbon $since = null): Collection
    {
        $token = $this->getToken();
        if (! $token) {
            Log::warning('EbayMessageService: no active token, skipping fetch', [
                'credential_id' => $this->credential->id,
            ]);

            return collect();
        }

        $since = $since ?? now()->subDays(7);
        $messages = collect();

        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $messageIds = $this->fetchMessageHeaders($since, $page);

            if ($messageIds->isEmpty()) {
                break;
            }

            // Batch fetch full message details, 10 at a time
            foreach ($messageIds->chunk(10) as $idBatch) {
                $batchMessages = $this->fetchMessageDetails($idBatch->values()->all());
                $messages = $messages->merge($batchMessages);
            }

            // eBay returns up to 200 headers per page
            $hasMore = $messageIds->count() >= 200;
            $page++;
        }

        Log::info('EbayMessageService: fetched messages', [
            'credential_id' => $this->credential->id,
            'count' => $messages->count(),
        ]);

        return $messages;
    }

    /**
     * Fetch message headers (IDs + metadata) for a date range.
     */
    protected function fetchMessageHeaders(Carbon $since, int $page = 1): Collection
    {
        $startTime = $since->toIso8601String();
        $endTime = now()->toIso8601String();

        $xml = '<?xml version="1.0" encoding="utf-8"?>
<GetMyMessagesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <RequesterCredentials>
        <eBayAuthToken>'.htmlspecialchars($this->getToken()->decrypted_access_token).'</eBayAuthToken>
    </RequesterCredentials>
    <DetailLevel>ReturnHeaders</DetailLevel>
    <StartTime>'.$startTime.'</StartTime>
    <EndTime>'.$endTime.'</EndTime>
    <Pagination>
        <EntriesPerPage>200</EntriesPerPage>
        <PageNumber>'.$page.'</PageNumber>
    </Pagination>
</GetMyMessagesRequest>';

        $response = $this->callTradingApi('GetMyMessages', $xml);

        if (! $response) {
            return collect();
        }

        $messageIds = collect();

        if (isset($response->Messages->Message)) {
            foreach ($response->Messages->Message as $msg) {
                $messageIds->push((string) $msg->MessageID);
            }
        }

        Log::info('EbayMessageService: fetched message headers', [
            'credential_id' => $this->credential->id,
            'page' => $page,
            'count' => $messageIds->count(),
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        return $messageIds;
    }

    /**
     * Fetch full message details for a batch of message IDs.
     */
    protected function fetchMessageDetails(array $messageIds): Collection
    {
        $idsXml = '';
        foreach ($messageIds as $id) {
            $idsXml .= '<MessageID>'.htmlspecialchars($id).'</MessageID>';
        }

        $xml = '<?xml version="1.0" encoding="utf-8"?>
<GetMyMessagesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <RequesterCredentials>
        <eBayAuthToken>'.htmlspecialchars($this->getToken()->decrypted_access_token).'</eBayAuthToken>
    </RequesterCredentials>
    <DetailLevel>ReturnMessages</DetailLevel>
    <MessageIDs>'.$idsXml.'</MessageIDs>
</GetMyMessagesRequest>';

        $response = $this->callTradingApi('GetMyMessages', $xml);

        if (! $response) {
            return collect();
        }

        $messages = collect();

        // Buyer-relevant message types to keep
        $allowedTypes = [
            'AskSellerQuestion',
            'ResponseToASQQuestion',
            'ContactEbayMember',
            'ContactTransactionPartner',
            'ContacteBayMemberViaCommunityLink',
        ];

        if (isset($response->Messages->Message)) {
            foreach ($response->Messages->Message as $msg) {
                $messageType = (string) ($msg->MessageType ?? '');

                // Skip system/marketing messages, allow buyer-relevant and unknown types
                if ($messageType !== '' && ! in_array($messageType, $allowedTypes)) {
                    Log::debug('EbayMessageService: skipping message type', [
                        'type' => $messageType,
                        'subject' => (string) ($msg->Subject ?? ''),
                        'message_id' => (string) $msg->MessageID,
                    ]);

                    continue;
                }

                $messages->push([
                    'marketplace_message_id' => (string) $msg->MessageID,
                    'marketplace_thread_id' => (string) ($msg->ExternalMessageID ?? $msg->MessageID),
                    'marketplace_item_id' => (string) ($msg->ItemID ?? ''),
                    'message_type' => $messageType ?: null,
                    'subject' => (string) ($msg->Subject ?? ''),
                    'body' => $this->extractMessageBody((string) ($msg->Text ?? '')),
                    'sender_name' => (string) ($msg->Sender ?? ''),
                    'sender_id' => (string) ($msg->SendingUserID ?? $msg->Sender ?? ''),
                    'received_at' => ! empty((string) ($msg->ReceiveDate ?? ''))
                        ? Carbon::parse((string) $msg->ReceiveDate)
                        : now(),
                    'direction' => $this->determineDirection($msg),
                ]);
            }
        }

        Log::info('EbayMessageService: processed message details', [
            'credential_id' => $this->credential->id,
            'requested' => count($messageIds),
            'returned' => $messages->count(),
        ]);

        return $messages;
    }

    /**
     * Extract the actual message text from eBay's HTML email wrapper.
     * eBay wraps buyer messages in a full HTML email template with CSS,
     * tracking pixels, and conversation history. The actual message is
     * inside a <div id="UserInputtedText"> tag.
     */
    protected function extractMessageBody(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // If it doesn't look like HTML, return as-is
        if (! str_contains($html, '<') || ! str_contains($html, '>')) {
            return trim($html);
        }

        // Extract the buyer's actual message from the UserInputtedText div
        if (preg_match('/<div\s+id="UserInputtedText">(.*?)<\/div>/si', $html, $matches)) {
            $body = $matches[1];
            $body = preg_replace('/<br\s*\/?>/i', "\n", $body);
            $body = strip_tags($body);
            $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $body = trim($body);

            if (! empty($body)) {
                return $body;
            }
        }

        // Fallback: strip all HTML and clean up
        $body = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
        $body = preg_replace('/<br\s*\/?>/i', "\n", $body);
        $body = strip_tags($body);
        $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = preg_replace('/[ \t]+/', ' ', $body);
        $body = preg_replace('/\n[ \t]+/', "\n", $body);
        $body = preg_replace('/\n{3,}/', "\n\n", $body);

        return trim($body);
    }

    /**
     * Determine message direction based on sender.
     */
    protected function determineDirection(\SimpleXMLElement $msg): string
    {
        // If the message is flagged as a response or the sender matches the credential owner, it's outbound
        $flagged = (string) ($msg->Flagged ?? 'false');
        $responded = (string) ($msg->Responded ?? 'false');

        // Messages in folder 0 (Inbox) from other users are inbound
        $folderId = (string) ($msg->Folder->FolderID ?? '0');
        if ($folderId === '1') {
            // Sent folder
            return MarketplaceMessage::DIRECTION_OUTBOUND;
        }

        return MarketplaceMessage::DIRECTION_INBOUND;
    }

    /**
     * Send a reply to a marketplace message via eBay Trading API.
     */
    public function sendReply(MarketplaceMessage $message, string $body, int $userId, string $source = 'web'): MarketplaceMessageReply
    {
        $token = $this->getToken();

        // If we have a token and the message has the required fields, send via API
        if ($token && $message->marketplace_item_id && $message->sender_id) {
            $xml = '<?xml version="1.0" encoding="utf-8"?>
<AddMemberMessageAAQToPartnerRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <RequesterCredentials>
        <eBayAuthToken>'.htmlspecialchars($token->decrypted_access_token).'</eBayAuthToken>
    </RequesterCredentials>
    <ItemID>'.htmlspecialchars($message->marketplace_item_id).'</ItemID>
    <MemberMessage>
        <Body>'.htmlspecialchars($body).'</Body>
        <ParentMessageID>'.htmlspecialchars($message->marketplace_message_id).'</ParentMessageID>
        <RecipientID>'.htmlspecialchars($message->sender_id).'</RecipientID>
        <Subject>'.htmlspecialchars('Re: '.$message->subject).'</Subject>
    </MemberMessage>
</AddMemberMessageAAQToPartnerRequest>';

            $response = $this->callTradingApi('AddMemberMessageAAQToPartner', $xml);

            if (! $response || (string) ($response->Ack ?? '') === 'Failure') {
                $errorMsg = 'Unknown error';
                if ($response && isset($response->Errors->ShortMessage)) {
                    $errorMsg = (string) $response->Errors->ShortMessage;
                }

                Log::error('EbayMessageService: failed to send reply via eBay API', [
                    'message_id' => $message->id,
                    'error' => $errorMsg,
                ]);

                $reply = MarketplaceMessageReply::create([
                    'message_id' => $message->id,
                    'body' => $body,
                    'sent_by' => $userId,
                    'sent_at' => now(),
                    'status' => MarketplaceMessageReply::STATUS_FAILED,
                ]);

                $message->update([
                    'status' => MarketplaceMessage::STATUS_REPLIED,
                    'replied_at' => now(),
                ]);

                return $reply;
            }

            Log::info('EbayMessageService: reply sent via eBay API', [
                'message_id' => $message->id,
            ]);
        }

        $reply = MarketplaceMessageReply::create([
            'message_id' => $message->id,
            'body' => $body,
            'sent_by' => $userId,
            'sent_at' => now(),
            'status' => MarketplaceMessageReply::STATUS_SENT,
        ]);

        $message->update([
            'status' => MarketplaceMessage::STATUS_REPLIED,
            'replied_at' => now(),
        ]);

        $responseSeconds = $message->received_at
            ? (int) $message->received_at->diffInSeconds(now())
            : null;

        audit_event(
            action: 'message.replied',
            description: "Replied to {$message->marketplace} message from {$message->sender_name}",
            subject: $message,
            metadata: [
                'marketplace' => $message->marketplace,
                'reply_id' => $reply->id,
                'response_seconds' => $responseSeconds,
                'source' => $source,
            ],
            severity: 'info',
            category: 'communication',
            actor: \App\Models\User\User::find($userId),
        );

        return $reply;
    }

    /**
     * Make a Trading API call.
     */
    protected function callTradingApi(string $callName, string $xml): ?\SimpleXMLElement
    {
        try {
            $response = Http::withHeaders([
                'X-EBAY-API-IAF-TOKEN' => $this->getToken()->decrypted_access_token,
                'X-EBAY-API-SITEID' => (string) $this->siteId,
                'X-EBAY-API-COMPATIBILITY-LEVEL' => (string) $this->compatibilityLevel,
                'X-EBAY-API-CALL-NAME' => $callName,
                'Content-Type' => 'text/xml',
            ])->withBody($xml, 'text/xml')
                ->timeout(30)
                ->post($this->tradingApiUrl);

            if (! $response->successful()) {
                Log::error("EbayMessageService: {$callName} HTTP error", [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);

                return null;
            }

            libxml_use_internal_errors(true);
            $parsed = simplexml_load_string($response->body());

            if ($parsed === false) {
                Log::error("EbayMessageService: {$callName} XML parse error", [
                    'body' => substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $ack = (string) ($parsed->Ack ?? '');
            if ($ack === 'Failure') {
                $errorMsg = (string) ($parsed->Errors->ShortMessage ?? 'Unknown');
                $errorCode = (string) ($parsed->Errors->ErrorCode ?? '');
                Log::error("EbayMessageService: {$callName} API failure", [
                    'ack' => $ack,
                    'error_code' => $errorCode,
                    'error' => $errorMsg,
                    'long_message' => (string) ($parsed->Errors->LongMessage ?? ''),
                ]);

                return $parsed;
            }

            return $parsed;
        } catch (\Exception $e) {
            Log::error("EbayMessageService: {$callName} exception", [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get active OAuth token, refreshing if needed.
     */
    protected function getToken(): ?OAuthToken
    {
        if ($this->token) {
            return $this->token;
        }

        $this->token = $this->credential->getActiveEbayToken();

        if (! $this->token) {
            return null;
        }

        if ($this->token->needsRefresh()) {
            try {
                $oauthService = new EbayOAuthService($this->credential);
                $this->token = $oauthService->refreshToken($this->token);
            } catch (\Exception $e) {
                Log::error('EbayMessageService: token refresh failed', [
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        }

        return $this->token;
    }
}
