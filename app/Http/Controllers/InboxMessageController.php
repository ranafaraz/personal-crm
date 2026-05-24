<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use App\Models\InboxMessage;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InboxMessageController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->tenantQuery(InboxMessage::class)
            ->with(['emailAccount', 'matchedContact', 'matchedOpportunity', 'tags']);

        if ($reviewStatus = $request->input('review_status')) {
            $query->where('review_status', $reviewStatus);
        }
        if ($sentiment = $request->input('sentiment')) {
            $query->where('sentiment', $sentiment);
        }
        if ($accountId = $request->input('email_account_id')) {
            $query->where('email_account_id', $accountId);
        }
        if ($tagId = $request->input('tag')) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('from_email', 'like', "%{$search}%")
                  ->orWhere('from_name', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $messages = $query->orderByDesc('received_at')->paginate(25)->withQueryString();

        $emailAccounts = $this->tenantQuery(EmailAccount::class)->orderBy('name')->get();
        $tags          = $this->tenantQuery(Tag::class)->orderBy('name')->get();

        return view('inbox.index', compact('messages', 'emailAccounts', 'tags'));
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
