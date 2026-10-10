<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\Communication\MarketplaceMessage;
use App\Models\Integration\IntegrationCredential;
use App\Services\Communication\EbayMessageService;
use App\Services\Communication\MessageSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function __construct(
        protected MessageSyncService $syncService
    ) {
        $this->middleware(['auth']);
    }

    /**
     * Display the message inbox.
     */
    public function inbox(Request $request): Response
    {
        $user = Auth::user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        $query = MarketplaceMessage::where('organization_id', $organizationId)
            ->with(['customer', 'order', 'product:id,name,sku'])
            ->whereNot('status', MarketplaceMessage::STATUS_ARCHIVED);

        // Filter by marketplace
        if ($request->filled('marketplace')) {
            $query->where('marketplace', $request->input('marketplace'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $messages = $query->orderByDesc('received_at')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $stats = $this->syncService->getInboxStats($organizationId);

        return Inertia::render('Communication/Inbox', [
            'messages' => $messages,
            'stats' => $stats,
            'filters' => $request->only(['marketplace', 'status']),
        ]);
    }

    /**
     * Display a message thread.
     */
    public function thread(Request $request, MarketplaceMessage $message): Response
    {
        $message->load(['customer', 'order', 'product:id,name,sku', 'replies.sender']);

        // Load related messages in the same thread
        $threadMessages = collect([$message]);
        if ($message->marketplace_thread_id) {
            $threadMessages = MarketplaceMessage::where('marketplace_thread_id', $message->marketplace_thread_id)
                ->with(['replies.sender', 'product:id,name,sku'])
                ->orderBy('received_at')
                ->orderBy('created_at')
                ->get();
        }

        // Mark as read
        $this->syncService->markAsRead($message);

        return Inertia::render('Communication/Thread', [
            'message' => $message,
            'threadMessages' => $threadMessages,
        ]);
    }

    /**
     * Send a reply to a message.
     */
    public function reply(Request $request, MarketplaceMessage $message)
    {
        $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $user = Auth::user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        // Resolve eBay credential for the org to send reply via API
        $credential = IntegrationCredential::where('organization_id', $organizationId)
            ->where('platform', 'ebay')
            ->where('is_active', true)
            ->first();

        if ($credential) {
            $ebayService = new EbayMessageService($credential);
            $ebayService->sendReply($message, $request->input('body'), $user->id);
        } else {
            // Fallback: save reply locally without sending to eBay
            $message->replies()->create([
                'body' => $request->input('body'),
                'sent_by' => $user->id,
                'sent_at' => now(),
                'status' => 'failed',
            ]);
            $message->update([
                'status' => MarketplaceMessage::STATUS_REPLIED,
                'replied_at' => now(),
            ]);
        }

        return redirect()->back()->with('success', 'Reply sent successfully.');
    }

    /**
     * Mark a message as read.
     */
    public function markRead(MarketplaceMessage $message)
    {
        $this->syncService->markAsRead($message);

        return redirect()->back()->with('success', 'Message marked as read.');
    }

    /**
     * Archive a message.
     */
    public function archive(MarketplaceMessage $message)
    {
        $this->syncService->archive($message);

        return redirect()->route('messages.inbox')->with('success', 'Message archived.');
    }
}
