<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use App\Models\InboxMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InboxMessageController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->tenantQuery(InboxMessage::class)
            ->with(['emailAccount', 'matchedContact', 'matchedOpportunity']);

        if ($reviewStatus = $request->input('review_status')) {
            $query->where('review_status', $reviewStatus);
        }
        if ($sentiment = $request->input('sentiment')) {
            $query->where('sentiment', $sentiment);
        }
        if ($accountId = $request->input('email_account_id')) {
            $query->where('email_account_id', $accountId);
        }

        $messages = $query->orderByDesc('received_at')->paginate(25)->withQueryString();

        $emailAccounts = $this->tenantQuery(EmailAccount::class)
            ->orderBy('name')
            ->get();

        return view('inbox.index', compact('messages', 'emailAccounts'));
    }

    public function show(Request $request, int $id): View
    {
        $message = $this->tenantQuery(InboxMessage::class)
            ->with(['emailAccount', 'matchedContact', 'matchedOpportunity', 'matchedOutbound'])
            ->findOrFail($id);

        // Auto-mark as read when viewed
        if (!$message->is_read) {
            $message->update(['is_read' => true]);
        }

        return view('inbox.show', compact('message'));
    }

    public function markReviewed(Request $request, int $id): RedirectResponse
    {
        $message = $this->tenantQuery(InboxMessage::class)->findOrFail($id);

        $request->validate([
            'review_status' => 'required|in:reviewed,pending,ignored',
            'sentiment'     => 'nullable|in:positive,neutral,negative',
        ]);

        $message->update([
            'review_status' => $request->input('review_status'),
            'sentiment'     => $request->input('sentiment', $message->sentiment),
            'is_read'       => true,
        ]);

        return redirect()->back()->with('success', 'Message marked as reviewed.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $message = $this->tenantQuery(InboxMessage::class)->findOrFail($id);

        $message->delete();

        return redirect()->route('inbox.index')->with('success', 'Message deleted.');
    }
}
