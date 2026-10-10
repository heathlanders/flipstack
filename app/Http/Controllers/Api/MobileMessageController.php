<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Communication\MarketplaceMessage;
use App\Models\Integration\IntegrationCredential;
use App\Services\Communication\EbayMessageService;
use App\Services\Communication\MessageSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileMessageController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        protected MessageSyncService $syncService
    ) {}

    /**
     * GET /api/messages
     * Inbox list with filters + unread/marketplace stats.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|string|in:unread,read,replied,archived',
            'marketplace' => 'nullable|string|in:ebay,shopify,amazon',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $organizationId = $this->organizationId($request);

        $query = MarketplaceMessage::with([
            'customer:id,name',
            'order:id,marketplace_order_id',
            'product:id,name,sku',
        ])
            ->where('organization_id', $organizationId)
            ->whereNot('status', MarketplaceMessage::STATUS_ARCHIVED);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('marketplace')) {
            $query->where('marketplace', $request->input('marketplace'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'ilike', "%{$search}%")
                    ->orWhere('body', 'ilike', "%{$search}%")
                    ->orWhere('sender_name', 'ilike', "%{$search}%");
            });
        }

        $perPage = (int) min((int) $request->input('per_page', 20), 100);
        $messages = $query->orderByDesc('received_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $messages->getCollection()->map(fn ($m) => $this->formatMessage($m)),
            'pagination' => [
                'total' => $messages->total(),
                'per_page' => $messages->perPage(),
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
            ],
            'stats' => $this->syncService->getInboxStats((string) $organizationId),
        ]);
    }

    /**
     * GET /api/messages/{message}
     * Thread detail (all messages in the conversation + replies). Marks read.
     */
    public function show(Request $request, MarketplaceMessage $message): JsonResponse
    {
        if ($message->organization_id !== $this->organizationId($request)) {
            return response()->json(['success' => false, 'message' => 'Message not found'], 404);
        }

        $message->load(['customer:id,name', 'order:id,marketplace_order_id', 'product:id,name,sku', 'replies.sender:id,name']);

        $thread = collect([$message]);
        if ($message->marketplace_thread_id) {
            $thread = MarketplaceMessage::where('organization_id', $message->organization_id)
                ->where('marketplace_thread_id', $message->marketplace_thread_id)
                ->with(['replies.sender:id,name', 'product:id,name,sku'])
                ->orderBy('received_at')
                ->orderBy('created_at')
                ->get();
        }

        $this->syncService->markAsRead($message);

        return response()->json([
            'success' => true,
            'message' => $this->formatMessage($message->fresh(['customer:id,name', 'order:id,marketplace_order_id', 'product:id,name,sku', 'replies.sender:id,name']), withReplies: true),
            'thread' => $thread->map(fn ($m) => $this->formatMessage($m, withReplies: true)),
        ]);
    }

    /**
     * POST /api/messages/{message}/reply
     * Send a reply (via eBay API when a credential exists, else stored locally).
     */
    public function reply(Request $request, MarketplaceMessage $message): JsonResponse
    {
        if ($message->organization_id !== $this->organizationId($request)) {
            return response()->json(['success' => false, 'message' => 'Message not found'], 404);
        }

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $user = $request->user();

        $credential = IntegrationCredential::where('organization_id', $message->organization_id)
            ->where('platform', 'ebay')
            ->where('is_active', true)
            ->first();

        if ($credential) {
            // sendReply creates the reply, updates the message, and emits the
            // message.replied audit (source = mobile) on success.
            $reply = (new EbayMessageService($credential))
                ->sendReply($message, $validated['body'], (int) $user->id, 'mobile');
        } else {
            $reply = $message->replies()->create([
                'body' => $validated['body'],
                'sent_by' => $user->id,
                'sent_at' => now(),
                'status' => 'failed',
            ]);
            $message->update([
                'status' => MarketplaceMessage::STATUS_REPLIED,
                'replied_at' => now(),
            ]);

            audit_event(
                action: 'message.replied',
                description: "Replied to {$message->marketplace} message from {$message->sender_name}",
                subject: $message,
                metadata: [
                    'marketplace' => $message->marketplace,
                    'reply_id' => (string) $reply->id,
                    'delivered' => false,
                    'source' => 'mobile',
                ],
                severity: 'info',
                category: 'communication',
            );
        }

        return response()->json([
            'success' => true,
            'message' => $this->formatMessage($message->fresh(['replies.sender:id,name']), withReplies: true),
            'reply' => [
                'id' => (string) $reply->id,
                'body' => $reply->body,
                'status' => $reply->status,
                'sent_at' => $reply->sent_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * POST /api/messages/{message}/mark-read
     */
    public function markRead(Request $request, MarketplaceMessage $message): JsonResponse
    {
        if ($message->organization_id !== $this->organizationId($request)) {
            return response()->json(['success' => false, 'message' => 'Message not found'], 404);
        }

        $this->syncService->markAsRead($message);

        return response()->json(['success' => true, 'message' => $this->formatMessage($message->fresh())]);
    }

    /**
     * POST /api/messages/{message}/archive
     */
    public function archive(Request $request, MarketplaceMessage $message): JsonResponse
    {
        if ($message->organization_id !== $this->organizationId($request)) {
            return response()->json(['success' => false, 'message' => 'Message not found'], 404);
        }

        $this->syncService->archive($message);

        return response()->json(['success' => true, 'message' => $this->formatMessage($message->fresh())]);
    }

    private function organizationId(Request $request): int
    {
        return $this->resolveOrganizationId($request->user())
            ?? abort(response()->json([
                'success' => false,
                'message' => 'No organization associated with this account.',
            ], 422));
    }

    private function formatMessage(MarketplaceMessage $m, bool $withReplies = false): array
    {
        $data = [
            'id' => $m->id,
            'marketplace' => $m->marketplace,
            'direction' => $m->direction,
            'subject' => $m->subject,
            'body' => $m->body,
            'sender_name' => $m->sender_name,
            'status' => $m->status,
            'is_unread' => $m->status === MarketplaceMessage::STATUS_UNREAD,
            'received_at' => $m->received_at?->toIso8601String(),
            'created_at' => $m->created_at?->toIso8601String(),
            'customer' => $m->relationLoaded('customer') && $m->customer ? [
                'id' => $m->customer->id,
                'name' => $m->customer->name,
            ] : null,
            'order' => $m->relationLoaded('order') && $m->order ? [
                'id' => $m->order->id,
                'marketplace_order_id' => $m->order->marketplace_order_id,
            ] : null,
            'product' => $m->relationLoaded('product') && $m->product ? [
                'id' => $m->product->id,
                'name' => $m->product->name,
                'sku' => $m->product->sku,
            ] : null,
        ];

        if ($withReplies && $m->relationLoaded('replies')) {
            $data['replies'] = $m->replies->map(fn ($r) => [
                'id' => $r->id,
                'body' => $r->body,
                'status' => $r->status,
                'sent_at' => $r->sent_at?->toIso8601String(),
                'sender' => $r->relationLoaded('sender') && $r->sender ? [
                    'id' => $r->sender->id,
                    'name' => $r->sender->name,
                ] : null,
            ])->values();
        }

        return $data;
    }
}
